<?php

namespace Tests\Feature\Admin;

use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 / Commit 11-B — a `module_addon` Product must point at a module
 * that is actually sellable in the marketplace (Module::paidMarketplace()).
 * Internal / core / license_only modules must be rejected at the Admin
 * Product form, not just relied upon downstream in the purchase flow.
 */
class ProductModuleAssignmentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml sets the legacy CACHE_DRIVER env var, but config/cache.php
        // reads CACHE_STORE, so tests otherwise fall back to the app's real
        // (unavailable in this environment) Redis cache via DetectResellerFromDomain
        // middleware. Forcing the array store here is test-local only — it does
        // not touch phpunit.xml, .env, or any application file.
        config(['cache.default' => 'array']);
    }

    private function actingAsAdmin(): User
    {
        // User::factory() defaults still assign an 'email' attribute that no
        // longer exists on the users table (see the SMS/mobile-only removal
        // migration); constructing the row directly avoids that unrelated,
        // pre-existing factory/schema mismatch.
        $admin = User::create([
            'mobile' => '09' . random_int(100000000, 999999999),
            'password' => 'password',
            'is_admin' => true,
        ]);
        $this->actingAs($admin);
        return $admin;
    }

    private function baseProductPayload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'sku-' . uniqid(),
            'name_fa' => 'محصول تست',
            'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 100000,
            'is_one_time' => '1',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_module_addon_with_paid_marketplace_module_succeeds(): void
    {
        $this->actingAsAdmin();

        $module = Module::create([
            'slug' => 'test-crm-' . uniqid(),
            'title' => 'مخاطبین و CRM',
            'type' => Module::TYPE_PAID,
            'is_active' => true,
            'price_irr' => 150000,
            'license_required' => false,
            'marketplace_visible' => true,
        ]);

        $response = $this->post(route('admin.products.store'), $this->baseProductPayload([
            'module_id' => $module->id,
        ]));

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', [
            'type' => Product::TYPE_MODULE_ADDON,
            'module_id' => $module->id,
        ]);
    }

    public function test_module_addon_with_internal_module_fails_validation(): void
    {
        $this->actingAsAdmin();

        $module = Module::create([
            'slug' => 'test-saas-management-' . uniqid(),
            'title' => 'مدیریت SaaS (داخلی)',
            'type' => Module::TYPE_INTERNAL,
            'is_active' => true,
        ]);

        $response = $this->post(route('admin.products.store'), $this->baseProductPayload([
            'module_id' => $module->id,
        ]));

        $response->assertSessionHasErrors('module_id');
        $this->assertDatabaseMissing('products', [
            'module_id' => $module->id,
        ]);
    }

    public function test_module_addon_with_license_only_module_fails_validation(): void
    {
        $this->actingAsAdmin();

        $module = Module::create([
            'slug' => 'test-license-management-' . uniqid(),
            'title' => 'مدیریت لایسنس',
            'type' => Module::TYPE_PAID,
            'is_active' => true,
            'price_irr' => 200000,
            'license_required' => true,
            'marketplace_visible' => true,
        ]);

        $response = $this->post(route('admin.products.store'), $this->baseProductPayload([
            'module_id' => $module->id,
        ]));

        $response->assertSessionHasErrors('module_id');
        $this->assertDatabaseMissing('products', [
            'module_id' => $module->id,
        ]);
    }

    public function test_non_module_addon_product_is_unaffected(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.products.store'), $this->baseProductPayload([
            'type' => Product::TYPE_LICENSE_TEMPLATE,
            'module_id' => null,
        ]));

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', [
            'type' => Product::TYPE_LICENSE_TEMPLATE,
        ]);
    }
}
