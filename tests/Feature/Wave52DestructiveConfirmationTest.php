<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\Site;
use App\Models\User;
use App\Services\EntitlementLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wave 5.2 / P0-1 — Confirmation + reason + ledger.revoke on
 * Admin\ModuleController destructive paths.
 *
 * Behaviour under test:
 *   - update() with module_type=internal on a module that has active
 *     site_modules MUST require revoke_reason (min:5) AND
 *     confirmed_destructive=accepted. Missing either rejects the request
 *     with validation errors (no mutation occurs, no audit row, no
 *     ledger revoke).
 *   - When both are supplied, the controller:
 *       (a) flips every active site_modules row to is_active=0
 *       (b) writes ONE module.updated activity_log row with
 *           side_effects.revoke_reason + side_effects.affected_site_ids
 *           + side_effects.site_modules_disabled_count
 *       (c) writes ONE revoked ModuleEntitlement row per site×module
 *           pair with status=revoked, revoke_reason carrying the admin
 *           reason, revoked_by=admin id.
 *   - Switching a module that has NO active sites does NOT require the
 *     destructive fields (backwards compatibility for non-destructive
 *     type flips).
 *   - bulkUpdate() set_type_internal with active sites enforces the
 *     same validation + writes ledger revokes for every affected pair.
 *   - Non-destructive bulk actions (marketplace_on/off, etc.) keep
 *     working with no reason required.
 */
