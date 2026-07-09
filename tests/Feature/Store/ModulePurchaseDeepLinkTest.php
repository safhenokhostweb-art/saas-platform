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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Phase 3-F / Commit 10-A — /store/modules?module=<slug> deep-link
 * resolution. Reuses Module::scopeCustomerFacing() for both the Module
 * lookup and the Product lookup (no independent internal-exclusion
 * query). Never accepts site_id from this deep link and never adds to
 * cart directly — only pre-selects a Product for the existing page.
 *
 * Backend-only: invokes StoreController::modules() directly and
 * inspects the returned response, without rendering Blade (same
 * approach as Commit 8's InternalModuleVisibilityTest).
 */
class ModulePurchaseDeepLinkTest extends TestCase
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

    private function makeModuleAddonProduct(Module $module): Product
    {
        return Product::create([
            'sku' => 'SKU-' . uniqid('', true),
            'name_fa' => 'محصول ماژول تست',
            'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 100000,
            'module_id' => $module->id,
            'is_active' => true,
        ]);
    }

    private function callModules(string $moduleSlug)
    {
        $controller = app(StoreController::class);
        $request = Request::create('/store/modules', 'GET', ['module' => $moduleSlug]);

        return $controller->modules($request, app(CartService::class));
    }

    public function test_valid_customer_facing_module_resolves_correct_product(): void
    {
        $this->actingAsStoreCustomer();
        $module = $this->makeModule(Module::TYPE_PAID);
        $product = $this->makeModuleAddonProduct($module);

        $view = $this->callModules($module->slug);

        $this->assertSame($product->id, $view->getData()['preselectedProductId']);
    }

    public function test_internal_module_is_not_purchasable(): void
    {
        $this->actingAsStoreCustomer();
        $module = $this->makeModule(Module::TYPE_INTERNAL);
        $this->makeModuleAddonProduct($module);

        $this->expectException(NotFoundHttpException::class);

        $this->callModules($module->slug);
    }

    public function test_unknown_module_slug_returns_not_found(): void
    {
        $this->actingAsStoreCustomer();

        $this->expectException(NotFoundHttpException::class);

        $this->callModules('unknown-module-slug-' . uniqid('', true));
    }

    public function test_module_without_product_cannot_be_purchased(): void
    {
        $this->actingAsStoreCustomer();
        $module = $this->makeModule(Module::TYPE_PAID);
        // No Product row created for this module.

        $response = $this->callModules($module->slug);

        $this->assertTrue($response->isRedirect(route('store.modules')));
        $this->assertSame(
            'این ماژول در حال حاضر برای خرید موجود نیست.',
            session('error')
        );
    }
}
