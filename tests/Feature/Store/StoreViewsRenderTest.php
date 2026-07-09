<?php

namespace Tests\Feature\Store;

use App\Models\Customer;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3-D / Commit 9 — store/modules.blade.php and store/index.blade.php
 * must compile and render without a Blade/PHP syntax error (unbalanced
 * @php/@endphp, stray parenthesis, duplicated dead markup fixed in this
 * commit). This is a render-only smoke test, not a business-logic test.
 */
class StoreViewsRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // /store/* routes run through DetectResellerFromDomain, which calls
        // Cache::has(). config/cache.php reads CACHE_STORE, not the
        // CACHE_DRIVER override in phpunit.xml, so the default store
        // resolves to Redis (the real .env value) instead of the intended
        // array store, and Redis is not running in this test environment.
        // Unrelated to view rendering, so the store is corrected directly.
        config(['cache.default' => 'array']);
    }

    private function actingAsStoreCustomer(): User
    {
        $user = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        Customer::create([
            'user_id' => $user->id,
            'name' => 'تست مشتری فروشگاه',
            'mobile' => $user->mobile,
            'email' => 'store_' . uniqid('', true) . '@example.com',
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_store_modules_page_renders_successfully(): void
    {
        $this->actingAsStoreCustomer();
        $module = Module::create([
            'slug' => 'test-module-' . uniqid('', true),
            'title' => 'ماژول تست',
            'description' => null,
            'is_active' => true,
            'type' => Module::TYPE_PAID,
        ]);
        Product::create([
            'sku' => 'SKU-' . uniqid('', true),
            'name_fa' => 'محصول ماژول تست',
            'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 100000,
            'module_id' => $module->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('store.modules'));

        $response->assertOk();
    }

    public function test_store_index_page_renders_successfully(): void
    {
        $this->actingAsStoreCustomer();
        Product::create([
            'sku' => 'SKU-' . uniqid('', true),
            'name_fa' => 'بستهٔ پشتیبانی تست',
            'type' => Product::TYPE_SUPPORT_PLAN,
            'price_irr' => 50000,
            'module_id' => null,
            'is_active' => true,
        ]);

        $response = $this->get(route('store.index'));

        $response->assertOk();
    }
}
