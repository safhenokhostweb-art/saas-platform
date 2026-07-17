<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use App\Services\CustomerInstallation\Ssl\CpanelSslProvisioner;
use App\Services\CustomerInstallation\Ssl\Dto\SslResult;
use App\Services\CustomerInstallation\Ssl\NginxVhostWriter;
use App\Services\CustomerInstallation\Ssl\SslProvisionerFactory;
use App\Services\CustomerInstallation\Ssl\SslProvisionerInterface;
use App\Services\CustomerInstallation\Ssl\VpsSslProvisioner;
use App\Services\CustomerInstallation\Stages\IssueSslStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 15-B1/15-B2 — IssueSslStage is exercised against spy provisioners
 * and a spy NginxVhostWriter so these tests prove the STAGE's own decision
 * logic (Arvan short-circuit, per-driver failure severity, refreshing the
 * vhost via the shared writer rather than another Stage) independent of
 * whether Certbot/UAPI actually succeed — those mechanisms are covered by
 * VpsSslProvisionerTest/CpanelSslProvisionerTest.
 *
 * Phase 15-B2 — no longer depends on ConfigureWebServerStage at all (see
 * NginxVhostWriter's docblock for why that coupling was removed).
 */
class IssueSslStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(FakeRemoteDriver $driver, bool $sslEnabled, string $domain): InstallationContext
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
            'customer_company'  => 'Issue SSL Co',
            'customer_email'    => 'admin@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'Issue SSL Co', 'owner_name' => 'Owner', 'email' => 'admin@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => $sslEnabled, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    private function spyVpsProvisioner(?SslResult $toReturn, array &$calls): VpsSslProvisioner
    {
        return new class($toReturn, $calls) extends VpsSslProvisioner {
            public function __construct(private readonly ?SslResult $result, private array &$calls) {}
            public function issueCertificate(InstallationContext $ctx, string $domain): SslResult
            {
                $this->calls[] = $domain;
                return $this->result;
            }
        };
    }

    private function spyCpanelProvisioner(?SslResult $toReturn, array &$calls): CpanelSslProvisioner
    {
        return new class($toReturn, $calls) extends CpanelSslProvisioner {
            public function __construct(private readonly ?SslResult $result, private array &$calls) {}
            public function issueCertificate(InstallationContext $ctx, string $domain): SslResult
            {
                $this->calls[] = $domain;
                return $this->result;
            }
        };
    }

    private function vhostWriterSpy(bool $success, array &$writeCalls): NginxVhostWriter
    {
        return new class($success, $writeCalls) extends NginxVhostWriter {
            public function __construct(private readonly bool $ok, private array &$writeCalls) {}
            public function write(InstallationContext $ctx): StageResult
            {
                $this->writeCalls[] = true;

                return $this->ok
                    ? StageResult::success('CONFIGURE_WEB_SERVER', ['ssl_block_written' => true], [], 0)
                    : StageResult::failure('CONFIGURE_WEB_SERVER', 'nginx reload failed', 0);
            }
        };
    }

    private function stage(SslProvisionerInterface $vps, SslProvisionerInterface $cpanel, NginxVhostWriter $writer): IssueSslStage
    {
        return new IssueSslStage(
            new SslProvisionerFactory($vps, $cpanel),
            new ArvanCdnDomainDecider(),
            $writer,
        );
    }

    public function test_required_capabilities_and_optional_semantics(): void
    {
        $stage = $this->stage(new VpsSslProvisioner(), $this->createStub(CpanelSslProvisioner::class), new NginxVhostWriter(new ArvanCdnDomainDecider(), new VpsSslProvisioner()));

        $this->assertSame(['supportsSSL'], $stage->requiredCapabilities());
        $this->assertFalse($stage->isCapabilityRequiredToProceed(), 'A missing SSL capability at the driver level must skip, not fail — see the per-driver runtime-failure severity decision below for a separate concern');
    }

    public function test_skips_when_ssl_not_requested(): void
    {
        $calls = [];
        $writeCalls = [];
        $stage = $this->stage($this->spyVpsProvisioner(null, $calls), $this->createStub(CpanelSslProvisioner::class), $this->vhostWriterSpy(true, $writeCalls));

        $ctx = $this->context(new FakeRemoteDriver(), sslEnabled: false, domain: 'example.com');
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue($result->skipped);
        $this->assertSame([], $calls, 'No provisioner call when SSL was never requested');
        $this->assertSame([], $writeCalls);
    }

    public function test_arvan_managed_domain_is_skip_success_without_calling_any_provisioner(): void
    {
        DnsProviderSetting::create([
            'provider' => 'arvan', 'root_domain' => 'safhenokhost.ir',
            'is_active' => true, 'cloud_proxy' => true,
            'api_token_encrypted' => (new CredentialVault())->encrypt('token'),
        ]);

        $calls = [];
        $writeCalls = [];
        $stage = $this->stage($this->spyVpsProvisioner(null, $calls), $this->createStub(CpanelSslProvisioner::class), $this->vhostWriterSpy(true, $writeCalls));

        $ctx = $this->context(new FakeRemoteDriver(), sslEnabled: true, domain: 'tenant1.safhenokhost.ir');
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('arvan_cdn', $result->output['ssl_managed_by']);
        $this->assertSame('skipped_success', $result->output['status']);
        $this->assertSame([], $calls, 'Arvan-managed domains must never reach SslProvisionerFactory');
        $this->assertSame([], $writeCalls, 'Arvan-managed domains never need a vhost refresh — nothing was issued');
    }

    /**
     * Phase 15-B2 — the hardening this test locks in: VPS/Docker have no
     * fallback SSL owner for a requested-but-unissued certificate, so this
     * is now a real installation failure (would abort + dispatch rollback
     * at the orchestrator level), not a warning.
     */
    public function test_vps_failed_issuance_is_a_real_stage_failure(): void
    {
        $calls = [];
        $writeCalls = [];
        $stage = $this->stage(
            $this->spyVpsProvisioner(SslResult::failed('certbot is not installed'), $calls),
            $this->createStub(CpanelSslProvisioner::class),
            $this->vhostWriterSpy(true, $writeCalls),
        );

        $ctx = $this->context(new FakeRemoteDriver(), sslEnabled: true, domain: 'example.com');
        $result = $stage->execute($ctx);

        $this->assertFalse($result->success, 'VPS has no fallback SSL owner — a failed issuance must fail the stage');
        $this->assertStringContainsString('certbot is not installed', $result->error);
        $this->assertSame(['example.com'], $calls);
        $this->assertSame([], $writeCalls, 'No vhost refresh when issuance failed');
    }

    /**
     * Phase 15-B2 — cPanel keeps the pre-15-B2 graceful-warning behavior:
     * AutoSSL enablement is a hosting-provider/WHM decision this pipeline
     * cannot make, so a failed/skipped check must not fail the installation.
     */
    public function test_cpanel_failed_issuance_is_reported_as_a_warning_not_a_stage_failure(): void
    {
        $calls = [];
        $writeCalls = [];
        $stage = $this->stage(
            $this->createStub(VpsSslProvisioner::class),
            $this->spyCpanelProvisioner(SslResult::skipped('AutoSSL is not enabled for this account'), $calls),
            $this->vhostWriterSpy(true, $writeCalls),
        );

        $cpanelDriver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $ctx = $this->context($cpanelDriver, sslEnabled: true, domain: 'cp.example.com');
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success, 'cPanel — AutoSSL availability is outside this pipeline\'s control, must not fail the installation');
        $this->assertSame('skipped', $result->output['status']);
        $this->assertStringContainsString('AutoSSL is not enabled', $result->output['message']);
        $this->assertSame(['cp.example.com'], $calls);
    }

    public function test_successful_issuance_calls_the_shared_vhost_writer_directly(): void
    {
        $calls = [];
        $writeCalls = [];
        $stage = $this->stage(
            $this->spyVpsProvisioner(SslResult::issued('/etc/letsencrypt/live/example.com/fullchain.pem', '/etc/letsencrypt/live/example.com/privkey.pem'), $calls),
            $this->createStub(CpanelSslProvisioner::class),
            $this->vhostWriterSpy(true, $writeCalls),
        );

        $ctx = $this->context(new FakeRemoteDriver(), sslEnabled: true, domain: 'example.com');
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('issued', $result->output['status']);
        $this->assertTrue($result->output['web_server_reconfigured']);
        $this->assertCount(1, $writeCalls, 'NginxVhostWriter::write() must be called exactly once after a successful issuance');
    }

    public function test_vhost_refresh_failure_does_not_fail_the_ssl_stage(): void
    {
        $calls = [];
        $writeCalls = [];
        $stage = $this->stage(
            $this->spyVpsProvisioner(SslResult::issued('/etc/letsencrypt/live/example.com/fullchain.pem', '/etc/letsencrypt/live/example.com/privkey.pem'), $calls),
            $this->createStub(CpanelSslProvisioner::class),
            $this->vhostWriterSpy(false, $writeCalls),
        );

        $ctx = $this->context(new FakeRemoteDriver(), sslEnabled: true, domain: 'example.com');
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success, 'Certificate is already issued and site still reachable over HTTP — must not fail');
        $this->assertFalse($result->output['web_server_reconfigured']);
    }

    /**
     * Phase 15-G — Part 5: cPanel/WHM already declares
     * supportsWebServerConfiguration=false (it manages its own vhost/SSL
     * binding), yet a successful issuance called NginxVhostWriter::write()
     * unconditionally — an SSH write to /etc/nginx/... on an account that
     * can't touch that path, on every single cPanel SSL issuance. This test
     * locks in the fix: the vhost writer must never be invoked when the
     * driver doesn't support web-server configuration, while the
     * certificate itself is still reported as issued.
     */
    public function test_cpanel_successful_issuance_never_calls_the_vhost_writer(): void
    {
        $calls = [];
        $writeCalls = [];
        $stage = $this->stage(
            $this->createStub(VpsSslProvisioner::class),
            $this->spyCpanelProvisioner(SslResult::issued('/var/cpanel/ssl/installed/keys/cp.example.com.key', '/var/cpanel/ssl/installed/certs/cp.example.com.crt'), $calls),
            $this->vhostWriterSpy(true, $writeCalls),
        );

        $cpanelDriver = new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL);
        $ctx = $this->context($cpanelDriver, sslEnabled: true, domain: 'cp.example.com');
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame('issued', $result->output['status']);
        $this->assertFalse($result->output['web_server_reconfigured'], 'cPanel never has a vhost to reconfigure');
        $this->assertSame([], $writeCalls, 'NginxVhostWriter::write() must never be called for a driver without supportsWebServerConfiguration');
        $this->assertSame(['cp.example.com'], $calls);
    }

    public function test_vps_successful_issuance_still_calls_the_vhost_writer(): void
    {
        $calls = [];
        $writeCalls = [];
        $stage = $this->stage(
            $this->spyVpsProvisioner(SslResult::issued('/etc/letsencrypt/live/example.com/fullchain.pem', '/etc/letsencrypt/live/example.com/privkey.pem'), $calls),
            $this->createStub(CpanelSslProvisioner::class),
            $this->vhostWriterSpy(true, $writeCalls),
        );

        $vpsDriver = new FakeRemoteDriver(DriverCapabilities::forVps(), DriverType::SSH);
        $ctx = $this->context($vpsDriver, sslEnabled: true, domain: 'example.com');
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue($result->output['web_server_reconfigured']);
        $this->assertCount(1, $writeCalls, 'Part 5 must not change VPS behavior');
    }
}
