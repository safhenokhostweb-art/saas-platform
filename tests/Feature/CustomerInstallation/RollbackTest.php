<?php

namespace Tests\Feature\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallRollback;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CustomerInstallationRollbackService;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\InstallationStateManager;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeInstallation(): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'failed',
            'failed_at'         => now(),
            'customer_company'  => 'Rollback Co',
            'customer_email'    => 'rb@example.com',
            'customer_domain'   => 'rb.example.com',
            'request_payload'   => [
                'company' => 'Rollback Co', 'owner_name' => 'Owner',
                'email' => 'rb@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'rb.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_rollback_checkpoints_created(): void
    {
        $installation = $this->makeInstallation();

        CustomerInstallRollback::create([
            'installation_id' => $installation->id,
            'stage'           => 'EXTRACT_PACKAGE',
            'snapshot'        => ['remote_path' => '/var/www/test'],
            'executed_at'     => null,
            'created_at'      => now(),
        ]);

        $this->assertDatabaseHas('customer_install_rollbacks', [
            'installation_id' => $installation->id,
            'stage'           => 'EXTRACT_PACKAGE',
            'executed_at'     => null,
        ]);
    }

    public function test_rollback_marks_checkpoints_as_executed(): void
    {
        $installation = $this->makeInstallation();

        $checkpoint = CustomerInstallRollback::create([
            'installation_id' => $installation->id,
            'stage'           => 'CONFIGURE_QUEUE',
            'snapshot'        => ['config_path' => '/etc/supervisor/conf.d/test.conf'],
            'executed_at'     => null,
            'created_at'      => now(),
        ]);

        $service = app(CustomerInstallationRollbackService::class);
        $service->rollback($installation);

        $checkpoint->refresh();
        $this->assertNotNull($checkpoint->executed_at);
    }

    public function test_rollback_transitions_to_rolled_back_status(): void
    {
        $installation = $this->makeInstallation();

        $service = app(CustomerInstallationRollbackService::class);
        $service->rollback($installation);

        $installation->refresh();
        $this->assertContains($installation->status, ['rolled_back', 'rollback_failed']);
    }

    public function test_immutable_log_entries_cannot_be_updated(): void
    {
        $installation = $this->makeInstallation();

        $state = new InstallationStateManager();
        $state->markRunning($installation);

        $log = \App\Models\CustomerInstallationLog::where('installation_id', $installation->id)->first();
        $this->assertNotNull($log);

        // Test immutability — update should silently return false
        $result = $log->update(['message' => 'tampered']);
        $this->assertFalse($result);

        $log->refresh();
        $this->assertNotEquals('tampered', $log->message);
    }

    public function test_log_records_all_stage_events(): void
    {
        $installation = $this->makeInstallation();
        $state = new InstallationStateManager();

        $state->markRunning($installation);
        $state->recordStageStart($installation, 'VALIDATE_SERVER');
        $state->recordStageComplete($installation, 'VALIDATE_SERVER', ['server_info' => 'Ubuntu'], 100);
        $state->recordStageFailure($installation, 'VALIDATE_PHP', 'PHP not found', 50);

        $logs = \App\Models\CustomerInstallationLog::where('installation_id', $installation->id)->get();

        $events = $logs->pluck('event')->toArray();

        $this->assertContains('started', $events);
        $this->assertContains('completed', $events);
        $this->assertContains('failed', $events);
    }

    // ─── Phase 13-M3a — Module Update Rollback Order ───────────────────────

    private function makeModuleUpdateInstallation(): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_MODULE_UPDATE',
            'status'            => 'failed',
            'failed_at'         => now(),
            'site_id'           => 999,
            'customer_company'  => 'Module Update Co',
            'customer_email'    => 'mu@example.com',
            'customer_domain'   => 'mu.example.com',
            'request_payload'   => [
                'company' => 'Module Update Co', 'owner_name' => 'Owner',
                'email' => 'mu@example.com', 'phone' => '09123456789',
                'domain' => 'mu.example.com',
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_MODULE_UPDATE',
            ],
        ]);
    }

    private function seedCheckpoint(CustomerInstallation $installation, string $stage, array $snapshot): void
    {
        CustomerInstallRollback::create([
            'installation_id' => $installation->id,
            'stage'           => $stage,
            'snapshot'        => $snapshot,
            'executed_at'     => null,
            'created_at'      => now(),
        ]);
    }

    /**
     * Phase 13-M3a — proves the fix end-to-end through the real
     * CustomerInstallationRollbackService: checkpoints are seeded in the
     * new forward order (LOCK_TENANT created before BACKUP_MODULE_UPGRADE,
     * matching CustomerInstallationOrchestrator::moduleUpdateStages()), so
     * LIFO replay must restore the database (BACKUP_MODULE_UPGRADE.rollback())
     * before it unlocks the tenant (LOCK_TENANT.rollback()) — verified by
     * the actual order of remote commands issued.
     */
    public function test_rollback_restores_database_before_unlocking_tenant(): void
    {
        $fixture = storage_path('app/install-packages/m3a_rollback_order_fixture.sql.gz');
        file_put_contents($fixture, 'ordered-dump-content');
        $sha256 = hash_file('sha256', $fixture);

        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_DATABASE='", 0, "testdb\n");
        $driver->addResponse("grep '^DB_USERNAME='", 0, "testuser\n");
        $driver->addResponse("grep '^DB_PASSWORD='", 0, "testpass\n");
        $driver->addResponse('gunzip -c', 0, "restore-ok\n");
        $driver->addResponse('mysql -N -u', 0, "4\n");

        $this->mock(RemoteConnectionService::class, function ($mock) use ($driver) {
            $mock->shouldReceive('buildDriver')->andReturn($driver);
        });

        $installation = $this->makeModuleUpdateInstallation();

        // Seed checkpoints in the exact order the orchestrator now produces
        // them forward: LOCK_TENANT first, then BACKUP_MODULE_UPGRADE, then
        // the remaining mutating stages.
        $this->seedCheckpoint($installation, 'LOCK_TENANT', []);
        $this->seedCheckpoint($installation, 'BACKUP_MODULE_UPGRADE', [
            'backup_sha256'    => $sha256,
            'backup_arvan_key' => null,
            'local_repo_file'  => $fixture,
        ]);
        $this->seedCheckpoint($installation, 'UPLOAD_PACKAGE', ['remote_path' => '/tmp/module.zip']);
        $this->seedCheckpoint($installation, 'VERIFY_PACKAGE', ['remote_path' => '/tmp/module.zip']);
        $this->seedCheckpoint($installation, 'EXTRACT_MODULE_PACKAGE', ['module_path' => '/var/www/test/packages/saas-platform/modules/forms']);

        try {
            app(CustomerInstallationRollbackService::class)->rollback($installation);
        } finally {
            @unlink($fixture);
        }

        $commandLog = $driver->getCommandLog();
        $restoreIdx = null;
        $unlockIdx  = null;

        foreach ($commandLog as $i => $command) {
            if ($restoreIdx === null && str_contains($command, 'gunzip -c')) {
                $restoreIdx = $i;
            }
            if ($unlockIdx === null && str_contains($command, 'artisan up')) {
                $unlockIdx = $i;
            }
        }

        $this->assertNotNull($restoreIdx, 'Expected the database restore command (gunzip -c | mysql) to run');
        $this->assertNotNull($unlockIdx, 'Expected the unlock command (artisan up) to run');
        $this->assertLessThan($unlockIdx, $restoreIdx, 'Database restore must run before the tenant is unlocked');

        $installation->refresh();
        $this->assertSame('rolled_back', $installation->status);
    }

    /**
     * Phase 13-M3b — when the database restore itself fails, replay must
     * halt immediately: LOCK_TENANT's checkpoint (the unlock) must never
     * be reached, the tenant stays locked, and the installation is marked
     * rollback_failed rather than reporting a clean rollback.
     */
    public function test_rollback_halts_and_leaves_tenant_locked_when_database_restore_fails(): void
    {
        $driver = new FakeRemoteDriver();
        // No grep/gunzip responses registered — irrelevant here, since the
        // snapshot below has no usable backup reference at all, so
        // BackupModuleUpgradeStage::rollback() throws before touching the
        // connection.

        $this->mock(RemoteConnectionService::class, function ($mock) use ($driver) {
            $mock->shouldReceive('buildDriver')->andReturn($driver);
        });

        $installation = $this->makeModuleUpdateInstallation();

        $this->seedCheckpoint($installation, 'LOCK_TENANT', []);
        $this->seedCheckpoint($installation, 'BACKUP_MODULE_UPGRADE', [
            // Deliberately missing backup_sha256/backup_arvan_key/local_repo_file
            // — BackupModuleUpgradeStage::rollback() throws immediately.
        ]);
        $this->seedCheckpoint($installation, 'UPLOAD_PACKAGE', ['remote_path' => '/tmp/module.zip']);
        $this->seedCheckpoint($installation, 'VERIFY_PACKAGE', ['remote_path' => '/tmp/module.zip']);
        $this->seedCheckpoint($installation, 'EXTRACT_MODULE_PACKAGE', ['module_path' => '/var/www/test/packages/saas-platform/modules/forms']);

        app(CustomerInstallationRollbackService::class)->rollback($installation);

        $commandLog = $driver->getCommandLog();
        $this->assertEmpty(
            array_filter($commandLog, fn ($c) => str_contains($c, 'artisan up')),
            'LOCK_TENANT.rollback() (the unlock) must never run when the database restore fails'
        );

        $installation->refresh();
        $this->assertSame('rollback_failed', $installation->status);
        $this->assertStringContainsString('BACKUP_MODULE_UPGRADE', $installation->failure_reason);

        // EXTRACT/VERIFY/UPLOAD were replayed (and succeeded) before BACKUP
        // was reached — only BACKUP (failed) and LOCK (never reached) remain pending.
        $extract = CustomerInstallRollback::where('installation_id', $installation->id)->where('stage', 'EXTRACT_MODULE_PACKAGE')->first();
        $backup  = CustomerInstallRollback::where('installation_id', $installation->id)->where('stage', 'BACKUP_MODULE_UPGRADE')->first();
        $lock    = CustomerInstallRollback::where('installation_id', $installation->id)->where('stage', 'LOCK_TENANT')->first();

        $this->assertNotNull($extract->executed_at, 'EXTRACT_MODULE_PACKAGE ran before the critical failure and should be marked executed');
        $this->assertNull($backup->executed_at, 'BACKUP_MODULE_UPGRADE failed and must not be marked executed');
        $this->assertNull($lock->executed_at, 'LOCK_TENANT must never be reached after the critical halt');
    }
}
