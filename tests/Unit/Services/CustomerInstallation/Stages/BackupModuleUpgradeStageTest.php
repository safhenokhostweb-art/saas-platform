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

    // ─── Phase 13-M2 — Database Restore Rollback ───────────────────────────

    public function test_can_rollback_is_true(): void
    {
        $this->assertTrue(app(BackupModuleUpgradeStage::class)->canRollback());
    }

    public function test_execute_populates_rollback_snapshot(): void
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
            $mock->shouldReceive('storeCustomerBaseline')->andReturn([
                'success' => true,
                'local_path' => '/fake/repo/Customers/Site-999/uuid/20260101_000000',
            ]);
        });
        $this->mock(ArvanBackupService::class);

        $ctx = $this->makeContext($driver);
        $result = app(BackupModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame($result->output['backup_sha256'], $result->rollbackSnapshot['backup_sha256']);
        $this->assertNull($result->rollbackSnapshot['backup_arvan_key']);
        $this->assertSame(
            '/fake/repo/Customers/Site-999/uuid/20260101_000000' . DIRECTORY_SEPARATOR . 'customer_baseline.sql.gz',
            $result->rollbackSnapshot['local_repo_file']
        );
    }

    public function test_rollback_throws_when_snapshot_has_no_backup_reference(): void
    {
        $ctx = $this->makeContext(new FakeRemoteDriver());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no usable backup reference/');

        app(BackupModuleUpgradeStage::class)->rollback($ctx, []);
    }

    public function test_rollback_restores_from_local_repo_file(): void
    {
        $fixture = storage_path('app/install-packages/rollback_fixture_local.sql.gz');
        file_put_contents($fixture, 'fake-dump-content');
        $sha256 = hash_file('sha256', $fixture);

        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('gunzip -c', 0, "restore-ok\n");
        $driver->addResponse('mysql -N -u', 0, "5\n");

        $ctx = $this->makeContext($driver);

        try {
            app(BackupModuleUpgradeStage::class)->rollback($ctx, [
                'backup_sha256' => $sha256,
                'backup_arvan_key' => null,
                'local_repo_file' => $fixture,
            ]);
        } finally {
            @unlink($fixture);
        }

        $uploads = $driver->getUploadLog();
        $this->assertNotEmpty($uploads);
        $this->assertStringContainsString('module_upgrade_restore_', $uploads[0]['local']);
    }

    public function test_rollback_restores_via_arvan_when_local_file_unavailable(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('download')->once()->andReturnUsing(function ($key, $dest) {
                file_put_contents($dest, 'arvan-dump-content');
                return true;
            });
        });

        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('gunzip -c', 0, "restore-ok\n");
        $driver->addResponse('mysql -N -u', 0, "3\n");

        $ctx = $this->makeContext($driver);

        app(BackupModuleUpgradeStage::class)->rollback($ctx, [
            'backup_sha256' => hash('sha256', 'arvan-dump-content'),
            'backup_arvan_key' => 'customers/site-999/module-upgrades/backup_x.sql.gz',
            'local_repo_file' => null,
        ]);

        $this->assertNotEmpty($driver->getUploadLog());
    }

    public function test_rollback_throws_on_checksum_mismatch(): void
    {
        $fixture = storage_path('app/install-packages/rollback_fixture_mismatch.sql.gz');
        file_put_contents($fixture, 'fake-dump-content');

        $ctx = $this->makeContext(new FakeRemoteDriver());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/checksum mismatch/');

        try {
            app(BackupModuleUpgradeStage::class)->rollback($ctx, [
                'backup_sha256' => str_repeat('0', 64),
                'backup_arvan_key' => null,
                'local_repo_file' => $fixture,
            ]);
        } finally {
            @unlink($fixture);
        }
    }

    public function test_rollback_throws_when_mysql_restore_fails(): void
    {
        $fixture = storage_path('app/install-packages/rollback_fixture_restore_fail.sql.gz');
        file_put_contents($fixture, 'x');
        $sha256 = hash_file('sha256', $fixture);

        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('gunzip -c', 0, "restore-failed\n");

        $ctx = $this->makeContext($driver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/mysql restore failed/');

        try {
            app(BackupModuleUpgradeStage::class)->rollback($ctx, [
                'backup_sha256' => $sha256,
                'backup_arvan_key' => null,
                'local_repo_file' => $fixture,
            ]);
        } finally {
            @unlink($fixture);
        }
    }

    public function test_rollback_throws_when_post_restore_verification_finds_no_tables(): void
    {
        $fixture = storage_path('app/install-packages/rollback_fixture_no_tables.sql.gz');
        file_put_contents($fixture, 'x');
        $sha256 = hash_file('sha256', $fixture);

        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('gunzip -c', 0, "restore-ok\n");
        $driver->addResponse('mysql -N -u', 0, "0\n");

        $ctx = $this->makeContext($driver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no tables/');

        try {
            app(BackupModuleUpgradeStage::class)->rollback($ctx, [
                'backup_sha256' => $sha256,
                'backup_arvan_key' => null,
                'local_repo_file' => $fixture,
            ]);
        } finally {
            @unlink($fixture);
        }
    }

    public function test_rollback_throws_when_db_credentials_unreadable(): void
    {
        $fixture = storage_path('app/install-packages/rollback_fixture_no_creds.sql.gz');
        file_put_contents($fixture, 'x');
        $sha256 = hash_file('sha256', $fixture);

        // No grep responses registered — FakeRemoteDriver defaults to empty stdout.
        $ctx = $this->makeContext(new FakeRemoteDriver());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB credentials/');

        try {
            app(BackupModuleUpgradeStage::class)->rollback($ctx, [
                'backup_sha256' => $sha256,
                'backup_arvan_key' => null,
                'local_repo_file' => $fixture,
            ]);
        } finally {
            @unlink($fixture);
        }
    }
}
