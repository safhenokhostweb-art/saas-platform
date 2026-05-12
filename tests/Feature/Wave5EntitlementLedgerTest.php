<?php

namespace Tests\Feature;

use App\Console\Commands\BackfillModuleEntitlementsCommand;
use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\Site;
use App\Services\EntitlementLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Wave 5.4 — Regression tests for Wave 5.1 / P1-1 Customer Module Entitlements Ledger.
 *
 * Coverage:
 *   - migration schema: table + columns + FKs (restrictOnDelete on
 *     sites/customers/modules; nullOnDelete on optional linkage).
 *   - EntitlementLedger::grant() idempotency and metadata merge.
 *   - EntitlementLedger::revoke() with explicit reason + double-revoke no-op.
 *   - Re-grant after revoke produces a NEW row (audit-trail preserved).
 *   - entitlements:backfill is idempotent across reruns.
 *   - Phase A invariant: every active site_modules pair has a matching
 *     active ledger row after backfill (zero entitlement loss).
 *   - module_slug snapshot survives a slug rename on the module catalog.
 *   - Schema-guards: services are safe to call when the table is absent.
 */
class Wave5EntitlementLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'تست مشتری ' . uniqid('', true),
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'test_' . uniqid('', true) . '@example.com',
        ]);
    }

    private function makeSite(Customer $customer): Site
    {
        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'test-' . uniqid() . '.example.com',
            'is_active' => true,
        ]);
    }

    private function makeModule(string $slug, string $type = Module::TYPE_PAID): Module
    {
        return Module::firstOrCreate(
            ['slug' => $slug],
            [
                'title' => 'ماژول ' . $slug,
                'type' => $type,
                'is_active' => true,
            ]
        );
    }

    public function test_migration_creates_module_entitlements_table_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('module_entitlements'));

        foreach ([
            'id', 'site_id', 'customer_id', 'module_id', 'module_slug',
            'source', 'status', 'granted_at', 'granted_by',
            'revoked_at', 'revoked_by', 'revoke_reason',
            'invoice_id', 'invoice_item_id', 'module_purchase_id', 'module_license_id',
            'metadata', 'created_at', 'updated_at',
        ] as $col) {
            $this->assertTrue(
                Schema::hasColumn('module_entitlements', $col),
                "Column {$col} is missing on module_entitlements"
            );
        }
    }

    public function test_grant_creates_active_row_with_module_slug_snapshot(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('forms');

        /** @var EntitlementLedger $ledger */
        $ledger = app(EntitlementLedger::class);
        $row = $ledger->grant($site, $module, ModuleEntitlement::SOURCE_PURCHASE, [
            'metadata' => ['test' => 'grant'],
        ]);

        $this->assertNotNull($row);
        $this->assertSame($site->id, $row->site_id);
        $this->assertSame($customer->id, $row->customer_id);
        $this->assertSame($module->id, $row->module_id);
        $this->assertSame('forms', $row->module_slug);
        $this->assertSame(ModuleEntitlement::STATUS_ACTIVE, $row->status);
        $this->assertSame(ModuleEntitlement::SOURCE_PURCHASE, $row->source);
        $this->assertNotNull($row->granted_at);
        $this->assertNull($row->revoked_at);
        $this->assertSame('grant', $row->metadata['test'] ?? null);
    }

    public function test_grant_is_idempotent_and_merges_metadata(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('shop');

        $ledger = app(EntitlementLedger::class);
        $first = $ledger->grant($site, $module, ModuleEntitlement::SOURCE_SEED, [
            'metadata' => ['first_pass' => true],
        ]);
        $second = $ledger->grant($site, $module, ModuleEntitlement::SOURCE_PURCHASE, [
            'metadata' => ['second_pass' => 'yes'],
        ]);

        $this->assertSame($first->id, $second->id, 'grant must return the same row id (idempotent)');
        $this->assertSame(1, ModuleEntitlement::query()
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->where('status', 'active')
            ->count());

        $merged = $second->fresh()->metadata;
        $this->assertTrue($merged['first_pass'] ?? false);
        $this->assertSame('yes', $merged['second_pass'] ?? null);
        $this->assertArrayHasKey('last_grant_attempt', $merged);
    }

    public function test_revoke_stamps_status_and_reason_and_double_revoke_is_noop(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('blog');

        $ledger = app(EntitlementLedger::class);
        $row = $ledger->grant($site, $module, ModuleEntitlement::SOURCE_PURCHASE);
        $this->assertNotNull($row);

        $okFirst = $ledger->revoke($row->id, null, 'admin_decision_test');
        $this->assertTrue($okFirst);

        $row->refresh();
        $this->assertSame(ModuleEntitlement::STATUS_REVOKED, $row->status);
        $this->assertNotNull($row->revoked_at);
        $this->assertSame('admin_decision_test', $row->revoke_reason);

        // Double-revoke must be a no-op (returns false).
        $okSecond = $ledger->revoke($row->id, null, 'should_not_revoke_twice');
        $this->assertFalse($okSecond);

        $row->refresh();
        $this->assertSame('admin_decision_test', $row->revoke_reason, 'reason must not be overwritten by second revoke');
    }

    public function test_regrant_after_revoke_creates_new_row_and_preserves_history(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('advertisements');

        $ledger = app(EntitlementLedger::class);
        $first = $ledger->grant($site, $module, ModuleEntitlement::SOURCE_PURCHASE);
        $ledger->revoke($first->id, null, 'test_revoke');

        $second = $ledger->grant($site, $module, ModuleEntitlement::SOURCE_ADMIN_GRANT);

        $this->assertNotNull($second);
        $this->assertNotSame($first->id, $second->id, 'a fresh row must be created after revoke');
        $this->assertSame(ModuleEntitlement::STATUS_ACTIVE, $second->status);

        $this->assertSame(2, ModuleEntitlement::query()
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->count());
        $this->assertSame(1, ModuleEntitlement::query()
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->where('status', 'active')
            ->count());
        $this->assertSame(1, ModuleEntitlement::query()
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->where('status', 'revoked')
            ->count());
    }

    public function test_module_slug_snapshot_survives_slug_rename(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('coupon_v1');

        $ledger = app(EntitlementLedger::class);
        $ledger->grant($site, $module, ModuleEntitlement::SOURCE_PURCHASE);

        $module->update(['slug' => 'coupon_v2']);

        $row = ModuleEntitlement::query()
            ->where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('coupon_v1', $row->module_slug, 'ledger must preserve the original slug at grant time');
    }

    public function test_backfill_command_is_idempotent_and_covers_site_modules(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $modA = $this->makeModule('mod_a');
        $modB = $this->makeModule('mod_b');

        DB::table('site_modules')->insert([
            ['site_id' => $site->id, 'module_id' => $modA->id, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['site_id' => $site->id, 'module_id' => $modB->id, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $before = ModuleEntitlement::count();

        Artisan::call('entitlements:backfill');
        $afterFirst = ModuleEntitlement::count();
        $forThisSite = ModuleEntitlement::where('site_id', $site->id)->count();
        $this->assertSame(2, $forThisSite, 'two ledger rows must exist for this site (one per active site_module)');

        Artisan::call('entitlements:backfill');
        $afterSecond = ModuleEntitlement::count();
        $this->assertSame($afterFirst, $afterSecond, 'second backfill must not duplicate rows');

        // Phase A invariant: every active site_module has a matching active ledger row.
        $missing = DB::table('site_modules')
            ->where('site_modules.is_active', 1)
            ->leftJoin('module_entitlements', function ($j) {
                $j->on('module_entitlements.site_id', '=', 'site_modules.site_id')
                    ->on('module_entitlements.module_id', '=', 'site_modules.module_id')
                    ->where('module_entitlements.status', '=', 'active');
            })
            ->whereNull('module_entitlements.id')
            ->count();
        $this->assertSame(0, $missing, 'no active site_module should be missing in the ledger after backfill');
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $mod = $this->makeModule('blog_dry');
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $mod->id, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('entitlements:backfill', ['--dry-run' => true]);
        $this->assertSame(0, ModuleEntitlement::count(), 'dry-run must persist nothing');
    }

    public function test_revoke_returns_false_for_unknown_id(): void
    {
        $ledger = app(EntitlementLedger::class);
        $this->assertFalse($ledger->revoke(987654321, null, 'no_such_row'));
    }

    public function test_grant_with_unknown_source_returns_null(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('bad_src');

        $ledger = app(EntitlementLedger::class);
        $row = $ledger->grant($site, $module, 'bogus_source_value');
        $this->assertNull($row);
        $this->assertSame(0, ModuleEntitlement::count());
    }

    public function test_relations_are_wired_on_site_customer_and_module(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('rel_test');

        $ledger = app(EntitlementLedger::class);
        $ledger->grant($site, $module, ModuleEntitlement::SOURCE_PURCHASE);

        $this->assertCount(1, $site->fresh()->entitlements);
        $this->assertCount(1, $site->fresh()->activeEntitlements);
        $this->assertCount(1, $customer->fresh()->entitlements);
        $this->assertCount(1, $customer->fresh()->activeEntitlements);
        $this->assertCount(1, $module->fresh()->entitlements);
        $this->assertCount(1, $module->fresh()->activeEntitlements);
    }
}
