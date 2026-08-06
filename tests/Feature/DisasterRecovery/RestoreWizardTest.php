<?php
namespace Tests\Feature\DisasterRecovery;

use App\Models\Infrastructure\RestoreLog;
use App\Models\Site;
use App\Models\User;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\RestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RestoreWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->site  = Site::create([
            'title'            => 'Test Site',
            'domain'           => 'test.example.com',
            'is_active'        => true,
            'lifecycle_status' => 'active',
        ]);

        // Clean Redis state before each test
        Redis::del('restore_in_progress');
    }

    // ─── Access Control Tests ─────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_restore_wizard(): void
    {
        $this->get(route('admin.disaster-recovery.index'))
             ->assertRedirect();
    }

    public function test_non_admin_cannot_access_restore_wizard(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user)
             ->get(route('admin.disaster-recovery.index'))
             ->assertForbidden();
    }

    public function test_admin_can_access_restore_wizard_index(): void
    {
        $this->actingAs($this->admin)
             ->get(route('admin.disaster-recovery.index'))
             ->assertOk()
             ->assertViewIs('admin.disaster-recovery.index');
    }

    // ─── Browse Tests ─────────────────────────────────────────────────────────

    public function test_browse_requires_valid_type(): void
    {
        $this->actingAs($this->admin)
             ->getJson(route('admin.disaster-recovery.browse', ['type' => 'invalid_type']))
             ->assertStatus(422)
             ->assertJsonFragment(['error' => 'نوع بازیابی نامعتبر است']);
    }

    public function test_browse_tenant_type_requires_site_id(): void
    {
        $this->actingAs($this->admin)
             ->getJson(route('admin.disaster-recovery.browse', ['type' => 'tenant_database']))
             ->assertStatus(422)
             ->assertJsonFragment(['error' => 'site_id برای بازیابی تِنِنت الزامی است']);
    }

    public function test_browse_platform_database_returns_file_list(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('listFiles')
                 ->with('saas-platform/database')
                 ->once()
                 ->andReturn([
                     ['path' => 'saas-platform/database/db_backup_2026-06-25_020000.sql.gz', 'filename' => 'db_backup_2026-06-25_020000.sql.gz', 'size' => 5242880, 'last_modified' => 1750857600],
                 ]);
        });

        $response = $this->actingAs($this->admin)
             ->getJson(route('admin.disaster-recovery.browse', ['type' => 'platform_database']));

        $response->assertOk()
                 ->assertJsonPath('count', 1)
                 ->assertJsonPath('files.0.filename', 'db_backup_2026-06-25_020000.sql.gz');
    }

    public function test_browse_tenant_database_uses_correct_prefix(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('listFiles')
                 ->with("customer-sites/tenant-{$this->site->id}/database")
                 ->once()
                 ->andReturn([]);
        });

        $this->actingAs($this->admin)
             ->getJson(route('admin.disaster-recovery.browse', ['type' => 'tenant_database', 'site_id' => $this->site->id]))
             ->assertOk()
             ->assertJsonPath('count', 0);
    }

    // ─── Validation Tests ─────────────────────────────────────────────────────

    public function test_validate_backup_file_exists(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->once()->andReturn(true);
            $mock->shouldReceive('size')->andReturn(5242880);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $response = $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.validate'), [
                 'backup_file' => 'saas-platform/database/db_backup_2026-06-25_020000.sql.gz',
                 'type'        => 'platform_database',
             ]);

        $response->assertOk()
                 ->assertJsonPath('valid', true);
    }

    public function test_validate_returns_invalid_when_file_not_found(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->once()->andReturn(false);
            $mock->shouldReceive('size')->andReturn(null);
            $mock->shouldReceive('lastModified')->andReturn(null);
        });

        $response = $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.validate'), [
                 'backup_file' => 'saas-platform/database/nonexistent.sql.gz',
                 'type'        => 'platform_database',
             ]);

        $response->assertOk()
                 ->assertJsonPath('valid', false);
    }

    public function test_validate_returns_invalid_when_another_restore_is_locked(): void
    {
        Redis::set('restore_in_progress', 'platform-db', 'EX', 1800);

        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->once()->andReturn(true);
            $mock->shouldReceive('size')->andReturn(5242880);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $response = $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.validate'), [
                 'backup_file' => 'saas-platform/database/db_backup_2026-06-25_020000.sql.gz',
                 'type'        => 'platform_database',
             ]);

        $response->assertOk()
                 ->assertJsonPath('valid', false);
    }

    // ─── Dry Run Tests ────────────────────────────────────────────────────────

    public function test_dry_run_platform_database_creates_log(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('size')->andReturn(5242880);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.dry-run'), [
                 'type'        => 'platform_database',
                 'backup_file' => 'saas-platform/database/db_backup_2026-06-25_020000.sql.gz',
             ])
             ->assertOk()
             ->assertJsonStructure(['type', 'validation', 'steps', 'warnings', 'impact', 'safe_to_run']);

        $this->assertDatabaseHas('restore_logs', [
            'restore_type' => 'platform_database',
            'dry_run'      => true,
            'status'       => RestoreLog::STATUS_DRY_RUN_COMPLETE,
        ]);
    }

    public function test_dry_run_tenant_requires_site_id(): void
    {
        $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.dry-run'), [
                 'type'        => 'tenant_database',
                 'backup_file' => 'customer-sites/tenant-1/database/backup.sql.gz',
             ])
             ->assertStatus(422)
             ->assertJsonFragment(['error' => 'site_id برای بازیابی تِنِنت الزامی است']);
    }

    public function test_dry_run_includes_correct_steps_for_tenant(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('size')->andReturn(1048576);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $response = $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.dry-run'), [
                 'type'        => 'tenant_database',
                 'backup_file' => "customer-sites/tenant-{$this->site->id}/database/backup.sql.gz",
                 'site_id'     => $this->site->id,
             ])
             ->assertOk();

        $steps = $response->json('steps');
        $this->assertIsArray($steps);
        $this->assertGreaterThanOrEqual(10, count($steps));

        // Verify tenant isolation steps are present
        $stepActions = array_column($steps, 'action');
        $this->assertTrue(collect($stepActions)->contains(fn($a) => str_contains($a, "site_id={$this->site->id}")));
    }

    // ─── Cross-Tenant Protection Tests ────────────────────────────────────────

    public function test_dry_run_rejects_nonexistent_site(): void
    {
        $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.dry-run'), [
                 'type'        => 'tenant_database',
                 'backup_file' => 'customer-sites/tenant-9999/database/backup.sql.gz',
                 'site_id'     => 9999,
             ])
             ->assertStatus(422);
    }

    // ─── Execute Tests (Validation Gate) ─────────────────────────────────────

    public function test_execute_requires_confirmation(): void
    {
        $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.execute'), [
                 'type'         => 'platform_storage',
                 'backup_file'  => 'saas-platform/storage/storage_backup_2026-06-25_020000.tar.gz',
                 'confirmation' => false,
             ])
             ->assertStatus(422);
    }

    public function test_execute_platform_database_requires_confirm_text(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('size')->andReturn(5242880);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.execute'), [
                 'type'         => 'platform_database',
                 'backup_file'  => 'saas-platform/database/db_backup_2026-06-25_020000.sql.gz',
                 'confirmation' => true,
                 'confirm_text' => 'WRONG-TEXT',
             ])
             ->assertStatus(422)
             ->assertJsonFragment(['error' => 'متن تأییدیه اشتباه است. باید دقیقاً RESTORE-PLATFORM باشد']);
    }

    public function test_execute_blocked_when_another_restore_running(): void
    {
        Redis::set('restore_in_progress', 'platform-db', 'EX', 1800);

        $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.execute'), [
                 'type'         => 'platform_storage',
                 'backup_file'  => 'saas-platform/storage/backup.tar.gz',
                 'confirmation' => true,
             ])
             ->assertStatus(409);
    }

    public function test_execute_creates_restore_log_and_dispatches_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('size')->andReturn(5242880);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $response = $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.execute'), [
                 'type'         => 'tenant_database',
                 'backup_file'  => "customer-sites/tenant-{$this->site->id}/database/db_backup_{$this->site->id}_2026-06-25_020000.sql.gz",
                 'site_id'      => $this->site->id,
                 'confirmation' => true,
             ]);

        $response->assertOk()
                 ->assertJsonStructure(['success', 'token', 'log_id', 'stream_url']);

        $this->assertDatabaseHas('restore_logs', [
            'restore_type' => 'tenant_database',
            'site_id'      => $this->site->id,
            'dry_run'      => false,
            'status'       => RestoreLog::STATUS_PENDING,
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RestoreTenantDatabaseJob::class, function ($job) {
            return $job->siteId === $this->site->id;
        });
    }

    // ─── Abort Tests ──────────────────────────────────────────────────────────

    public function test_abort_clears_lock_for_pending_restore(): void
    {
        Redis::set('restore_in_progress', 'tenant-1', 'EX', 1800);
        RestoreLog::factory()->create([
            'status' => RestoreLog::STATUS_PENDING,
            'restore_token' => 'pending-abort-token',
        ]);

        $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.abort'))
             ->assertOk()
             ->assertJsonPath('success', true);

        $this->assertNull(Redis::get('restore_in_progress'));
        $this->assertDatabaseHas('restore_logs', ['status' => RestoreLog::STATUS_ABORTED]);
    }

    public function test_abort_preserves_lock_for_running_restore(): void
    {
        Redis::set('restore_in_progress', 'tenant-1', 'EX', 1800);
        RestoreLog::factory()->create([
            'status' => RestoreLog::STATUS_RUNNING,
            'restore_token' => 'running-abort-token',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.disaster-recovery.abort'))
            ->assertStatus(409);

        $this->assertNotNull(Redis::get('restore_in_progress'));
    }

    public function test_tenant_storage_restore_is_blocked_until_scoped_format_exists(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.disaster-recovery.execute'), [
                'type' => RestoreLog::TYPE_TENANT_STORAGE,
                'backup_file' => 'customer-sites/tenant-1/storage/archive.tar.gz',
                'site_id' => $this->site->id,
                'confirmation' => true,
            ])
            ->assertStatus(422);
    }

    public function test_abort_when_no_lock_returns_gracefully(): void
    {
        $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.abort'))
             ->assertOk()
             ->assertJsonPath('success', null); // message key, no error
    }

    // ─── Status Tests ─────────────────────────────────────────────────────────

    public function test_status_returns_locked_false_when_no_lock(): void
    {
        $this->actingAs($this->admin)
             ->getJson(route('admin.disaster-recovery.status'))
             ->assertOk()
             ->assertJsonPath('locked', false);
    }

    public function test_status_returns_locked_true_when_restore_running(): void
    {
        Redis::set('restore_in_progress', 'platform-db', 'EX', 1800);

        $this->actingAs($this->admin)
             ->getJson(route('admin.disaster-recovery.status'))
             ->assertOk()
             ->assertJsonPath('locked', true)
             ->assertJsonPath('lock_value', 'platform-db');
    }

    // ─── Logs Tests ───────────────────────────────────────────────────────────

    public function test_logs_page_shows_restore_history(): void
    {
        RestoreLog::factory()->count(3)->create([
            'restore_type' => RestoreLog::TYPE_PLATFORM_DATABASE,
            'status'       => RestoreLog::STATUS_SUCCESS,
            'executed_by'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
             ->get(route('admin.disaster-recovery.logs'))
             ->assertOk()
             ->assertViewIs('admin.disaster-recovery.logs');
    }

    // ─── RestoreService Unit Tests ────────────────────────────────────────────

    public function test_restore_service_lock_acquire_and_release(): void
    {
        $service = app(RestoreService::class);

        $this->assertFalse($service->isLocked());
        $this->assertTrue($service->acquireLock('test-lock', 1));
        $this->assertTrue($service->isLocked());
        $this->assertEquals('test-lock', $service->getLockValue());

        $service->releaseLock();
        $this->assertFalse($service->isLocked());
    }

    public function test_restore_service_progress_push_and_read(): void
    {
        $service = app(RestoreService::class);
        $token   = 'test-token-' . uniqid();

        $service->pushProgress($token, ['step' => 1, 'message' => 'test', 'progress' => 10]);
        $service->pushProgress($token, ['step' => 2, 'message' => 'done', 'progress' => 100]);

        $events = $service->getProgressEvents($token);
        $this->assertCount(2, $events);

        $first = json_decode($events[0], true);
        $this->assertEquals(1, $first['step']);
        $this->assertEquals(10, $first['progress']);

        $service->clearProgress($token);
        $this->assertCount(0, $service->getProgressEvents($token));
    }

    public function test_restore_service_dry_run_returns_expected_structure(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('size')->andReturn(5242880);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $service = app(RestoreService::class);
        $result  = $service->dryRun('platform_database', 'saas-platform/database/db_backup.sql.gz');

        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('validation', $result);
        $this->assertArrayHasKey('steps', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('impact', $result);
        $this->assertArrayHasKey('safe_to_run', $result);
        $this->assertNotEmpty($result['steps']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_human_size_formatting(): void
    {
        $service = app(RestoreService::class);
        $this->assertEquals('0 B', $service->humanSize(0));
        $this->assertEquals('1 KB', $service->humanSize(1024));
        $this->assertEquals('1 MB', $service->humanSize(1048576));
        $this->assertEquals('5 MB', $service->humanSize(5242880));
    }

    // ─── Backup Format Contract — both naming formats, both producers ────────
    //
    // Reproduces the 2026-08-06 incident: db:backup-tenants (this app) and the
    // tenant's own dedicated-install db:backup (customer-site repo) both write
    // to customer-sites/tenant-{siteId}/database/ but with incompatible
    // content — one is a `--where site_id=X` row-scoped dump of the shared
    // customer_site DB, the other is a full unfiltered dump of the tenant's
    // own dedicated database. Only the first is safe to import into
    // restore_tmp_{siteId}.

    public function test_tenant_scoped_filename_matches_db_backup_tenants_format(): void
    {
        $service = app(RestoreService::class);

        $this->assertTrue($service->isTenantScopedBackupFilename('db_backup_1_2026-08-06_023014.sql.gz'));
        $this->assertTrue($service->isTenantScopedBackupFilename('db_backup_1_2026-08-06_023014.sql.gz', 1));
    }

    public function test_tenant_scoped_filename_rejects_dedicated_full_db_backup_format(): void
    {
        $service = app(RestoreService::class);

        // The customer-site repo's own db:backup command — a full dump of
        // that tenant's OWN dedicated database, not the shared customer_site DB.
        $this->assertFalse($service->isTenantScopedBackupFilename('backup_2026-08-06_02-00-04.sql.gz'));
    }

    public function test_tenant_scoped_filename_rejects_mismatched_site_id(): void
    {
        $service = app(RestoreService::class);

        // Filename belongs to tenant 2 — must not be accepted for tenant 1's restore.
        $this->assertFalse($service->isTenantScopedBackupFilename('db_backup_2_2026-08-06_023014.sql.gz', 1));
    }

    public function test_validate_rejects_dedicated_full_db_backup_for_tenant_database_type(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('size')->andReturn(147 * 1024);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $response = $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.validate'), [
                 'backup_file' => "customer-sites/tenant-{$this->site->id}/database/backup_2026-08-06_02-00-04.sql.gz",
                 'type'        => 'tenant_database',
                 'site_id'     => $this->site->id,
             ]);

        $response->assertOk()->assertJsonPath('valid', false);
    }

    public function test_validate_accepts_tenant_scoped_backup_for_tenant_database_type(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('size')->andReturn(810);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $response = $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.validate'), [
                 'backup_file' => "customer-sites/tenant-{$this->site->id}/database/db_backup_{$this->site->id}_2026-08-06_023014.sql.gz",
                 'type'        => 'tenant_database',
                 'site_id'     => $this->site->id,
             ]);

        $response->assertOk()->assertJsonPath('valid', true);
    }

    public function test_execute_rejects_dedicated_full_db_backup_for_tenant_database_type(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('size')->andReturn(147 * 1024);
            $mock->shouldReceive('lastModified')->andReturn(1750857600);
        });

        $response = $this->actingAs($this->admin)
             ->postJson(route('admin.disaster-recovery.execute'), [
                 'type'         => 'tenant_database',
                 'backup_file'  => "customer-sites/tenant-{$this->site->id}/database/backup_2026-08-06_02-00-04.sql.gz",
                 'site_id'      => $this->site->id,
                 'confirmation' => true,
             ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('restore_logs', [
            'restore_type' => 'tenant_database',
            'site_id'      => $this->site->id,
            'status'       => RestoreLog::STATUS_PENDING,
        ]);
    }

    public function test_browse_tenant_database_flags_incompatible_backups_but_keeps_them_listed(): void
    {
        $siteId = $this->site->id;

        $this->mock(ArvanBackupService::class, function ($mock) use ($siteId) {
            $mock->shouldReceive('listFiles')
                 ->with("customer-sites/tenant-{$siteId}/database")
                 ->once()
                 ->andReturn([
                     ['path' => "customer-sites/tenant-{$siteId}/database/db_backup_{$siteId}_2026-08-06_023014.sql.gz", 'filename' => "db_backup_{$siteId}_2026-08-06_023014.sql.gz", 'size' => 810, 'last_modified' => 1754440214],
                     ['path' => "customer-sites/tenant-{$siteId}/database/backup_2026-08-06_02-00-04.sql.gz", 'filename' => 'backup_2026-08-06_02-00-04.sql.gz', 'size' => 147 * 1024, 'last_modified' => 1754438404],
                 ]);
        });

        $response = $this->actingAs($this->admin)
             ->getJson(route('admin.disaster-recovery.browse', ['type' => 'tenant_database', 'site_id' => $siteId]));

        $response->assertOk()->assertJsonPath('count', 2);

        $files = $response->json('files');
        $this->assertTrue($files[0]['compatible']);
        $this->assertNull($files[0]['incompatible_reason']);
        $this->assertFalse($files[1]['compatible']);
        $this->assertNotEmpty($files[1]['incompatible_reason']);
    }

    // ─── Content-level directive detection ────────────────────────────────────

    public function test_detects_create_database_as_incompatible(): void
    {
        $service = app(RestoreService::class);
        $sql = "CREATE DATABASE `customer_site`;\nINSERT INTO `users` VALUES (1);";

        $this->assertSame('CREATE DATABASE', $service->detectIncompatibleTenantRestoreDirective($sql));
    }

    public function test_detects_definer_clause_as_incompatible(): void
    {
        $service = app(RestoreService::class);
        $sql = "/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg` BEFORE INSERT ON `orders` */;";

        $this->assertSame('DEFINER clause', $service->detectIncompatibleTenantRestoreDirective($sql));
    }

    public function test_detects_lock_tables_as_incompatible(): void
    {
        $service = app(RestoreService::class);
        $sql = "LOCK TABLES `orders` WRITE;\nINSERT INTO `orders` VALUES (1);\nUNLOCK TABLES;";

        $this->assertSame('LOCK TABLES', $service->detectIncompatibleTenantRestoreDirective($sql));
    }

    public function test_detects_database_qualified_table_name_as_incompatible(): void
    {
        $service = app(RestoreService::class);
        $sql = "INSERT INTO `customer_dedicated_db`.`orders` VALUES (1);";

        $this->assertSame('database-qualified table name', $service->detectIncompatibleTenantRestoreDirective($sql));
    }

    public function test_row_scoped_dump_content_has_no_incompatible_directives(): void
    {
        $service = app(RestoreService::class);
        // Shape of a real db:backup-tenants dump: no --databases flag, so no
        // CREATE DATABASE/USE, no --routines/--triggers, so no DEFINER.
        $sql = "-- MySQL dump\nINSERT INTO `sites` (`id`, `site_id`) VALUES (1, 1);\n";

        $this->assertNull($service->detectIncompatibleTenantRestoreDirective($sql));
    }

    public function test_scans_gzipped_dump_for_incompatible_directives(): void
    {
        $service = app(RestoreService::class);
        $path    = tempnam(sys_get_temp_dir(), 'restore-scan-test') . '.sql.gz';

        file_put_contents($path, gzencode("CREATE DATABASE `customer_dedicated_db`;\nINSERT INTO `orders` VALUES (1);"));

        try {
            $this->assertSame('CREATE DATABASE', $service->scanGzipForIncompatibleTenantRestoreDirective($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_scans_gzipped_row_scoped_dump_as_compatible(): void
    {
        $service = app(RestoreService::class);
        $path    = tempnam(sys_get_temp_dir(), 'restore-scan-test') . '.sql.gz';

        file_put_contents($path, gzencode("-- MySQL dump\nINSERT INTO `sites` (`id`, `site_id`) VALUES (1, 1);\n"));

        try {
            $this->assertNull($service->scanGzipForIncompatibleTenantRestoreDirective($path));
        } finally {
            @unlink($path);
        }
    }
}
