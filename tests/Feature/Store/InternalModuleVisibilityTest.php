<?php

namespace Tests\Feature\Store;

use App\Http\Controllers\StoreController;
use App\Models\Customer;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 3-D / Commit 8 — Platform Store must never list (or accept into
 * cart) a Product whose Module is not customer-facing (internal/
 * platform-only). Source of truth: Module::scopeCustomerFacing().
 *
 * Backend-only: this invokes StoreController's actions directly and
 * inspects the returned View's data, without rendering Blade — the view
 * layer is intentionally not this security fix's responsibility (and two
 * pre-existing, unrelated Blade syntax errors in store/modules.blade.php
 * and store/index.blade.php currently prevent full HTTP/render-level
 * testing of these routes; that is a separate, already-reported issue).
 */
class InternalModuleVisibilityTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeModule(string $type): Module
    {
        return Module::create([
            'slug' => 'test-module-' . uniqid('', true),
            'title' => 'ماژول تست',
            'description' => null,
            'is_active' => true,
            'type' => $type,
        ]);
    }

    private function makeModuleAddonProduct(Module $module, string $name): Product
    {
        return Product::create([
            'sku' => 'SKU-' . uniqid('', true),
            'name_fa' => $name,
            'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 100000,
            'module_id' => $module->id,
            'is_active' => true,
        ]);
    }

    public function test_customer_facing_module_product_is_shown_in_store(): void
    {
        $this->actingAsStoreCustomer();
        $module = $this->makeModule(Module::TYPE_PAID);
        $product = $this->makeModuleAddonProduct($module, 'محصول قابل نمایش');

        $controller = app(StoreController::class);
        $view = $controller->modules(Request::create('/store/modules', 'GET'), app(CartService::class));
        $products = $view->getData()['products'];

        $this->assertTrue($products->contains('id', $product->id));
    }

    public function test_internal_module_product_is_not_shown_in_store(): void
    {
        $this->actingAsStoreCustomer();
        $module = $this->makeModule(Module::TYPE_INTERNAL);
        $product = $this->makeModuleAddonProduct($module, 'محصول داخلی مخفی');

        $controller = app(StoreController::class);
        $view = $controller->modules(Request::create('/store/modules', 'GET'), app(CartService::class));
        $products = $view->getData()['products'];

        $this->assertFalse($products->contains('id', $product->id));
    }

    public function test_normal_non_module_product_is_unaffected(): void
    {
        $this->actingAsStoreCustomer();
        $product = Product::create([
            'sku' => 'SKU-' . uniqid('', true),
            'name_fa' => 'بستهٔ پشتیبانی عادی',
            'type' => Product::TYPE_SUPPORT_PLAN,
            'price_irr' => 50000,
            'module_id' => null,
            'is_active' => true,
        ]);

        $controller = app(StoreController::class);
        $view = $controller->index(Request::create('/store', 'GET'), app(CartService::class));
        $products = $view->getData()['products'];

        $this->assertTrue($products->contains('id', $product->id));
    }
}
