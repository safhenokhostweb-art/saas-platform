<?php

namespace Tests\Unit\Services\Infrastructure;

use App\Models\Infrastructure\RestoreLog;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Infrastructure\RestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ties each backup producer's exact filename format to the consumer-side
 * classifier in RestoreService — the two drifted apart in production
 * (2026-08-06 incident): db:backup-tenants (this app) and the tenant's own
 * dedicated-install db:backup (customer-site repo) write to the SAME Arvan
 * prefix with incompatible content, and nothing enforced that only the
 * former is safe to import into restore_tmp_{siteId}.
 *
 * These tests build filenames the exact same way each producer does
 * (same format string, same segments) so a future format change on either
 * side that breaks the contract fails here instead of in production.
 */
class TenantBackupFormatContractTest extends TestCase
{
    use RefreshDatabase;

    private RestoreService $restore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restore = app(RestoreService::class);
    }

    /**
     * Producer: app/Console/Commands/BackupTenantDatabaseCommand.php:75
     * `"db_backup_{$siteId}_" . now()->format('Y-m-d_His') . '.sql.gz'`
     * — a `--where="site_id={siteId}"` filtered dump of the SHARED
     * customer_site database. The only format executeTenantDatabaseRestore()
     * is designed to import into restore_tmp_{siteId}.
     */
    public function test_db_backup_tenants_producer_format_is_accepted_as_tenant_scoped(): void
    {
        $siteId   = 1;
        $filename = "db_backup_{$siteId}_" . now()->format('Y-m-d_His') . '.sql.gz';

        $this->assertTrue($this->restore->isTenantScopedBackupFilename($filename, $siteId));
    }

    /**
     * Producer: customer-site/app/Console/Commands/BackupDatabaseCommand.php:30
     * `"backup_{$timestamp}.{$extension}"` where `$timestamp = now()->format('Y-m-d_H-i-s')`
     * — a full, unfiltered dump of the tenant's OWN dedicated database
     * (--routines --triggers included), uploaded to the exact same
     * customer-sites/tenant-{siteId}/database/ prefix as the format above.
     * Must never be accepted for the shared-schema tenant restore path.
     */
    public function test_customer_site_dedicated_db_backup_format_is_rejected_as_tenant_scoped(): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename  = "backup_{$timestamp}.sql.gz";

        $this->assertFalse($this->restore->isTenantScopedBackupFilename($filename));
        $this->assertFalse($this->restore->isTenantScopedBackupFilename($filename, 1));
    }

    /**
     * Producer: app/Console/Commands/BackupDatabaseCommand.php:30 (this app's
     * OWN platform-wide backup, no siteId segment at all) — also must not be
     * mistaken for a tenant-row-scoped backup just because it starts with
     * "db_backup_".
     */
    public function test_platform_wide_db_backup_format_is_rejected_as_tenant_scoped(): void
    {
        $filename = 'db_backup_' . now()->format('Y-m-d_His') . '.sql.gz';

        $this->assertFalse($this->restore->isTenantScopedBackupFilename($filename));
    }

    /**
     * The exact two filenames from the reported production incident.
     */
    public function test_reported_incident_filenames_classify_correctly(): void
    {
        $this->assertTrue($this->restore->isTenantScopedBackupFilename('db_backup_1_2026-08-06_023014.sql.gz', 1));
        $this->assertFalse($this->restore->isTenantScopedBackupFilename('backup_2026-08-06_02-00-04.sql.gz', 1));
    }

    // ─── Defense-in-depth: the service layer must reject on its own ──────────
    //
    // Reproduces the 2026-08-06 "validation bypass" report: a caller that
    // never went through RestoreWizardController::execute() (a direct
    // RestoreService::executeTenantDatabaseRestore() call — exactly what a
    // stale/queued job or a future alternate dispatch path would do) must
    // still be rejected. If this test fails, the service-level gate has
    // regressed and the controller is the ONLY thing standing between an
    // incompatible file and `CREATE DATABASE restore_tmp_{siteId}`.

    public function test_executeTenantDatabaseRestore_rejects_incompatible_filename_without_calling_arvan_at_all(): void
    {
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldNotReceive('download');
        });

        $log = RestoreLog::create([
            'restore_type'  => RestoreLog::TYPE_TENANT_DATABASE,
            'site_id'       => 1,
            'backup_file'   => 'customer-sites/tenant-1/database/backup_2026-08-06_02-00-04.sql.gz',
            'status'        => RestoreLog::STATUS_PENDING,
            'restore_token' => Str::random(40),
        ]);

        app(RestoreService::class)->executeTenantDatabaseRestore(
            $log,
            'customer-sites/tenant-1/database/backup_2026-08-06_02-00-04.sql.gz',
            1
        );

        $log->refresh();
        $this->assertSame(RestoreLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('سازگار نیست', $log->error_message);
        $this->assertStringContainsString('backup_2026-08-06_02-00-04.sql.gz', $log->error_message);
    }

    public function test_executeTenantDatabaseRestore_proceeds_past_the_gate_for_a_compatible_filename(): void
    {
        // Compatible filename must clear the Step-0 gate and reach the
        // download step (which then fails cleanly because Arvan has no
        // real file for this test) — proving the gate isn't over-broad and
        // rejecting the format it's supposed to accept.
        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('download')->once()->andReturn(false);
        });

        $log = RestoreLog::create([
            'restore_type'  => RestoreLog::TYPE_TENANT_DATABASE,
            'site_id'       => 1,
            'backup_file'   => 'customer-sites/tenant-1/database/db_backup_1_2026-08-06_023014.sql.gz',
            'status'        => RestoreLog::STATUS_PENDING,
            'restore_token' => Str::random(40),
        ]);

        app(RestoreService::class)->executeTenantDatabaseRestore(
            $log,
            'customer-sites/tenant-1/database/db_backup_1_2026-08-06_023014.sql.gz',
            1
        );

        $log->refresh();
        $this->assertSame(RestoreLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('دانلود', $log->error_message);
    }
}
