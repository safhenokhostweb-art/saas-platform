<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationPackage;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Models\ThemeFleetUpdate;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\CustomerPackageUpgradePipeline;
use App\Services\CustomerInstallation\Dto\CommandResult;
use App\Services\CustomerInstallation\Dto\ConnectionTestResult;
use App\Services\CustomerInstallation\RemoteConnectionService;
use App\Services\ThemeBuilder\ThemeFleetUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class CustomerPackageUpgradePipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDir;
    private string $packageZip;
    private string $packageSha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir() . '/pkg_upgrade_test_' . uniqid();
        @mkdir($this->fixtureDir . '/site_root', 0777, true);
        @mkdir($this->fixtureDir . '/storage/app/install-packages', 0777, true);

        // Create sample site root files
        file_put_contents($this->fixtureDir . '/site_root/artisan', "<?php // artisan");
        file_put_contents($this->fixtureDir . '/site_root/.env', "APP_URL=https://testsite.ir\nDB_DATABASE=testdb\n");
        file_put_contents($this->fixtureDir . '/site_root/installed.lock', "installed");
        @mkdir($this->fixtureDir . '/site_root/storage/logs', 0777, true);
        file_put_contents($this->fixtureDir . '/site_root/storage/logs/laravel.log', "log content");

        // Create dummy package zip
        $this->packageZip = $this->fixtureDir . '/storage/app/install-packages/customer-site-v1.1.2.zip';
        $zip = new \ZipArchive();
        $zip->open($this->packageZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('artisan', "<?php // new artisan");
        $zip->addFromString('app/Services/TestService.php', "<?php // service");
        $zip->close();

        $this->packageSha = hash_file('sha256', $this->packageZip);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->fixtureDir);
        parent::tearDown();
    }

    private function deleteRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($p) ? $this->deleteRecursive($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function makeFakeConnection(string $siteRoot, string $packageSha): RemoteConnectionInterface
    {
        return new class($siteRoot, $packageSha) implements RemoteConnectionInterface {
            public array $executedCommands = [];
            public array $uploadedFiles = [];

            public function __construct(private string $root, private string $packageSha) {}

            public function connect(): void {}
            public function testConnection(): ConnectionTestResult {
                return new ConnectionTestResult(true, 10, 'OK');
            }

            public function execute(string $command, array $env = []): CommandResult {
                $this->executedCommands[] = $command;

                if (str_contains($command, 'test -d') && str_contains($command, 'test -f')) {
                    return new CommandResult(0, 'exists', '', 5);
                }
                if (str_contains($command, 'test -d')) {
                    return new CommandResult(0, 'exists', '', 5);
                }
                if (str_contains($command, 'grep -E')) {
                    return new CommandResult(0, "APP_URL=https://testsite.ir\nDB_DATABASE=testdb\n", '', 5);
                }
                if (str_contains($command, 'sha256sum') || str_contains($command, 'shasum')) {
                    // Match package sha check
                    return new CommandResult(0, $this->packageSha . "  file.zip", '', 5);
                }
                if (str_contains($command, 'php artisan --version')) {
                    return new CommandResult(0, 'Laravel Framework 12.0.0', '', 5);
                }

                return new CommandResult(0, 'OK', '', 5);
            }

            public function upload(string $localPath, string $remotePath, ?callable $progress = null): void {
                $this->uploadedFiles[$remotePath] = $localPath;
            }

            public function download(string $remotePath, string $localPath): void {}
            public function fileExists(string $remotePath): bool { return true; }
            public function diskFree(string $path): int { return 1000000000; }
            public function disconnect(): void {}
        };
    }

    public function test_upgrade_fails_if_no_active_package(): void
    {
        $customer = Customer::create(['name' => 'Test Cust', 'mobile' => '09120000001']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Test Site', 'domain' => 'testsite.ir']);

        $pipeline = app(CustomerPackageUpgradePipeline::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('هیچ پکیج نصب فعالی در سیستم یافت نشد.');

        $pipeline->upgradeSite($site);
    }

    public function test_upgrade_fails_if_package_checksum_mismatch(): void
    {
        $customer = Customer::create(['name' => 'Test Cust', 'mobile' => '09120000001']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Test Site', 'domain' => 'testsite.ir']);

        $pkg = CustomerInstallationPackage::create([
            'version'     => '1.1.2',
            'arvan_key'   => '',
            'local_path'  => $this->packageZip,
            'sha256'      => 'wrong-sha256-hash',
            'size_bytes'  => filesize($this->packageZip),
            'released_at' => now(),
            'is_active'   => true,
        ]);

        $pipeline = app(CustomerPackageUpgradePipeline::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('عدم انطباق چک‌سام پکیج محلی');

        $pipeline->upgradeSite($site, $pkg);
    }

    public function test_upgrade_pipeline_executes_lock_backup_extract_migrate_and_audit(): void
    {
        $customer = Customer::create(['name' => 'Test Cust', 'mobile' => '09120000001']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Test Site',
            'domain'      => 'testsite.ir',
            'db_name'     => 'testdb',
        ]);

        $server = CustomerRemoteServer::create([
            'label'               => 'VPS',
            'type'                => 'ssh',
            'is_platform_managed' => true,
            'host'                => '127.0.0.1',
            'port'                => 22,
            'username'            => 'root',
        ]);

        CustomerInstallation::create([
            'site_id'          => $site->id,
            'remote_server_id' => $server->id,
            'installer_user_id'=> 1,
            'status'           => 'complete',
            'customer_company' => 'Test Cust',
            'customer_email'   => 'test@example.com',
            'customer_domain'  => 'testsite.ir',
            'package_version'  => '1.1.0',
            'request_payload'  => ['web_root_path' => $this->fixtureDir . '/site_root'],
        ]);

        $pkg = CustomerInstallationPackage::create([
            'version'     => '1.1.2',
            'arvan_key'   => '',
            'local_path'  => $this->packageZip,
            'sha256'      => $this->packageSha,
            'size_bytes'  => filesize($this->packageZip),
            'released_at' => now(),
            'is_active'   => true,
        ]);

        $fakeConn = $this->makeFakeConnection($this->fixtureDir . '/site_root', $this->packageSha);

        $mockConnService = $this->createMock(RemoteConnectionService::class);
        $mockConnService->method('buildDriver')->willReturn($fakeConn);
        $this->app->instance(RemoteConnectionService::class, $mockConnService);

        $pipeline = app(CustomerPackageUpgradePipeline::class);
        $fleetUpdate = $pipeline->upgradeSite($site, $pkg, null);

        if ($fleetUpdate->status === ThemeFleetUpdate::STATUS_FAILED) {
            $this->fail("Upgrade unexpectedly failed: " . $fleetUpdate->failure_reason);
        }

        $this->assertSame(ThemeFleetUpdate::STATUS_COMPLETED, $fleetUpdate->status);
        $this->assertSame('1.1.0', $fleetUpdate->component_versions['customer_core']['from']);
        $this->assertSame('1.1.2', $fleetUpdate->component_versions['customer_core']['to']);

        // Verify execution sequence in fake commands
        $cmds = $fakeConn->executedCommands;

        // 1. Maintenance down
        $hasDown = false;
        // 2. Backup
        $hasBackup = false;
        // 3. Unzip with exclusions of .env, installed.lock, storage/
        $hasSafeExtract = false;
        // 4. Migration
        $hasMigrate = false;
        // 5. Maintenance up
        $hasUp = false;

        foreach ($cmds as $cmd) {
            if (str_contains($cmd, 'php artisan down')) $hasDown = true;
            if (str_contains($cmd, 'cp -a') && str_contains($cmd, '.backup_')) $hasBackup = true;
            if (str_contains($cmd, 'unzip -o -q') && str_contains($cmd, "-x '.env*' 'installed.lock' '*/installed.lock' 'storage/*'")) $hasSafeExtract = true;
            if (str_contains($cmd, 'php artisan migrate --force')) $hasMigrate = true;
            if (str_contains($cmd, 'php artisan up')) $hasUp = true;
        }

        $this->assertTrue($hasDown, "artisan down must be executed to lock tenant");
        $this->assertTrue($hasBackup, "cp -a backup must be created before changes");
        $this->assertTrue($hasSafeExtract, "unzip must exclude .env, installed.lock, and storage/*");
        $this->assertTrue($hasMigrate, "artisan migrate --force must be executed");
        $this->assertTrue($hasUp, "artisan up must be executed on completion");

        // Verify database customer_installations package_version updated
        $inst = CustomerInstallation::where('site_id', $site->id)->first();
        $this->assertSame('1.1.2', $inst->package_version);
    }

    public function test_upgrade_concurrency_lock_prevents_simultaneous_runs(): void
    {
        $customer = Customer::create(['name' => 'Lock Cust', 'mobile' => '09120000002']);
        $site = Site::create(['customer_id' => $customer->id, 'title' => 'Lock Site', 'domain' => 'locksite.ir']);

        $pkg = CustomerInstallationPackage::create([
            'version'     => '1.1.2',
            'arvan_key'   => '',
            'local_path'  => $this->packageZip,
            'sha256'      => $this->packageSha,
            'size_bytes'  => filesize($this->packageZip),
            'released_at' => now(),
            'is_active'   => true,
        ]);

        // Acquire lock beforehand
        $lockKey = CustomerPackageUpgradePipeline::LOCK_PREFIX . $site->id;
        $activeLock = Cache::lock($lockKey, 60);
        $this->assertTrue($activeLock->get());

        $pipeline = app(CustomerPackageUpgradePipeline::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('در حال حاضر روی سایت');

        try {
            $pipeline->upgradeSite($site, $pkg);
        } finally {
            $activeLock->release();
        }
    }

    public function test_upgrade_failure_triggers_automatic_rollback(): void
    {
        $customer = Customer::create(['name' => 'Fail Cust', 'mobile' => '09120000003']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Fail Site',
            'domain'      => 'failsite.ir',
            'db_name'     => 'faildb',
        ]);

        $server = CustomerRemoteServer::create([
            'label'               => 'VPS',
            'type'                => 'ssh',
            'is_platform_managed' => true,
            'host'                => '127.0.0.1',
            'port'                => 22,
            'username'            => 'root',
        ]);

        CustomerInstallation::create([
            'site_id'          => $site->id,
            'remote_server_id' => $server->id,
            'installer_user_id'=> 1,
            'status'           => 'complete',
            'customer_company' => 'Fail Cust',
            'customer_email'   => 'fail@example.com',
            'customer_domain'  => 'failsite.ir',
            'package_version'  => '1.1.0',
            'request_payload'  => ['web_root_path' => $this->fixtureDir . '/site_root'],
        ]);

        $pkg = CustomerInstallationPackage::create([
            'version'     => '1.1.2',
            'arvan_key'   => '',
            'local_path'  => $this->packageZip,
            'sha256'      => $this->packageSha,
            'size_bytes'  => filesize($this->packageZip),
            'released_at' => now(),
            'is_active'   => true,
        ]);

        // Connection that fails during migrate step
        $failingConn = new class($this->fixtureDir . '/site_root', $this->packageSha) implements RemoteConnectionInterface {
            public array $executedCommands = [];
            public function __construct(private string $root, private string $expectedSha) {}

            public function connect(): void {}
            public function testConnection(): ConnectionTestResult { return new ConnectionTestResult(true, 10, 'OK'); }
            public function execute(string $command, array $env = []): CommandResult {
                $this->executedCommands[] = $command;

                if (str_contains($command, 'test -d') || str_contains($command, 'test -f')) {
                    return new CommandResult(0, 'exists', '', 5);
                }
                if (str_contains($command, 'grep -E')) {
                    return new CommandResult(0, "APP_URL=https://failsite.ir\nDB_DATABASE=faildb\n", '', 5);
                }
                if (str_contains($command, 'sha256sum') || str_contains($command, 'shasum')) {
                    return new CommandResult(0, $this->expectedSha . "  file.zip", '', 5);
                }
                if (str_contains($command, 'php artisan migrate --force')) {
                    // Simulate migration failure!
                    return new CommandResult(1, '', 'SQLSTATE[HY000]: Migration table locked', 5);
                }
                return new CommandResult(0, 'OK', '', 5);
            }
            public function upload(string $l, string $r, ?callable $p = null): void {}
            public function download(string $r, string $l): void {}
            public function fileExists(string $r): bool { return true; }
            public function diskFree(string $p): int { return 1000000000; }
            public function disconnect(): void {}
        };

        $mockConnService = $this->createMock(RemoteConnectionService::class);
        $mockConnService->method('buildDriver')->willReturn($failingConn);
        $this->app->instance(RemoteConnectionService::class, $mockConnService);

        $pipeline = app(CustomerPackageUpgradePipeline::class);
        $fleetUpdate = $pipeline->upgradeSite($site, $pkg, null);

        $this->assertSame(ThemeFleetUpdate::STATUS_FAILED, $fleetUpdate->status);
        $this->assertStringContainsString('خطا در اجرای مایگریشن‌های سایت', $fleetUpdate->failure_reason);

        // Verify auto-rollback restored backup and unlocked site
        $cmds = $failingConn->executedCommands;
        $hasRestore = false;
        $hasUnlock = false;

        foreach ($cmds as $cmd) {
            if (str_contains($cmd, 'cp -a') && str_contains($cmd, '.backup_') && str_contains($cmd, 'site_root')) {
                $hasRestore = true;
            }
            if (str_contains($cmd, 'php artisan up')) {
                $hasUnlock = true;
            }
        }

        $this->assertTrue($hasRestore, "Auto-rollback must restore from backup directory on failure");
        $this->assertTrue($hasUnlock, "Auto-rollback must unlock the site (artisan up) on failure");
    }

    public function test_rollback_package_update_restores_from_recorded_backup(): void
    {
        $customer = Customer::create(['name' => 'Rollback Cust', 'mobile' => '09120000004']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Rollback Site',
            'domain'      => 'rbsite.ir',
            'db_name'     => 'rbdb',
        ]);

        $server = CustomerRemoteServer::create([
            'label'               => 'VPS',
            'type'                => 'ssh',
            'is_platform_managed' => true,
            'host'                => '127.0.0.1',
            'port'                => 22,
            'username'            => 'root',
        ]);

        CustomerInstallation::create([
            'site_id'          => $site->id,
            'remote_server_id' => $server->id,
            'installer_user_id'=> 1,
            'status'           => 'complete',
            'customer_company' => 'Rollback Cust',
            'customer_email'   => 'rb@example.com',
            'customer_domain'  => 'rbsite.ir',
            'package_version'  => '1.1.2',
            'request_payload'  => ['web_root_path' => $this->fixtureDir . '/site_root'],
        ]);

        $fakeConn = $this->makeFakeConnection($this->fixtureDir . '/site_root', $this->packageSha);
        $mockConnService = $this->createMock(RemoteConnectionService::class);
        $mockConnService->method('buildDriver')->willReturn($fakeConn);
        $this->app->instance(RemoteConnectionService::class, $mockConnService);

        $backupDir = $this->fixtureDir . '/.backup_site_root_20261123';
        @mkdir($backupDir, 0777, true);

        $targetUpdate = ThemeFleetUpdate::create([
            'site_id'                => $site->id,
            'update_type'            => ThemeFleetUpdate::TYPE_PACKAGE,
            'status'                 => ThemeFleetUpdate::STATUS_COMPLETED,
            'component_versions'     => ['customer_core' => ['from' => '1.1.0', 'to' => '1.1.2']],
            'reconciliation_summary' => ['backup_dir' => $backupDir],
            'started_at'             => now()->subHour(),
            'completed_at'           => now()->subHour(),
        ]);

        $pipeline = app(CustomerPackageUpgradePipeline::class);
        $rollbackUpdate = $pipeline->rollbackPackageUpdate($targetUpdate, null);

        $this->assertSame(ThemeFleetUpdate::STATUS_COMPLETED, $rollbackUpdate->status);
        $this->assertTrue($rollbackUpdate->is_rollback);
        $this->assertSame($targetUpdate->id, $rollbackUpdate->rollback_from_update_id);
        $this->assertSame('1.1.0', CustomerInstallation::where('site_id', $site->id)->first()->package_version);
    }

    public function test_command_result_aliases_and_methods_work_as_expected(): void
    {
        $success = new CommandResult(0, "Output line 1\n", "", 12);
        $this->assertTrue($success->succeeded());
        $this->assertTrue($success->successful());
        $this->assertTrue($success->isSuccessful());
        $this->assertFalse($success->failed());
        $this->assertSame("Output line 1", $success->output());
        $this->assertSame(0, $success->toArray()['exit_code']);

        $failure = new CommandResult(127, "", "command not found\n", 8);
        $this->assertFalse($failure->succeeded());
        $this->assertFalse($failure->successful());
        $this->assertFalse($failure->isSuccessful());
        $this->assertTrue($failure->failed());
        $this->assertSame("command not found", $failure->output());
    }

    public function test_upgrade_site_without_customer_installation_record_resolves_webroot_via_server_discovery(): void
    {
        // Site like safhenokhost.ir that has no row in customer_installations
        $customer = Customer::create(['name' => 'Primary Cust', 'mobile' => '09120000005']);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title'       => 'Safhe Nokhost Main Site',
            'domain'      => 'safhenokhost.ir',
            'db_name'     => 'safhenokhost_db',
        ]);

        $server = CustomerRemoteServer::create([
            'label'               => 'VPS Platform Managed',
            'type'                => 'ssh',
            'is_platform_managed' => true,
            'host'                => '127.0.0.1',
            'port'                => 22,
            'username'            => 'root',
        ]);

        $pkg = CustomerInstallationPackage::create([
            'version'     => '1.1.2',
            'arvan_key'   => '',
            'local_path'  => $this->packageZip,
            'sha256'      => $this->packageSha,
            'size_bytes'  => filesize($this->packageZip),
            'released_at' => now(),
            'is_active'   => true,
        ]);

        // Fake connection with real CommandResult instances
        $fakeConn = $this->makeFakeConnection($this->fixtureDir . '/site_root', $this->packageSha);

        $mockConnService = $this->createMock(RemoteConnectionService::class);
        $mockConnService->method('buildDriver')->willReturn($fakeConn);
        $this->app->instance(RemoteConnectionService::class, $mockConnService);

        $pipeline = app(CustomerPackageUpgradePipeline::class);
        $fleetUpdate = $pipeline->upgradeSite($site, $pkg, null);

        $this->assertSame(ThemeFleetUpdate::STATUS_COMPLETED, $fleetUpdate->status);
        $this->assertSame('1.1.2', $fleetUpdate->component_versions['customer_core']['to']);

        // Verify discovery commands were executed using CommandResult methods
        $executed = implode("\n", $fakeConn->executedCommands);
        $this->assertStringContainsString('ls -d /var/www/cust_site_*', $executed);
    }
}
