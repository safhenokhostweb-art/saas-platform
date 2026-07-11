<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\ModuleUpdateTarget;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\LockTenantStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13-J — LockTenantStage scenarios.
 */
class LockTenantStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeContext(FakeRemoteDriver $driver): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Test Server', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id,
            'installer_user_id' => 1,
            'install_mode' => 'MODE_MODULE_UPDATE',
            'status' => 'running',
            'customer_company' => 'Test Co',
            'customer_email' => 'test@example.com',
            'customer_domain' => 'test.example.com',
            'request_payload' => [
                'company' => 'Test Co', 'owner_name' => 'Owner', 'email' => 'test@example.com',
                'phone' => '09123456789', 'domain' => 'test.example.com',
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_MODULE_UPDATE',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $target = new ModuleUpdateTarget('forms', 1, '1.1.0');

        return new InstallationContext($installation, $request, $driver, $target);
    }

    public function test_succeeds_when_artisan_down_succeeds(): void
    {
        $driver = new FakeRemoteDriver();
        // Default FakeRemoteDriver response is exit 0 — matches a clean `artisan down`.
        $ctx = $this->makeContext($driver);

        $result = app(LockTenantStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue($result->output['locked']);

        $commands = $driver->getCommandLog();
        $this->assertNotEmpty(array_filter($commands, fn ($c) => str_contains($c, 'artisan down')));
    }

    public function test_fails_when_artisan_down_fails(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('artisan down', 1, "Some error\n");

        $ctx = $this->makeContext($driver);
        $result = app(LockTenantStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Failed to enable maintenance mode', $result->error);
    }

    public function test_rollback_runs_artisan_up(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->makeContext($driver);

        app(LockTenantStage::class)->rollback($ctx, []);

        $commands = $driver->getCommandLog();
        $this->assertNotEmpty(array_filter($commands, fn ($c) => str_contains($c, 'artisan up')));
    }

    /**
     * Phase 13-M3b — LockTenantStage's own rollback (the unlock) is not
     * itself a critical recovery step; it must run with the default
     * (non-critical) AbstractStage behavior so a failed unlock doesn't
     * halt replay of anything that might come after it.
     */
    public function test_is_not_critical_for_safety(): void
    {
        $this->assertFalse(app(LockTenantStage::class)->isCriticalForSafety());
    }
}
