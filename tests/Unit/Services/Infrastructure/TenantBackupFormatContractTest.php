<?php

namespace Tests\Unit\Services\Infrastructure;

use App\Services\Infrastructure\RestoreService;
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
}
