<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\ModuleUpdateTarget;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\CompleteModuleUpgradeStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13-I — CompleteModuleUpgradeStage scenarios. Reuses
 * PostInstallValidationService unmodified — these tests exercise it
 * through the same remote-command shape ValidateDiskStage/ExtractPackageStage
 * tests already use (FakeRemoteDriver::addResponse).
 */
class CompleteModuleUpgradeStageTest extends TestCase
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

    public function test_marks_complete_when_required_checks_pass(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('public/storage', 0, "linked\n");
        $driver->addResponse('.cie_health', 0, "writable\n");

        $ctx = $this->makeContext($driver);
        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $ctx->installation->refresh();
        $this->assertSame('complete', $ctx->installation->status);
        $this->assertNotNull($ctx->installation->post_install_validation);
    }

    public function test_fails_and_does_not_mark_complete_when_storage_check_fails(): void
    {
        // No storage-symlink response registered — defaults to "missing" (required, FAIL).
        $driver = new FakeRemoteDriver();

        $ctx = $this->makeContext($driver);
        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $ctx->installation->refresh();
        $this->assertNotSame('complete', $ctx->installation->status);
    }
}
