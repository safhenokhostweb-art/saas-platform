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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Wave 5.2 / P0-1 — Per-site confirmation page + reason + ledger.revoke
 * on Admin\ModuleController destructive paths.
 *
 * Contract under test (refined per explicit user requirements):
 *   - When `module_type` is being switched to INTERNAL on a module that
 *     has active site_modules, the first POST does NOT mutate anything.
 *     It renders an interstitial confirmation page that lists every
 *     affected site.
 *   - On the confirmation page, sites that have a successful purchase
 *     in `site_module_purchases` OR an active row in `module_licenses`
 *     for this module MUST be pre-checked “Keep Active”. This protects
 *     paid entitlements from accidental revocation.
 *   - Only the second POST, carrying `__confirm_step=apply`, applies the
 *     change. That POST requires:
 *         * revoke_reason (min:5)
 *         * confirmed_destructive accepted
 *     and may pass a `keep_active_site_ids[]` list (a subset of the
 *     affected sites).
 *   - For each affected site that is NOT in keep_active_site_ids:
 *         * its site_modules row is flipped to is_active=false
 *         * EntitlementLedger::revokeBySiteModule is called → one
 *           ModuleEntitlement row with status=revoked, revoke_reason
 *           embedding the admin reason, revoked_by=admin id, revoked_at
 *           not null.
 *   - For each affected site that IS in keep_active_site_ids:
 *         * its site_modules row stays is_active=true
 *         * its entitlement stays ACTIVE.
 *   - The single `module.updated` activity_log row carries
 *     side_effects.revoke_reason, affected_site_ids, revoked_site_ids,
 *     kept_site_ids, site_modules_disabled_count.
 *   - When a module has NO active sites, switching to INTERNAL does NOT
 *     require destructive fields (no interstitial, no validation).
 *   - `bulkUpdate()` with set_type_internal applies the same two-step
 *     flow per (site,module) pair.
 *   - Non-destructive bulk actions (marketplace_on/off, etc.) keep
 *     working with no confirmation required.
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
     * Attach $count sites to $module with a seed ACTIVE entitlement.
     *
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
            app(EntitlementLedger::class)->grant($site, $module, ModuleEntitlement::SOURCE_SEED);
            $ids[] = $site->id;
        }
        return [$ids, $customer];
    }

    /**
     * Seed a successful purchase row to mark a site as “commercially attached”.
     * The mere presence of a row in site_module_purchases counts as proof of
     * purchase (the table only stores successful purchases — there is no
     * `status` column).
     */
    private function seedPurchase(int $siteId, int $moduleId): void
    {
        if (!Schema::hasTable('site_module_purchases')) {
            $this->markTestSkipped('site_module_purchases table not present');
        }
        DB::table('site_module_purchases')->insert([
            'site_id' => $siteId,
            'module_id' => $moduleId,
            'amount_irr' => 1000000,
            'purchased_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =====================================================================
    // update() — step 1: confirmation page
    // =====================================================================

    public function test_destructive_update_first_post_renders_confirmation_view_without_mutating(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_step1_render_view');
        [$siteIds] = $this->attachSites($module, 2);

        $response = $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                // no __confirm_step → step 1
            ]);

        $response->assertOk();
        $response->assertViewIs('admin.modules.confirm-destructive');
        $response->assertViewHas('module', fn ($m) => $m->id === $module->id);
        $response->assertViewHas('affectedSiteIds', fn ($ids) => count(array_diff($ids, $siteIds)) === 0
            && count(array_diff($siteIds, $ids)) === 0);

        // Nothing was mutated.
        $module->refresh();
        $this->assertSame(Module::TYPE_PAID, $module->type);
        $this->assertSame(
            2,
            DB::table('site_modules')->where('module_id', $module->id)->where('is_active', 1)->count(),
            'first POST must NOT disable any site_modules row'
        );
        $this->assertSame(
            0,
            ModuleEntitlement::query()
                ->where('module_id', $module->id)
                ->where('status', ModuleEntitlement::STATUS_REVOKED)
                ->count(),
            'first POST must NOT create any revoked entitlement row'
        );
        $this->assertSame(
            0,
            ActivityLog::where('action', 'module.updated')
                ->where('subject_type', Module::class)
                ->where('subject_id', $module->id)
                ->count(),
            'first POST must NOT write any audit row'
        );
    }

    public function test_confirmation_view_pre_checks_sites_with_active_purchase(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_precheck_purchase');
        [$siteIds] = $this->attachSites($module, 3);

        // Mark only site #0 as commercially attached.
        $this->seedPurchase($siteIds[0], $module->id);

        $response = $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
            ]);

        $response->assertOk();
        $response->assertViewIs('admin.modules.confirm-destructive');

        $commercial = $response->viewData('commerciallyAttachedSiteIds');
        $this->assertContains($siteIds[0], $commercial);
        $this->assertNotContains($siteIds[1], $commercial);
        $this->assertNotContains($siteIds[2], $commercial);
    }

    public function test_confirmation_view_pre_checks_sites_with_active_module_license(): void
    {
        if (!Schema::hasTable('module_licenses')) {
            $this->markTestSkipped('module_licenses table not present');
        }
        $admin = $this->admin();
        $module = $this->paidModule('w52_precheck_license');
        [$siteIds] = $this->attachSites($module, 2);

        DB::table('module_licenses')->insert([
            'site_id' => $siteIds[0],
            'module_id' => $module->id,
            'license_type' => 'one_time',
            'purchased_at' => now(),
            'status' => 'active',
            'revoked_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
            ]);

        $response->assertOk();
        $commercial = $response->viewData('commerciallyAttachedSiteIds');
        $this->assertContains($siteIds[0], $commercial);
        $this->assertNotContains($siteIds[1], $commercial);
    }

    // =====================================================================
    // update() — step 2: apply
    // =====================================================================

    public function test_apply_without_reason_returns_validation_errors(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_apply_no_reason');
        $this->attachSites($module, 2);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.edit', $module))
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                '__confirm_step' => 'apply',
                // no revoke_reason / confirmed_destructive
            ]);

        $response->assertSessionHasErrors(['revoke_reason', 'confirmed_destructive']);

        $module->refresh();
        $this->assertSame(Module::TYPE_PAID, $module->type);
        $this->assertSame(2, DB::table('site_modules')->where('module_id', $module->id)->where('is_active', 1)->count());
    }

    public function test_apply_with_short_reason_returns_validation_error(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_apply_short_reason');
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
                '__confirm_step' => 'apply',
                'revoke_reason' => 'abc',
                'confirmed_destructive' => '1',
            ]);

        $response->assertSessionHasErrors(['revoke_reason']);
        $module->refresh();
        $this->assertSame(Module::TYPE_PAID, $module->type);
    }

    public function test_apply_revokes_only_unchecked_sites_and_preserves_kept_ones(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_partial_revoke');
        [$siteIds] = $this->attachSites($module, 4);
        // Admin decides to keep sites [0,1] active and revoke sites [2,3].
        $keep = [$siteIds[0], $siteIds[1]];
        $revoke = [$siteIds[2], $siteIds[3]];

        $reason = 'بازنشستگی نوع پولی برای دو سایت آزمایشی.';

        $response = $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                '__confirm_step' => 'apply',
                'revoke_reason' => $reason,
                'confirmed_destructive' => '1',
                'keep_active_site_ids' => $keep,
            ]);

        $response->assertRedirect(route('admin.modules.index'));

        // Kept sites stay active in site_modules.
        foreach ($keep as $sid) {
            $row = DB::table('site_modules')->where('site_id', $sid)->where('module_id', $module->id)->first();
            $this->assertNotNull($row);
            $this->assertSame(1, (int) $row->is_active, "kept site $sid must remain is_active=1");
        }
        // Revoked sites flipped off, but rows not deleted.
        foreach ($revoke as $sid) {
            $row = DB::table('site_modules')->where('site_id', $sid)->where('module_id', $module->id)->first();
            $this->assertNotNull($row, "site $sid row must NOT be deleted");
            $this->assertSame(0, (int) $row->is_active, "site $sid must be is_active=0");
        }

        // ledger: kept sites stay active; revoked sites have status=revoked.
        foreach ($keep as $sid) {
            $active = ModuleEntitlement::query()
                ->where('site_id', $sid)->where('module_id', $module->id)
                ->where('status', ModuleEntitlement::STATUS_ACTIVE)->count();
            $this->assertSame(1, $active, "kept site $sid must keep one ACTIVE entitlement");
        }
        foreach ($revoke as $sid) {
            $row = ModuleEntitlement::query()
                ->where('site_id', $sid)->where('module_id', $module->id)
                ->where('status', ModuleEntitlement::STATUS_REVOKED)->first();
            $this->assertNotNull($row, "revoked site $sid must have a revoked entitlement row");
            $this->assertSame($admin->id, $row->revoked_by);
            $this->assertStringContainsString($reason, (string) $row->revoke_reason);
            $this->assertNotNull($row->revoked_at);
        }

        // Audit log records both lists.
        $log = ActivityLog::where('action', 'module.updated')
            ->where('subject_type', Module::class)
            ->where('subject_id', $module->id)
            ->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($reason, $log->properties['side_effects']['revoke_reason']);
        $this->assertEqualsCanonicalizing($siteIds, $log->properties['side_effects']['affected_site_ids']);
        $this->assertEqualsCanonicalizing($keep, $log->properties['side_effects']['kept_site_ids']);
        $this->assertEqualsCanonicalizing($revoke, $log->properties['side_effects']['revoked_site_ids']);
        $this->assertSame(count($revoke), (int) $log->properties['side_effects']['site_modules_disabled_count']);

        $module->refresh();
        $this->assertSame(Module::TYPE_INTERNAL, $module->type);
    }

    public function test_apply_with_all_sites_kept_does_not_revoke_anything(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_keep_everything');
        [$siteIds] = $this->attachSites($module, 2);

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                '__confirm_step' => 'apply',
                'revoke_reason' => 'تأیید: تمام سایت‌ها نگه داشته شوند.',
                'confirmed_destructive' => '1',
                'keep_active_site_ids' => $siteIds,
            ])
            ->assertRedirect(route('admin.modules.index'));

        // No revoked entitlement.
        $this->assertSame(0, ModuleEntitlement::query()
            ->where('module_id', $module->id)
            ->where('status', ModuleEntitlement::STATUS_REVOKED)->count());
        // All site_modules still active.
        $this->assertSame(2, DB::table('site_modules')->where('module_id', $module->id)->where('is_active', 1)->count());

        // Module type still updated to internal.
        $module->refresh();
        $this->assertSame(Module::TYPE_INTERNAL, $module->type);

        $log = ActivityLog::where('action', 'module.updated')
            ->where('subject_id', $module->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(0, (int) $log->properties['side_effects']['site_modules_disabled_count']);
        $this->assertEqualsCanonicalizing($siteIds, $log->properties['side_effects']['kept_site_ids']);
        $this->assertSame([], $log->properties['side_effects']['revoked_site_ids']);
    }

    public function test_apply_ignores_keep_ids_outside_affected_list(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_keep_ignores_strangers');
        [$siteIds] = $this->attachSites($module, 2);

        $strangerId = 999999; // not affected

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                '__confirm_step' => 'apply',
                'revoke_reason' => 'تست اینکه شناسهٔ بیرونی نادیده گرفته شود.',
                'confirmed_destructive' => '1',
                'keep_active_site_ids' => [$strangerId, $siteIds[0]], // only site[0] is valid keep
            ])
            ->assertRedirect();

        // site[0] kept, site[1] revoked.
        $this->assertSame(1, (int) DB::table('site_modules')
            ->where('site_id', $siteIds[0])->where('module_id', $module->id)->value('is_active'));
        $this->assertSame(0, (int) DB::table('site_modules')
            ->where('site_id', $siteIds[1])->where('module_id', $module->id)->value('is_active'));

        $log = ActivityLog::where('action', 'module.updated')
            ->where('subject_id', $module->id)->latest('id')->first();
        $this->assertSame([$siteIds[0]], $log->properties['side_effects']['kept_site_ids']);
        $this->assertNotContains($strangerId, $log->properties['side_effects']['kept_site_ids']);
    }

    public function test_update_to_internal_when_no_active_sites_does_not_require_confirmation(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_noop_no_active_sites');

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
            ])
            ->assertRedirect(route('admin.modules.index'));

        $module->refresh();
        $this->assertSame(Module::TYPE_INTERNAL, $module->type);
    }

    public function test_update_paid_to_license_does_not_render_confirmation(): void
    {
        $admin = $this->admin();
        $module = $this->paidModule('w52_paid_to_license');
        $this->attachSites($module, 2);

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'license',
                'marketplace_visible' => '1',
                'free_assignable' => '0',
                'license_required' => '0',
            ])
            ->assertRedirect(route('admin.modules.index'));

        // Active sites stay active.
        $this->assertSame(2, DB::table('site_modules')->where('module_id', $module->id)->where('is_active', 1)->count());
        $this->assertSame(0, ModuleEntitlement::query()
            ->where('module_id', $module->id)
            ->where('status', ModuleEntitlement::STATUS_REVOKED)->count());

        $module->refresh();
        $this->assertSame(Module::TYPE_CORE, $module->type);
    }

    // =====================================================================
    // bulkUpdate() — two-step
    // =====================================================================

    public function test_bulk_destructive_first_post_renders_confirm_bulk_view(): void
    {
        $admin = $this->admin();
        $a = $this->paidModule('w52_bulk_step1_a');
        $b = $this->paidModule('w52_bulk_step1_b');
        $this->attachSites($a, 2);
        $this->attachSites($b, 1);

        $response = $this->actingAs($admin)
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$a->id, $b->id],
                'bulk_action' => 'set_type_internal',
            ]);

        $response->assertOk();
        $response->assertViewIs('admin.modules.confirm-bulk-destructive');
        $response->assertViewHas('affectedPairs', fn ($pairs) => count($pairs) === 3);

        // No mutation.
        $a->refresh();
        $b->refresh();
        $this->assertSame(Module::TYPE_PAID, $a->type);
        $this->assertSame(Module::TYPE_PAID, $b->type);
        $this->assertSame(2, DB::table('site_modules')->where('module_id', $a->id)->where('is_active', 1)->count());
        $this->assertSame(1, DB::table('site_modules')->where('module_id', $b->id)->where('is_active', 1)->count());
    }

    public function test_bulk_apply_revokes_only_unchecked_pairs(): void
    {
        $admin = $this->admin();
        $a = $this->paidModule('w52_bulk_apply_partial_a');
        $b = $this->paidModule('w52_bulk_apply_partial_b');
        [$aSites] = $this->attachSites($a, 2); // both for A
        [$bSites] = $this->attachSites($b, 1); // one for B

        // Admin keeps A[0] active, revokes A[1] and B[0].
        $keepKey = $aSites[0] . ':' . $a->id;
        $reason = 'بازنشستگی گزینشی.';

        $this->actingAs($admin)
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$a->id, $b->id],
                'bulk_action' => 'set_type_internal',
                '__confirm_step' => 'apply',
                'revoke_reason' => $reason,
                'confirmed_destructive' => '1',
                'keep_active_pair_keys' => [$keepKey],
            ])
            ->assertRedirect(route('admin.modules.index'));

        // A[0] kept active.
        $this->assertSame(1, (int) DB::table('site_modules')
            ->where('site_id', $aSites[0])->where('module_id', $a->id)->value('is_active'));
        $this->assertSame(1, ModuleEntitlement::query()
            ->where('site_id', $aSites[0])->where('module_id', $a->id)
            ->where('status', ModuleEntitlement::STATUS_ACTIVE)->count());

        // A[1] revoked.
        $this->assertSame(0, (int) DB::table('site_modules')
            ->where('site_id', $aSites[1])->where('module_id', $a->id)->value('is_active'));
        $this->assertSame(1, ModuleEntitlement::query()
            ->where('site_id', $aSites[1])->where('module_id', $a->id)
            ->where('status', ModuleEntitlement::STATUS_REVOKED)->count());

        // B[0] revoked.
        $this->assertSame(0, (int) DB::table('site_modules')
            ->where('site_id', $bSites[0])->where('module_id', $b->id)->value('is_active'));
        $this->assertSame(1, ModuleEntitlement::query()
            ->where('site_id', $bSites[0])->where('module_id', $b->id)
            ->where('status', ModuleEntitlement::STATUS_REVOKED)->count());

        // Aggregate audit row carries the counters.
        $log = ActivityLog::where('action', 'module.bulk_updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($reason, $log->properties['side_effects']['revoke_reason']);
        $this->assertSame(2, (int) $log->properties['side_effects']['site_modules_disabled_count']);
        $this->assertSame(1, (int) $log->properties['side_effects']['site_modules_kept_active_count']);
        $this->assertSame(3, (int) $log->properties['side_effects']['affected_pairs_count']);

        // Both modules switched to internal.
        $a->refresh();
        $b->refresh();
        $this->assertSame(Module::TYPE_INTERNAL, $a->type);
        $this->assertSame(Module::TYPE_INTERNAL, $b->type);
    }

    public function test_bulk_apply_without_reason_returns_validation_errors(): void
    {
        $admin = $this->admin();
        $a = $this->paidModule('w52_bulk_apply_no_reason_a');
        $this->attachSites($a, 1);

        $this->actingAs($admin)
            ->from(route('admin.modules.index'))
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$a->id],
                'bulk_action' => 'set_type_internal',
                '__confirm_step' => 'apply',
            ])
            ->assertSessionHasErrors(['revoke_reason', 'confirmed_destructive']);

        // No mutation.
        $a->refresh();
        $this->assertSame(Module::TYPE_PAID, $a->type);
        $this->assertSame(1, DB::table('site_modules')->where('module_id', $a->id)->where('is_active', 1)->count());
    }

    public function test_bulk_non_destructive_action_still_works_without_confirmation(): void
    {
        $admin = $this->admin();
        $a = $this->paidModule('w52_bulk_marketplace_off_a');
        $b = $this->paidModule('w52_bulk_marketplace_off_b');
        $this->attachSites($a, 1);

        $this->actingAs($admin)
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$a->id, $b->id],
                'bulk_action' => 'marketplace_off',
            ])
            ->assertRedirect(route('admin.modules.index'));

        $a->refresh();
        $b->refresh();
        $this->assertFalse((bool) $a->marketplace_visible);
        $this->assertFalse((bool) $b->marketplace_visible);

        $this->assertSame(Module::TYPE_PAID, $a->type);
        $this->assertSame(1, DB::table('site_modules')->where('module_id', $a->id)->where('is_active', 1)->count());
        $this->assertSame(0, ModuleEntitlement::query()
            ->whereIn('module_id', [$a->id, $b->id])
            ->where('status', ModuleEntitlement::STATUS_REVOKED)->count());
    }

    public function test_bulk_set_type_internal_with_only_already_internal_modules_does_not_render_confirm(): void
    {
        $admin = $this->admin();
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
