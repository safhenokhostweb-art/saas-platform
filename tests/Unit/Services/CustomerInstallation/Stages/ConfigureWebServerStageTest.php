<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\ConfigureWebServerStage;
use App\Services\CustomerInstallation\StageCapabilityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F5 — ConfigureWebServerStage's own code is untouched this phase
 * (Part 3: no WebProvisioner, no code change). These tests confirm what the
 * audit found: the existing capability gate (Phase 14-E) already fully
 * protects cPanel from ever reaching the nginx-writing code, while VPS's
 * nginx flow is completely unaffected.
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

    private function context(FakeRemoteDriver $driver): InstallationContext
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
            'customer_domain'   => 'webserver.example.com',
            'request_payload'   => [
                'company' => 'WebServer Stage Co', 'owner_name' => 'Owner', 'email' => 'webserver@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'webserver.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
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
        $stage = new ConfigureWebServerStage();
        $cpanelServer = new CustomerRemoteServer([
            'type' => 'cpanel', 'host' => 'box.example.test',
            'username' => 'cust1', 'auth_method' => 'password',
        ]);
        $cpanelDriver = new CpanelDeploymentDriver($cpanelServer, new CredentialVault());
        $gate = new StageCapabilityGate();

        $this->assertFalse($gate->isSatisfied($stage, $cpanelDriver), 'cPanel must not satisfy supportsWebServerConfiguration');
        $this->assertFalse($stage->isCapabilityRequiredToProceed(), 'Missing this capability must skip, not fail, the installation');
    }

    public function test_vps_nginx_flow_is_unchanged(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('nginx -t', 0, 'syntax is ok, test is successful');
        $ctx = $this->context($driver);

        $result = (new ConfigureWebServerStage())->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');

        // Index-independent: detectPhpFpmSocket() issues its own execute()
        // calls first (php version probe, socket path checks), so assert
        // presence + relative order rather than fixed indices.
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
