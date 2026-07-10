<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\ModuleUpdateTarget;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\ExtractModulePackageStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13-I — ExtractModulePackageStage scenarios.
 */
class ExtractModulePackageStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeContext(FakeRemoteDriver $driver, ?ModuleUpdateTarget $target): InstallationContext
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

        return new InstallationContext($installation, $request, $driver, $target);
    }

    public function test_fails_without_module_target(): void
    {
        $ctx = $this->makeContext(new FakeRemoteDriver(), null);
        $result = app(ExtractModulePackageStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No moduleTarget', $result->error);
    }

    public function test_fails_without_prior_upload_output(): void
    {
        $target = new ModuleUpdateTarget('forms', 1, '1.1.0');
        $ctx = $this->makeContext(new FakeRemoteDriver(), $target);

        $result = app(ExtractModulePackageStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('UPLOAD_PACKAGE', $result->error);
    }

    public function test_extracts_successfully_when_no_existing_module_directory(): void
    {
        $target = new ModuleUpdateTarget('forms', 1, '1.1.0');
        $driver = new FakeRemoteDriver();
        // "test -d ... && echo exists || echo absent" defaults to empty stdout → not "exists" → absent
        $driver->addResponse('find', 0, "5\n"); // file count after extraction

        $ctx = $this->makeContext($driver, $target);
        $ctx->recordOutput('UPLOAD_PACKAGE', ['remote_path' => '/tmp/forms-1.1.0.zip']);

        $result = app(ExtractModulePackageStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame(5, $result->output['file_count']);
        $this->assertNull($result->output['previous_backed_up_to']);
        $this->assertNull($result->rollbackSnapshot['backup_path']);
        $this->assertStringContainsString('/packages/saas-platform/modules/forms', $result->output['module_path']);
    }

    public function test_extracts_successfully_and_backs_up_existing_directory(): void
    {
        $target = new ModuleUpdateTarget('forms', 1, '1.1.0');
        $driver = new FakeRemoteDriver();
        $driver->addResponse('test -d', 0, "exists\n");
        $driver->addResponse('find', 0, "8\n");

        $ctx = $this->makeContext($driver, $target);
        $ctx->recordOutput('UPLOAD_PACKAGE', ['remote_path' => '/tmp/forms-1.1.0.zip']);

        $result = app(ExtractModulePackageStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->output['previous_backed_up_to']);
        $this->assertNotNull($result->rollbackSnapshot['backup_path']);

        // Confirm the existing directory was moved aside before extraction.
        $moveCommands = array_filter($driver->getCommandLog(), fn ($c) => str_starts_with($c, 'mv '));
        $this->assertNotEmpty($moveCommands);
    }

    public function test_rollback_restores_from_backup_path_when_present(): void
    {
        $target = new ModuleUpdateTarget('forms', 1, '1.1.0');
        $driver = new FakeRemoteDriver();
        $ctx = $this->makeContext($driver, $target);

        app(ExtractModulePackageStage::class)->rollback($ctx, [
            'module_path' => '/var/www/site/packages/saas-platform/modules/forms',
            'backup_path' => '/var/www/site/packages/saas-platform/modules/forms.bak_20260101',
        ]);

        $commands = implode(' | ', $driver->getCommandLog());
        $this->assertStringContainsString('rm -rf', $commands);
        $this->assertStringContainsString('mv', $commands);
        $this->assertStringContainsString('forms.bak_20260101', $commands);
    }

    public function test_rollback_removes_directory_when_no_backup_path(): void
    {
        $target = new ModuleUpdateTarget('forms', 1, '1.1.0');
        $driver = new FakeRemoteDriver();
        $ctx = $this->makeContext($driver, $target);

        app(ExtractModulePackageStage::class)->rollback($ctx, [
            'module_path' => '/var/www/site/packages/saas-platform/modules/forms',
            'backup_path' => null,
        ]);

        $commands = $driver->getCommandLog();
        $this->assertCount(1, $commands);
        $this->assertStringContainsString('rm -rf', $commands[0]);
    }
}