class Wave52DestructiveConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'w52admin_' . uniqid() . '@example.com',
            'password' => 'Secret123!',
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    private function paidModule(string $slug): Module
    {
        return Module::firstOrCreate(
            ['slug' => $slug],
            [
                'title' => 'ماژول ' . $slug,
                'type' => Module::TYPE_PAID,
                'is_active' => true,
                'price_irr' => 1000000,
                'marketplace_visible' => true,
                'free_assignable' => false,
                'license_required' => false,
                'license_type_default' => 'one_time',
            ]
        );
    }

    /**
     * @param  int  $count  number of sites attached
     * @return array{0: array<int>, 1: Customer}
     */
    private function attachSites(Module $module, int $count): array
    {
        $customer = Customer::create([
            'name' => 'مشتری w52',
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'w52c_' . uniqid() . '@example.com',
        ]);
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $site = Site::create([
                'customer_id' => $customer->id,
                'title' => 'S' . $i,
                'domain' => 's' . $i . '-' . uniqid() . '.test',
                'is_active' => true,
            ]);
            DB::table('site_modules')->insert([
                'site_id' => $site->id,
                'module_id' => $module->id,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Seed an active entitlement so we can verify the ledger revoke.
            app(EntitlementLedger::class)->grant($site, $module, ModuleEntitlement::SOURCE_SEED);
            $ids[] = $site->id;
        }
        return [$ids, $customer];
    }

    // =====================================================================
    // update() — destructive type switch
    // =====================================================================

    public function test_update_to_internal_rejects_request_without_revoke_reason_when_active_sites_exist(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_block_no_reason');
        [$siteIds] = $this->attachSites($module, 2);

        $payload = [
            'title' => $module->title,
            'license_type_default' => 'one_time',
            'module_type' => 'internal',
            'marketplace_visible' => '0',
            'free_assignable' => '0',
            'license_required' => '0',
            // intentionally omit revoke_reason + confirmed_destructive
        ];

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.edit', $module))
            ->put(route('admin.modules.update', $module), $payload);

        $response->assertSessionHasErrors(['revoke_reason', 'confirmed_destructive']);

        // No row was disabled, no audit row, no ledger revoke.
        $stillActive = DB::table('site_modules')
            ->where('module_id', $module->id)
            ->where('is_active', 1)
            ->count();
        $this->assertSame(2, $stillActive);

        $this->assertSame(0, ActivityLog::where('action', 'module.updated')
            ->where('subject_type', Module::class)
            ->where('subject_id', $module->id)
            ->count());

        $this->assertSame(0, ModuleEntitlement::query()
            ->whereIn('site_id', $siteIds)
            ->where('module_id', $module->id)
            ->where('status', ModuleEntitlement::STATUS_REVOKED)
            ->count());

        // Module type was NOT changed either.
        $module->refresh();
        $this->assertSame(Module::TYPE_PAID, $module->type);
    }

    public function test_update_to_internal_rejects_short_revoke_reason(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_block_short_reason');
        $this->attachSites($module, 1);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.edit', $module))
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                'revoke_reason' => 'abc', // < min:5
                'confirmed_destructive' => '1',
            ]);

        $response->assertSessionHasErrors(['revoke_reason']);
        $module->refresh();
        $this->assertSame(Module::TYPE_PAID, $module->type);
    }

    public function test_update_to_internal_rejects_without_confirmation_checkbox(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_block_no_confirm');
        $this->attachSites($module, 1);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.edit', $module))
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                'revoke_reason' => 'reason that is long enough',
                // confirmed_destructive omitted
            ]);

        $response->assertSessionHasErrors(['confirmed_destructive']);
    }

    public function test_update_to_internal_with_valid_reason_disables_sites_and_revokes_ledger(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_success_with_reason');
        [$siteIds] = $this->attachSites($module, 3);

        $reason = 'ماژول از کاتالوگ نسخهٔ بعدی حذف می‌شود.';

        $response = $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                'revoke_reason' => $reason,
                'confirmed_destructive' => '1',
            ])
            ->assertRedirect(route('admin.modules.index'));

        // (a) all site_modules rows flipped to is_active=0; no row deleted.
        $stillActive = DB::table('site_modules')
            ->where('module_id', $module->id)
            ->where('is_active', 1)
            ->count();
        $this->assertSame(0, $stillActive, 'every active site_modules row must be disabled');

        $totalRows = DB::table('site_modules')
            ->where('module_id', $module->id)
            ->count();
        $this->assertSame(3, $totalRows, 'no site_modules row may be deleted by P0-1');

        // (b) audit log row carries reason + affected_site_ids + count.
        $log = ActivityLog::where('action', 'module.updated')
            ->where('subject_type', Module::class)
            ->where('subject_id', $module->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertArrayHasKey('side_effects', $log->properties);
        $this->assertSame($reason, $log->properties['side_effects']['revoke_reason']);
        $this->assertSame(3, (int) $log->properties['side_effects']['site_modules_disabled_count']);
        $this->assertEqualsCanonicalizing($siteIds, $log->properties['side_effects']['affected_site_ids']);

        // (c) one revoked entitlement per affected pair; reason embedded; revoked_by=admin.
        foreach ($siteIds as $siteId) {
            $row = ModuleEntitlement::query()
                ->where('site_id', $siteId)
                ->where('module_id', $module->id)
                ->where('status', ModuleEntitlement::STATUS_REVOKED)
                ->first();
            $this->assertNotNull($row, "site $siteId must have a revoked entitlement row");
            $this->assertSame($admin->id, $row->revoked_by);
            $this->assertStringContainsString($reason, (string) $row->revoke_reason);
            $this->assertNotNull($row->revoked_at);
        }

        // No active entitlement remains.
        $this->assertSame(0, ModuleEntitlement::query()
            ->where('module_id', $module->id)
            ->where('status', ModuleEntitlement::STATUS_ACTIVE)
            ->count());

        $module->refresh();
        $this->assertSame(Module::TYPE_INTERNAL, $module->type);
    }

    public function test_update_to_internal_when_no_active_sites_does_not_require_destructive_fields(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_noop_no_active_sites');
        // No sites attached.

        $response = $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                // no revoke_reason, no confirmed_destructive
            ])
            ->assertRedirect(route('admin.modules.index'));

        $module->refresh();
        $this->assertSame(Module::TYPE_INTERNAL, $module->type);
    }

    public function test_update_paid_to_license_does_not_require_destructive_fields(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_paid_to_license');
        $this->attachSites($module, 2);

        $response = $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'license', // paid → license (NOT destructive)
                'marketplace_visible' => '1',
                'free_assignable' => '0',
                'license_required' => '0',
            ])
            ->assertRedirect(route('admin.modules.index'));

        // Active sites stay active.
        $stillActive = DB::table('site_modules')
            ->where('module_id', $module->id)
            ->where('is_active', 1)
            ->count();
        $this->assertSame(2, $stillActive);

        // No revoked entitlement was created.
        $this->assertSame(0, ModuleEntitlement::query()
            ->where('module_id', $module->id)
            ->where('status', ModuleEntitlement::STATUS_REVOKED)
            ->count());

        $module->refresh();
        $this->assertSame(Module::TYPE_CORE, $module->type);
    }

    // =====================================================================
    // bulkUpdate() — destructive bulk
    // =====================================================================

    public function test_bulk_set_type_internal_requires_revoke_reason_when_any_module_has_active_sites(): void
    {
        $admin = $this->admin();
        $a = $this->paidModule('w52_bulk_a_active');
        $b = $this->paidModule('w52_bulk_b_active');
        $this->attachSites($a, 2);
        $this->attachSites($b, 1);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.index'))
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$a->id, $b->id],
                'bulk_action' => 'set_type_internal',
                // no revoke_reason / confirmed_destructive
            ]);

        $response->assertSessionHasErrors(['revoke_reason', 'confirmed_destructive']);

        $aActive = DB::table('site_modules')->where('module_id', $a->id)->where('is_active', 1)->count();
        $bActive = DB::table('site_modules')->where('module_id', $b->id)->where('is_active', 1)->count();
        $this->assertSame(2, $aActive);
        $this->assertSame(1, $bActive);

        $a->refresh();
        $b->refresh();
        $this->assertSame(Module::TYPE_PAID, $a->type);
        $this->assertSame(Module::TYPE_PAID, $b->type);
    }

    public function test_bulk_set_type_internal_with_reason_revokes_ledger_for_each_pair_and_logs_aggregate(): void
    {
        $admin = $this->admin();
        $a = $this->paidModule('w52_bulk_a_ok');
        $b = $this->paidModule('w52_bulk_b_ok');
        [$aSites] = $this->attachSites($a, 2);
        [$bSites] = $this->attachSites($b, 1);

        $reason = 'بازنشستگی گروهی ماژول‌های قدیمی.';

        $this->actingAs($admin)
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$a->id, $b->id],
                'bulk_action' => 'set_type_internal',
                'revoke_reason' => $reason,
                'confirmed_destructive' => '1',
            ])
            ->assertRedirect(route('admin.modules.index'));

        // All site_modules rows flipped off, but none deleted.
        $this->assertSame(0, DB::table('site_modules')->where('module_id', $a->id)->where('is_active', 1)->count());
        $this->assertSame(0, DB::table('site_modules')->where('module_id', $b->id)->where('is_active', 1)->count());
        $this->assertSame(2, DB::table('site_modules')->where('module_id', $a->id)->count());
        $this->assertSame(1, DB::table('site_modules')->where('module_id', $b->id)->count());

        // Aggregate audit row.
        $log = ActivityLog::where('action', 'module.bulk_updated')
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('set_type_internal', $log->properties['bulk_action']);
        $this->assertSame(2, (int) $log->properties['count']);
        $this->assertSame($reason, $log->properties['side_effects']['revoke_reason']);
        $this->assertSame(3, (int) $log->properties['side_effects']['affected_pairs_count']);
        $this->assertSame(3, (int) $log->properties['side_effects']['site_modules_disabled_count']);

        // One revoked entitlement per pair for module A.
        foreach ($aSites as $sid) {
            $row = ModuleEntitlement::query()
                ->where('site_id', $sid)->where('module_id', $a->id)
                ->where('status', ModuleEntitlement::STATUS_REVOKED)->first();
            $this->assertNotNull($row, "A: site $sid revoked entitlement missing");
            $this->assertStringContainsString($reason, (string) $row->revoke_reason);
            $this->assertSame($admin->id, $row->revoked_by);
        }
        foreach ($bSites as $sid) {
            $row = ModuleEntitlement::query()
                ->where('site_id', $sid)->where('module_id', $b->id)
                ->where('status', ModuleEntitlement::STATUS_REVOKED)->first();
            $this->assertNotNull($row, "B: site $sid revoked entitlement missing");
        }

        $a->refresh();
        $b->refresh();
        $this->assertSame(Module::TYPE_INTERNAL, $a->type);
        $this->assertSame(Module::TYPE_INTERNAL, $b->type);
    }

    public function test_bulk_non_destructive_action_does_not_require_reason(): void
    {
        $admin = $this->admin();
        $a = $this->paidModule('w52_bulk_marketplace_off_a');
        $b = $this->paidModule('w52_bulk_marketplace_off_b');
        $this->attachSites($a, 1);

        $this->actingAs($admin)
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$a->id, $b->id],
                'bulk_action' => 'marketplace_off',
                // no reason needed
            ])
            ->assertRedirect(route('admin.modules.index'));

        $a->refresh();
        $b->refresh();
        $this->assertFalse((bool) $a->marketplace_visible);
        $this->assertFalse((bool) $b->marketplace_visible);

        // Still paid (no type change), still has active sites.
        $this->assertSame(Module::TYPE_PAID, $a->type);
        $this->assertSame(1, DB::table('site_modules')->where('module_id', $a->id)->where('is_active', 1)->count());

        // No revoked entitlements created.
        $this->assertSame(0, ModuleEntitlement::query()
            ->whereIn('module_id', [$a->id, $b->id])
            ->where('status', ModuleEntitlement::STATUS_REVOKED)->count());
    }

    public function test_bulk_set_type_internal_with_only_already_internal_modules_does_not_require_reason(): void
    {
        $admin = $this->admin();
        // Module is ALREADY internal — no destructive transition.
        $alreadyInternal = Module::firstOrCreate(
            ['slug' => 'w52_already_internal'],
            ['title' => 'ماژول داخلی', 'type' => Module::TYPE_INTERNAL, 'is_active' => true]
        );

        $this->actingAs($admin)
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$alreadyInternal->id],
                'bulk_action' => 'set_type_internal',
            ])
            ->assertRedirect(route('admin.modules.index'));

        $alreadyInternal->refresh();
        $this->assertSame(Module::TYPE_INTERNAL, $alreadyInternal->type);
    }
}
