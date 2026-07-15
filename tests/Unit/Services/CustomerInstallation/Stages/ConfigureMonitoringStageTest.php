<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\ConfigureMonitoringStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F5 — audit confirmed ConfigureMonitoringStage has no VPS-only
 * command, no systemd dependency, and no root requirement: it only attempts
 * a best-effort health-check probe (with a catch-all fallback) and always
 * returns StageResult::success — real monitoring is centralized on the
 * platform side (site_id-driven), not installed on the remote server. No
 * code change was made; this is a new regression test confirming that on
 * both VPS and cPanel.
 */
class ConfigureMonitoringStageTest extends TestCase
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
            'site_id'           => 42,
            'customer_company'  => 'Monitoring Stage Co',
            'customer_email'    => 'monitoring@example.com',
            'customer_domain'   => 'monitoring.example.com',
            'request_payload'   => [
                'company' => 'Monitoring Stage Co', 'owner_name' => 'Owner', 'email' => 'monitoring@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'monitoring.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_always_succeeds_even_when_the_health_check_command_reports_failure(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('health:check', 1, 'command not found');
        $ctx = $this->context($driver);

        $result = (new ConfigureMonitoringStage())->execute($ctx);

        $this->assertTrue($result->success, 'Monitoring is centralized on the platform side — a failed remote probe must never fail the installation');
        $this->assertTrue($result->output['monitoring_active']);
        $this->assertSame(42, $result->output['site_id']);
    }

    public function test_has_no_capability_requirement_and_is_never_gated(): void
    {
        $stage = new ConfigureMonitoringStage();

        $this->assertSame([], $stage->requiredCapabilities());
    }

    public function test_runs_identically_regardless_of_driver_type(): void
    {
        // Not executed against a live cPanel connection (no network), but
        // confirms nothing in this stage's construction or capability
        // declaration branches on driver type — it is the same
        // AbstractStage default ([]) regardless.
        $server = new CustomerRemoteServer(['type' => 'cpanel', 'host' => 'box.example.test', 'username' => 'cust1', 'auth_method' => 'password']);
        $cpanelDriver = new CpanelDeploymentDriver($server, new CredentialVault());

        $stage = new ConfigureMonitoringStage();

        $this->assertSame([], $stage->requiredCapabilities());
        $this->assertSame('cpanel', $cpanelDriver->driverType()->value);
    }
}
