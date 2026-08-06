<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Infrastructure\InfraBackupRun;
use App\Models\Site;
use App\Services\Infrastructure\ArvanBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Producer-side coverage for db:backup-tenants (app/Console/Commands/
 * BackupTenantDatabaseCommand.php) — the ONLY command that produces the
 * db_backup_{siteId}_{Y-m-d_His}.sql.gz format RestoreService's tenant
 * restore path is designed to import.
 *
 * The --dry-run tests never shell out to mysqldump. The end-to-end tests
 * deliberately DO — they seed real rows into the actual local `customer_site`
 * database (via a throwaway runtime connection, mirroring how
 * PlatformSiteAgentService opens per-site connections) and run the real
 * command, because the 2026-08 incident this replaces a global --where with
 * was only ever caught by reproducing mysqldump's actual behavior — a mocked
 * DB layer would not have caught it and would not catch a regression either.
 */
class BackupTenantDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_SITE_CONN = 'tenant_backup_test_customer_site';

    protected function tearDown(): void
    {
        // A real (non-dry-run) backup creates storage/app/backups/tenants on
        // disk — RefreshDatabase only resets the database, not the
        // filesystem, so leftover directories would otherwise persist
        // across test runs and break the dry-run "touches nothing" assertion.
        $dir = storage_path('app/backups/tenants');
        if (is_dir($dir)) {
            array_map('unlink', glob("{$dir}/*") ?: []);
            @rmdir($dir);
        }

        parent::tearDown();
    }

    private function makeSite(string $title, string $domain): Site
    {
        $customer = Customer::create(['name' => $title . ' Owner', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        return Site::create([
            'customer_id' => $customer->id, 'title' => $title, 'domain' => $domain,
            'is_active' => true, 'lifecycle_status' => 'active',
        ]);
    }

    /**
     * A throwaway connection to the REAL local customer_site database —
     * the same one BackupTenantDatabaseCommand's mysqldump calls target.
     * Not a persisted config change: registered only for this test process.
     */
    private function customerSiteDb(): \Illuminate\Database\Connection
    {
        Config::set('database.connections.' . self::CUSTOMER_SITE_CONN, [
            'driver'    => 'mysql',
            'host'      => config('database.connections.mysql.host'),
            'port'      => config('database.connections.mysql.port', 3306),
            'database'  => 'customer_site',
            'username'  => config('database.connections.mysql.username'),
            'password'  => config('database.connections.mysql.password'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ]);

        DB::purge(self::CUSTOMER_SITE_CONN);

        return DB::connection(self::CUSTOMER_SITE_CONN);
    }

    /**
     * Seeds one real, minimal tenant footprint (customers + sites + menus +
     * tickets rows) in customer_site for the given site id, and returns a
     * closure that deletes exactly those rows — nothing else in the schema
     * is touched.
     */
    private function seedTenantFootprint(\Illuminate\Database\Connection $db, int $siteId, string $label): \Closure
    {
        $customerId = $db->table('customers')->insertGetId([
            'name' => "{$label} customer", 'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $db->table('sites')->insert([
            'id' => $siteId, 'customer_id' => $customerId, 'title' => $label,
            'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $db->table('menus')->insert([
            'site_id' => $siteId, 'name' => "{$label} menu", 'location' => 'primary',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $db->table('tickets')->insert([
            'site_id' => $siteId, 'ticket_number' => "TEST-{$siteId}-" . random_int(100000, 999999),
            'subject' => "{$label} ticket", 'description' => "{$label} ticket body",
            'status' => 'open', 'priority' => 'normal', 'type' => 'support',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return function () use ($db, $siteId, $customerId) {
            $db->table('tickets')->where('site_id', $siteId)->delete();
            $db->table('menus')->where('site_id', $siteId)->delete();
            $db->table('sites')->where('id', $siteId)->delete();
            $db->table('customers')->where('id', $customerId)->delete();
        };
    }

    public function test_dry_run_lists_active_sites_without_running_mysqldump(): void
    {
        $this->makeSite('Tenant One', 'one.example.com');

        $exitCode = Artisan::call('db:backup-tenants', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertDirectoryDoesNotExist(storage_path('app/backups/tenants'));
    }

    public function test_dry_run_reports_nothing_to_backup_when_no_active_sites(): void
    {
        $exitCode = Artisan::call('db:backup-tenants', ['--dry-run' => true]);
        $output   = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('nothing to backup', mb_strtolower($output));
    }

    public function test_dry_run_with_site_option_scopes_to_a_single_tenant(): void
    {
        $siteA = $this->makeSite('Tenant A', 'a.example.com');
        $this->makeSite('Tenant B', 'b.example.com');

        $exitCode = Artisan::call('db:backup-tenants', ['--dry-run' => true, '--site' => $siteA->id]);
        $output   = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('a.example.com', $output);
        $this->assertStringNotContainsString('b.example.com', $output);
    }

    public function test_dry_run_lists_the_tenant_scoped_table_map(): void
    {
        $this->makeSite('Tenant One', 'one.example.com');

        Artisan::call('db:backup-tenants', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('menus:', $output);
        $this->assertStringContainsString('tickets:', $output);
        $this->assertStringContainsString('sites:', $output);
    }

    // ─── End-to-end against the real customer_site database ──────────────────

    public function test_backup_contains_only_the_seeded_tenants_rows_and_records_complete_coverage(): void
    {
        $siteA = $this->makeSite('Tenant A', 'a.example.com');
        $siteB = $this->makeSite('Tenant B', 'b.example.com');

        $db = $this->customerSiteDb();
        $cleanupA = $this->seedTenantFootprint($db, $siteA->id, 'TenantA');
        $cleanupB = $this->seedTenantFootprint($db, $siteB->id, 'TenantB');

        $capturedGz = null;

        try {
            $this->mock(ArvanBackupService::class, function ($mock) use (&$capturedGz) {
                $mock->shouldReceive('tenantDbPath')->andReturn('customer-sites/tenant-test/database/test.sql.gz');
                $mock->shouldReceive('upload')->once()->andReturnUsing(function ($localPath) use (&$capturedGz) {
                    $capturedGz = file_get_contents($localPath);
                    return ['success' => true, 'message' => 'ok'];
                });
                $mock->shouldReceive('prune')->andReturn(['pruned' => 0, 'kept' => 0]);
            });

            $exitCode = Artisan::call('db:backup-tenants', ['--site' => $siteA->id]);

            $this->assertSame(0, $exitCode);
            $this->assertNotNull($capturedGz, 'Arvan upload should have received the generated backup file');

            $sql = gzdecode($capturedGz);

            $this->assertStringContainsString('TenantA menu', $sql);
            $this->assertStringContainsString('TenantA ticket', $sql);
            $this->assertStringNotContainsString('TenantB menu', $sql);
            $this->assertStringNotContainsString('TenantB ticket', $sql);

            $run = InfraBackupRun::where('site_id', $siteA->id)->latest('id')->first();
            $this->assertNotNull($run);
            $this->assertSame('success', $run->status);
            $this->assertSame('complete', $run->completeness_status);
            $this->assertContains('menus', $run->table_coverage['included_tables']);
            $this->assertContains('tickets', $run->table_coverage['included_tables']);
            $this->assertEmpty($run->table_coverage['skipped_tables']);
            $this->assertGreaterThan(0, $run->table_coverage['total_insert_count']);
        } finally {
            $cleanupA();
            $cleanupB();
        }
    }

    public function test_backup_of_a_genuinely_empty_tenant_still_succeeds_with_zero_inserts(): void
    {
        $site = $this->makeSite('Empty Tenant', 'empty.example.com');

        $this->mock(ArvanBackupService::class, function ($mock) {
            $mock->shouldReceive('tenantDbPath')->andReturn('customer-sites/tenant-test/database/test.sql.gz');
            $mock->shouldReceive('upload')->once()->andReturn(['success' => true, 'message' => 'ok']);
            $mock->shouldReceive('prune')->andReturn(['pruned' => 0, 'kept' => 0]);
        });

        $exitCode = Artisan::call('db:backup-tenants', ['--site' => $site->id]);

        $this->assertSame(0, $exitCode);

        $run = InfraBackupRun::where('site_id', $site->id)->latest('id')->first();
        $this->assertSame('success', $run->status);
        $this->assertSame('complete', $run->completeness_status);
        $this->assertSame(0, $run->table_coverage['total_insert_count']);
    }
}
