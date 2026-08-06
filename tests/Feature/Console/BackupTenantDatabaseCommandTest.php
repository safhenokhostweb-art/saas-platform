<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Producer-side coverage for db:backup-tenants (app/Console/Commands/
 * BackupTenantDatabaseCommand.php) — the ONLY command that produces the
 * db_backup_{siteId}_{Y-m-d_His}.sql.gz format RestoreService's tenant
 * restore path is designed to import. --dry-run is used throughout so
 * these tests never shell out to mysqldump or touch the filesystem.
 */
class BackupTenantDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(string $title, string $domain): Site
    {
        $customer = Customer::create(['name' => $title . ' Owner', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        return Site::create([
            'customer_id' => $customer->id, 'title' => $title, 'domain' => $domain,
            'is_active' => true, 'lifecycle_status' => 'active',
        ]);
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
}
