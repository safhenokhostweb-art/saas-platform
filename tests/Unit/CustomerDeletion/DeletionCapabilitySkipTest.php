<?php

namespace Tests\Unit\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Stages\RemoveWebServerStage;
use App\Services\CustomerDeletion\Stages\StopServicesStage;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Enums\DriverType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-G3 — deletion stages that manage VPS-only resources are gated on
 * the driver's declared capabilities, mirroring the install-side
 * StageCapabilityGate: a missing capability is a safe skip (no command runs,
 * verify() reports an explicit skip), never a bogus attempt against paths
 * that cannot exist on that hosting type.
 */
class DeletionCapabilitySkipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    private function context(FakeRemoteDriver $driver, string $serverType = 'fake'): DeleteContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => $serverType, 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => (new CredentialVault())->encrypt('the-password'),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Cap Skip Co',
            'customer_email'    => 'capskip@example.com',
            'customer_domain'   => 'capskip.example.com',
            'request_payload'   => [
                'company' => 'Cap Skip Co', 'owner_name' => 'Owner', 'email' => 'capskip@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'capskip.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $deletion = CustomerDeletion::create([
            'installation_id' => $installation->id,
            'initiated_by'    => 1,
            'policy'          => 'never_delete_backup',
            'status'          => 'running',
        ]);

        return DeleteContext::fromDeletion($deletion, $driver);
    }

    public function test_stop_services_skips_on_a_driver_without_supervisor(): void
    {
        $driver = new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL);
        $ctx = $this->context($driver, 'cpanel');

        $result = (new StopServicesStage())->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue($result->skipped);
        $this->assertSame([], $driver->getCommandLog(), 'no supervisorctl command may run');

        $checks = (new StopServicesStage())->verify($ctx);
        $this->assertTrue($checks[0]['passed']);
        $this->assertStringContainsString('skipped', $checks[0]['detail']);
    }

    public function test_stop_services_still_runs_on_vps(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver);

        $result = (new StopServicesStage())->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
        $this->assertStringContainsString('supervisorctl stop', implode("\n", $driver->getCommandLog()));
    }

    public function test_remove_web_server_skips_on_a_driver_without_web_server_configuration(): void
    {
        $driver = new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL);
        $ctx = $this->context($driver, 'cpanel');

        $result = (new RemoveWebServerStage())->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue($result->skipped);
        $this->assertSame([], $driver->getCommandLog(), 'no nginx command may run');

        $checks = (new RemoveWebServerStage())->verify($ctx);
        $this->assertTrue($checks[0]['passed']);
        $this->assertStringContainsString('skipped', $checks[0]['detail']);
    }

    public function test_remove_web_server_still_runs_on_vps(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver);

        $result = (new RemoveWebServerStage())->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertFalse($result->skipped);
        $log = implode("\n", $driver->getCommandLog());
        $this->assertStringContainsString('/etc/nginx/sites-enabled/' . $ctx->slug, $log);
        $this->assertStringContainsString('/etc/nginx/sites-available/' . $ctx->slug, $log);
        $this->assertStringContainsString('nginx -s reload', $log);
    }
}
