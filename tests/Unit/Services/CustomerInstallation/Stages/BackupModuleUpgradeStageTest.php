<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\ModuleUpdateTarget;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\BackupModuleUpgradeStage;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13-I — BackupModuleUpgradeStage scenarios.
 *
 * ArvanBackupService/LocalRecoveryRepository are mocked (not exercised
 * against real cloud storage) — matching the mocking pattern already used
 * in tests/Unit/Services/ModuleUpdateEligibilityServiceTest.php (12-K).
 */
class BackupModuleUpgradeStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        // The stage downloads into this dir without creating it (matching
        // InitializeBackupStage's existing behavior) — production relies on
        // it already existing; tests must create it explicitly.
        $dir = storage_path('app/install-packages');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
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
            'site_id' => 999,
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

    public function test_fails_without_db_credentials(): void
    {
        $ctx = $this->makeContext(new FakeRemoteDriver());

        $result = app(BackupModuleUpgradeStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not read DB credentials', $result->error);
    }

    public function test_fails_when_mysqldump_fails(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('mysqldump', 0, "dump-failed\n");

        $ctx = $this->makeContext($driver);
        $result = app(BackupModuleUpgradeStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('mysqldump failed', $result->error);
    }

    public function test_fails_when_dump_is_too_small(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('mysqldump', 0, "dump-ok\n");
        $driver->addResponse('stat -c%s', 0, "10\n");

        $ctx = $this->makeContext($driver);
        $result = app(BackupModuleUpgradeStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('suspiciously small', $result->error);
    }

    public function test_succeeds_and_stores_via_local_repo(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-01-01 00:00:00'));

        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('mysqldump', 0, "dump-ok\n");
        $driver->addResponse('stat -c%s', 0, "5000\n");
        $driver->putFile('/tmp/cie_module_upgrade_999_20260101_000000.sql.gz');

        $this->mock(LocalRecoveryRepository::class, function ($mock) {
            $mock->shouldReceive('shouldUseLocal')->andReturn(true);
            $mock->shouldReceive('shouldUseArvan')->andReturn(false);
            $mock->shouldReceive('storeCustomerBaseline')->andReturn(['success' => true]);
        });
        $this->mock(ArvanBackupService::class); // never called — shouldUseArvan() is false

        $ctx = $this->makeContext($driver);
        $result = app(BackupModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue($result->output['stored_locally']);
        $this->assertNull($result->output['backup_arvan_key']);
    }

    public function test_fails_when_both_storage_backends_unavailable(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('mysqldump', 0, "dump-ok\n");
        $driver->addResponse('stat -c%s', 0, "5000\n");
        $driver->putFile('/tmp/cie_module_upgrade_999_' . now()->format('Ymd_His') . '.sql.gz');

        $this->mock(LocalRecoveryRepository::class, function ($mock) {
            $mock->shouldReceive('shouldUseLocal')->andReturn(false);
            $mock->shouldReceive('shouldUseArvan')->andReturn(false);
        });
        $this->mock(ArvanBackupService::class);

        $ctx = $this->makeContext($driver);
        $result = app(BackupModuleUpgradeStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('could not be stored', $result->error);
    }
}
