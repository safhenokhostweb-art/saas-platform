<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Wave 5.4 — Regression tests for Wave 3 / P1-3 (ModuleController audit logging)
 * and Wave 2 / P1-4 (password hashed cast on User).
 *
 * P1-3 — Admin\ModuleController audit logging:
 *   - PUT admin.modules.update writes an `activity_logs` row with
 *     action='module.updated' AND `changes` diff containing only the
 *     fields that actually changed.
 *   - When module type switches to internal, the audit row carries
 *     `side_effects.site_modules_disabled_count` AND
 *     `side_effects.reason='type_switched_to_internal'`.
 *   - POST admin.modules.bulk-update writes a single aggregate audit row
 *     with action='module.bulk_updated', the action label, module ids and
 *     count; side effects are aggregated correctly.
 *
 * P1-4 — User password hashed cast:
 *   - Setting `password = 'plaintext'` on the User model writes the
 *     BCrypt-hashed value to the DB (not the plaintext).
 *   - Re-saving an already-hashed value does NOT double-hash it
 *     (Hash::needsRehash short-circuit; legacy users keep working).
 *   - Hash::check still validates against the stored value end-to-end.
 */
class Wave3AuditAndCastsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => 'Secret123!',
            'is_admin' => true,
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
                'price_irr' => 1000000,
                'marketplace_visible' => true,
                'free_assignable' => false,
                'license_required' => false,
                'license_type_default' => 'one_time',
            ]
        );
    }

    // =====================================================================
    // P1-3 — ModuleController::update audit logging
    // =====================================================================

    public function test_module_update_writes_module_updated_activity_log_with_diff(): void
    {
        $admin = $this->makeAdmin();
        $module = $this->makeModule('p13_audit_basic');

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => 'ماژول جدید',
                'price_irr' => 2500000,
                'license_type_default' => 'one_time',
                'module_type' => 'paid',
                'marketplace_visible' => '1',
                'free_assignable' => '0',
                'license_required' => '0',
            ])
            ->assertRedirect(route('admin.modules.index'));

        $log = ActivityLog::where('action', 'module.updated')
            ->where('subject_type', Module::class)
            ->where('subject_id', $module->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'activity_logs must contain a module.updated row');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertArrayHasKey('changes', $log->properties);
        $this->assertArrayHasKey('title', $log->properties['changes']);
        $this->assertSame('ماژول جدید', $log->properties['changes']['title']['new']);
        $this->assertSame(2500000, (int) $log->properties['changes']['price_irr']['new']);
    }

    public function test_module_update_records_side_effect_when_switching_to_internal(): void
    {
        $admin = $this->makeAdmin();
        $module = $this->makeModule('p13_audit_internal_sideeffect', Module::TYPE_PAID);

        // Attach two active sites to the module to produce a side-effect count of 2.
        $customer = \App\Models\Customer::create([
            'name' => 'مشتری',
            'mobile' => '09120000001',
            'email' => 'audit1_' . uniqid() . '@example.com',
        ]);
        $siteA = \App\Models\Site::create([
            'customer_id' => $customer->id,
            'title' => 'A',
            'domain' => 'a-' . uniqid() . '.test',
            'is_active' => true,
        ]);
        $siteB = \App\Models\Site::create([
            'customer_id' => $customer->id,
            'title' => 'B',
            'domain' => 'b-' . uniqid() . '.test',
            'is_active' => true,
        ]);
        DB::table('site_modules')->insert([
            ['site_id' => $siteA->id, 'module_id' => $module->id, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['site_id' => $siteB->id, 'module_id' => $module->id, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Wave 5.2 / P0-1 (revised): destructive type switch with active sites
        // now uses a two-step flow. To actually APPLY the change in one test
        // call, we send __confirm_step=apply with the destructive fields. The
        // original P1-3 side-effects keys (count + reason) remain in the
        // audit row.
        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'license_type_default' => 'one_time',
                'module_type' => 'internal',
                'marketplace_visible' => '0',
                'free_assignable' => '0',
                'license_required' => '0',
                '__confirm_step' => 'apply',
                'revoke_reason' => 'P1-3 audit side-effect verification reason',
                'confirmed_destructive' => '1',
            ])
            ->assertRedirect();

        $log = ActivityLog::where('action', 'module.updated')
            ->where('subject_type', Module::class)
            ->where('subject_id', $module->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('side_effects', $log->properties);
        $this->assertSame(2, (int) $log->properties['side_effects']['site_modules_disabled_count']);
        $this->assertSame('type_switched_to_internal', $log->properties['side_effects']['reason']);

        // Verify the actual side-effect happened: site_modules rows for this module are now is_active=0.
        $remainingActive = DB::table('site_modules')
            ->where('module_id', $module->id)
            ->where('is_active', 1)
            ->count();
        $this->assertSame(0, $remainingActive);
    }

    public function test_module_update_does_not_write_log_when_nothing_changed(): void
    {
        $admin = $this->makeAdmin();
        $module = $this->makeModule('p13_audit_noop');

        $countBefore = ActivityLog::where('action', 'module.updated')->count();

        // submit the same exact values as currently stored
        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), [
                'title' => $module->title,
                'price_irr' => (int) $module->price_irr,
                'license_type_default' => $module->license_type_default ?: 'one_time',
                'module_type' => 'paid',
                'marketplace_visible' => '1',
                'free_assignable' => '0',
                'license_required' => '0',
            ])
            ->assertRedirect();

        $countAfter = ActivityLog::where('action', 'module.updated')->count();
        $this->assertSame($countBefore, $countAfter, 'no-op submit should produce no audit log row');
    }

    public function test_bulk_update_writes_aggregate_audit_row(): void
    {
        $admin = $this->makeAdmin();
        $a = $this->makeModule('p13_bulk_a', Module::TYPE_PAID);
        $b = $this->makeModule('p13_bulk_b', Module::TYPE_PAID);

        $this->actingAs($admin)
            ->post(route('admin.modules.bulk-update'), [
                'module_ids' => [$a->id, $b->id],
                'bulk_action' => 'marketplace_off',
            ])
            ->assertRedirect();

        $log = ActivityLog::where('action', 'module.bulk_updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'bulk update must produce a module.bulk_updated audit row');
        $this->assertSame('marketplace_off', $log->properties['bulk_action']);
        $this->assertSame(2, (int) $log->properties['count']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $log->properties['module_ids']);
    }

    public function test_module_updated_action_label_is_persian(): void
    {
        $this->assertSame('ویرایش ماژول', ActivityLog::actionLabel('module.updated'));
        $this->assertSame('بروزرسانی گروهی ماژول‌ها', ActivityLog::actionLabel('module.bulk_updated'));
    }

    // =====================================================================
    // P1-4 — User password hashed cast (Bcrypt + needsRehash)
    // =====================================================================

    public function test_creating_user_stores_password_as_bcrypt_hash_not_plaintext(): void
    {
        $plain = 'MyVerySecret#42';
        $user = User::create([
            'mobile' => '09120000099',
            'email' => 'p14_' . uniqid() . '@example.com',
            'password' => $plain,
        ]);

        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertNotSame($plain, $stored, 'plaintext password must NOT be stored');
        $this->assertStringStartsWith('$2y$', $stored, 'password must be stored as BCrypt hash');
        $this->assertTrue(Hash::check($plain, $stored));
    }

    public function test_assigning_already_hashed_value_is_not_rehashed_again(): void
    {
        $plain = 'AnotherSecret#7';
        $alreadyHashed = Hash::make($plain);

        $user = User::create([
            'mobile' => '09120000098',
            'email' => 'p14_' . uniqid() . '@example.com',
            'password' => $alreadyHashed,
        ]);

        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertSame($alreadyHashed, $stored, 'an already-hashed value must NOT be hashed a second time');
        $this->assertTrue(Hash::check($plain, $stored));
    }

    public function test_login_succeeds_with_legacy_hashed_password(): void
    {
        // Simulate an existing legacy user whose password is already bcrypt-hashed.
        $plain = 'LegacyPassword!1';
        $legacyHash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]);
        $user = User::create([
            'mobile' => '09120000097',
            'email' => 'p14_legacy_' . uniqid() . '@example.com',
            'password' => $legacyHash,
        ]);

        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertSame($legacyHash, $stored, 'legacy bcrypt hash must be preserved verbatim');
        $this->assertTrue(Hash::check($plain, $stored), 'legacy user must still be able to authenticate');
    }
}
