<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerInstallation;
use App\Services\EntitlementLedger;
use App\Services\SiteDeletionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Onboarding\OnboardingFixtures;
use Tests\TestCase;

/**
 * Admin site delete: it used to answer 500 (SQLSTATE 1451) for any site with
 * licence or motion history, and refreshing that page opened the edit form.
 */
class SiteDestroyTest extends TestCase
{
    use RefreshDatabase;
    use OnboardingFixtures;

    private const HISTORY_TABLES = [
        'module_entitlements',
        'motion_bespoke_commissions',
        'motion_bespoke_assignments',
        'motion_bespoke_purchases',
        'motion_site_asset_packages',
    ];

    public function test_site_without_history_is_deleted_and_its_installations_are_marked_deleted(): void
    {
        $site = $this->site($this->customer(), 'plain.example.ir');
        $installation = CustomerInstallation::create([
            'site_id' => $site->id, 'remote_server_id' => $this->server()->id,
            'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH',
            'status' => 'complete', 'customer_company' => 'x', 'customer_email' => '',
            'customer_domain' => 'plain.example.ir',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.sites.destroy', $site))
            ->assertRedirect(route('admin.sites.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertSame('deleted', $installation->fresh()->status);
    }

    public function test_site_with_licence_history_is_deleted_and_the_ledger_row_is_kept_detached(): void
    {
        $customer = $this->customer();
        $site = $this->site($customer, 'licensed.example.ir');
        $row = app(EntitlementLedger::class)->grant($site, $this->solarKit(), 'admin_grant');
        $this->assertNotNull($row);

        $this->actingAs($this->admin())
            ->delete(route('admin.sites.destroy', $site))
            ->assertRedirect(route('admin.sites.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertDatabaseHas('module_entitlements', [
            'id' => $row->id, 'site_id' => null, 'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'site.deleted']);
    }

    public function test_every_history_table_detaches_instead_of_blocking(): void
    {
        foreach (self::HISTORY_TABLES as $table) {
            $fk = DB::selectOne(
                'SELECT rc.DELETE_RULE AS rule FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                   JOIN information_schema.KEY_COLUMN_USAGE k
                     ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
                  WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = ?
                    AND k.COLUMN_NAME = "site_id" AND k.REFERENCED_TABLE_NAME = "sites"',
                [$table]
            );
            $this->assertNotNull($fk, "{$table}.site_id must reference sites");
            $this->assertSame('SET NULL', $fk->rule, "{$table}.site_id");

            $nullable = DB::selectOne(
                'SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = "site_id"',
                [$table]
            );
            $this->assertSame('YES', $nullable->n, "{$table}.site_id nullable");
        }

        // No foreign key to sites may block a delete any more.
        $blocking = DB::select(
            'SELECT k.TABLE_NAME AS t FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                 ON rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
              WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME = "sites"
                AND rc.DELETE_RULE NOT IN ("CASCADE", "SET NULL")'
        );
        $this->assertSame([], array_map(fn ($r) => $r->t, $blocking));
    }

    public function test_a_failed_delete_returns_to_the_list_with_a_readable_error_not_a_500(): void
    {
        $site = $this->site($this->customer(), 'stuck.example.ir');

        $this->mock(SiteDeletionService::class)
            ->shouldReceive('delete')
            ->andThrow(new QueryException('mysql', 'delete from `sites` where `id` = ?', [$site->id], new class('Cannot delete or update a parent row') extends \PDOException {
                public function __construct(string $m)
                {
                    parent::__construct($m);
                    $this->errorInfo = ['23000', 1451, $m];
                }
            }));

        $this->actingAs($this->admin())
            ->delete(route('admin.sites.destroy', $site))
            ->assertRedirect(route('admin.sites.index'))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'حذف نشد') && str_contains($m, 'وابسته'));

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'site.delete_failed']);
    }

    public function test_non_admin_cannot_delete_a_site(): void
    {
        $site = $this->site($this->customer(), 'guarded.example.ir');
        $user = \App\Models\User::create(['mobile' => '09120000001', 'password' => bcrypt('x'), 'is_admin' => false, 'is_active' => true]);

        $this->actingAs($user)->delete(route('admin.sites.destroy', $site))->assertForbidden();

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }
}
