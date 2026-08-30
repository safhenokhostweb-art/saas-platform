<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;
use App\Services\CustomerInstallation\Ssl\VpsSslProvisioner;
use App\Services\CustomerInstallation\Stages\ConfigureWebServerStage;
use App\Services\CustomerInstallation\StageCapabilityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F5 — the capability-gate coverage (cPanel skip / VPS unchanged
 * base flow) is unchanged this phase.
 *
 * Phase 15-B1 — ConfigureWebServerStage's HTTPS-block decision (per domain,
 * via ArvanCdnDomainDecider/VpsSslProvisioner) — new tests below cover the
 * three resolution outcomes: SSL not requested, Arvan-managed domain (shared
 * cert is architecturally correct), and a non-Arvan domain with no
 * certificate issued yet (HTTP-only, deferred to IssueSslStage).
 *
 * Phase 15-B2 — execute()'s body moved into NginxVhostWriter (see its
 * docblock); this Stage is now a thin wrapper, so these tests construct it
 * with a real NginxVhostWriter instance and assert on the same observable
 * behavior as before — no behavior change.
 */
class ConfigureWebServerStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function stage(): ConfigureWebServerStage
    {
        return new ConfigureWebServerStage(new NginxVhostWriter(new ArvanCdnDomainDecider(), new VpsSslProvisioner()));
    }

    private function context(FakeRemoteDriver $driver, bool $sslEnabled = false, string $domain = 'webserver.example.com'): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'WebServer Stage Co',
            'customer_email'    => 'webserver@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'WebServer Stage Co', 'owner_name' => 'Owner', 'email' => 'webserver@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => $sslEnabled, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    /**
     * This is the exact check CustomerInstallationOrchestrator's loop
     * performs (Phase 14-E) before ever calling execute() — proving cPanel
     * is skipped means proving no SSH command touching nginx is ever
     * issued, without needing to run the full Orchestrator.
     */
    public function test_gate_skips_this_stage_on_cpanel_before_execute_would_run(): void
    {
        $stage = $this->stage();
        $cpanelServer = new CustomerRemoteServer([
            'type' => 'cpanel', 'host' => 'box.example.test',
            'username' => 'cust1', 'auth_method' => 'password',
        ]);
        $cpanelDriver = new CpanelDeploymentDriver($cpanelServer, new CredentialVault());
        $gate = new StageCapabilityGate();

        $this->assertFalse($gate->isSatisfied($stage, $cpanelDriver), 'cPanel must not satisfy supportsWebServerConfiguration');
        $this->assertFalse($stage->isCapabilityRequiredToProceed(), 'Missing this capability must skip, not fail, the installation');
    }

    public function test_vps_nginx_flow_is_unchanged_when_ssl_not_requested(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        $ctx = $this->context($driver, sslEnabled: false);

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertFalse($result->output['ssl_block_written']);

        $log = $driver->getCommandLog();
        $writeIdx  = $this->firstIndexContaining($log, 'sites-available');
        $enableIdx = $this->firstIndexContaining($log, 'ln -sf');
        $testIdx   = $this->firstIndexContaining($log, 'nginx -t');
        $reloadIdx = $this->firstIndexContaining($log, 'nginx -s reload');

        $this->assertNotNull($writeIdx, 'nginx config must be written');
        $this->assertNotNull($enableIdx, 'site must be enabled via sites-enabled symlink');
        $this->assertNotNull($testIdx, 'nginx config must be tested');
        $this->assertNotNull($reloadIdx, 'nginx must be reloaded');
        $this->assertLessThan($enableIdx, $writeIdx);
        $this->assertLessThan($testIdx, $enableIdx);
        $this->assertLessThan($reloadIdx, $testIdx);

        // No HTTPS block requested -> no certificate probe should even run.
        $this->assertNull($this->firstIndexContaining($log, 'letsencrypt'));
    }

    public function test_arvan_managed_domain_writes_https_block_with_shared_platform_certificate(): void
    {
        DnsProviderSetting::create([
            'provider' => 'arvan', 'root_domain' => 'safhenokhost.ir',
            'is_active' => true, 'cloud_proxy' => true,
            'api_token_encrypted' => (new CredentialVault())->encrypt('token'),
        ]);

        $driver = new FakeRemoteDriver();
        $driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        $ctx = $this->context($driver, sslEnabled: true, domain: 'tenant1.safhenokhost.ir');

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertTrue($result->output['ssl_block_written']);

        // Never a live certbot/openssl probe for an Arvan-managed domain.
        $this->assertNull($this->firstIndexContaining($driver->getCommandLog(), 'letsencrypt'));
    }

    public function test_non_arvan_domain_without_issued_certificate_writes_http_only(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        // No canned response for "test -f .../fullchain.pem" -> FakeRemoteDriver
        // default (exit 0, empty stdout) -> checkCertificate() sees no "yes" -> inactive.
        $ctx = $this->context($driver, sslEnabled: true, domain: 'example.com');

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertFalse($result->output['ssl_block_written'], 'No certificate issued yet — HTTPS block must be deferred to IssueSslStage');
    }

    public function test_non_arvan_domain_with_already_issued_certificate_writes_https_block(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        $driver->addResponse('test -f', 0, 'yes');
        $driver->addResponse('openssl x509', 0, 'notAfter=Sep  7 00:00:00 2026 GMT');
        $ctx = $this->context($driver, sslEnabled: true, domain: 'example.com');

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertTrue($result->output['ssl_block_written']);
    }

    private function firstIndexContaining(array $log, string $needle): ?int
    {
        foreach ($log as $index => $command) {
            if (str_contains($command, $needle)) {
                return $index;
            }
        }

        return null;
    }

}
