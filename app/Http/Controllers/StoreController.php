<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Site;
use App\Services\CartService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * فروشگاه (خرید محصولات) — برای مشتری لاگین‌شده.
 */
class StoreController extends Controller
{
    /**
     * حذف محصولاتی که به ماژول غیر customer-facing (internal/platform-only)
     * وابسته‌اند — چه در لیست فروشگاه، چه در مسیر افزودن به سبد خرید.
     * محصولات بدون module_id (license_template, support_plan) تحت تأثیر
     * قرار نمی‌گیرند. Source of truth: Module::scopeCustomerFacing()
     * (فقط core/paid؛ internal هرگز — طبق مستندات موجود پروژه).
     */
    protected function excludeInternalModuleProducts(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('module_id')
                ->orWhereHas('module', fn (Builder $m) => $m->customerFacing());
        })->where($this->excludeBespokeMotionAssets(...));
    }

    /**
     * A motion_asset Product is a licensed, customer-specific commission
     * (Phase X/11) — it must never appear in the generic storefront, which
     * has no concept of "this product belongs to one specific customer"
     * (Products aren't customer-scoped anywhere else in this codebase).
     * It is only ever surfaced via Customer\MotionGraphicsController,
     * scoped to the caller's own site's commissions.
     */
    protected function excludeBespokeMotionAssets(Builder $q): void
    {
        $q->where('type', '!=', Product::TYPE_MOTION_ASSET);
    }

    public function index(Request $request, CartService $cart)
    {
        // پیش‌پر کردن سبد از URL (برای لینک از سایت مالک: ?add=product_id&site_id=...)
        if ($request->filled('add')) {
            $productId = (int) $request->add;
            $siteId = $request->filled('site_id') ? (int) $request->site_id : null;
            $product = $this->excludeInternalModuleProducts(Product::where('is_active', true))->find($productId);
            if ($product) {
                $needsSite = in_array($product->type, [Product::TYPE_MODULE_ADDON, Product::TYPE_SUPPORT_PLAN]);
                if (!$needsSite || $siteId) {
                    $cart->add($productId, 1, $needsSite ? $siteId : null);
                    return redirect()->route('store.cart')->with('success', 'به سبد خرید اضافه شد.');
                }
            }
        }

        $products = $this->excludeInternalModuleProducts(Product::where('is_active', true))
            ->with(['module', 'supportPlan'])
            ->orderBy('type')
            ->get();
        $reseller = auth()->user()->customer?->reseller;
        $wholesaleDiscount = $reseller && $reseller->wholesale_discount_percent > 0
            ? (float) $reseller->wholesale_discount_percent
            : 0;
        $preselectedSiteId = $request->filled('site_id') ? (int) $request->site_id : null;

        $enabledModuleIdsBySite = $this->getEnabledModuleIdsBySiteForCustomer();

        return view('store.index', compact('products', 'reseller', 'wholesaleDiscount', 'preselectedSiteId', 'enabledModuleIdsBySite'));
    }

    /**
     * فروشگاه ماژول‌ها — فقط محصولات نوع ماژول افزودنی، با UI کارتی.
     */
    public function modules(Request $request, CartService $cart)
    {
        if ($request->filled('add')) {
            $productId = (int) $request->add;
            $siteId = $request->filled('site_id') ? (int) $request->site_id : null;
            $product = Product::where('is_active', true)
                ->where('type', Product::TYPE_MODULE_ADDON)
                ->whereHas('module', fn (Builder $m) => $m->customerFacing())
                ->find($productId);
            if ($product && $product->module_id) {
                if ($siteId) {
                    $cart->add($productId, 1, $siteId);
                    return redirect()->route('store.cart')->with('success', 'ماژول به سبد خرید اضافه شد.');
                }
            }
        }

        // Deep link از Customer Site: ?module=<slug> (Phase 3-F / Commit 10-A).
        // فقط Product متناظر را برای پیش‌انتخاب در همین صفحه resolve می‌کند؛
        // site_id از URL پذیرفته نمی‌شود و به سبد خرید اضافه نمی‌کند — انتخاب
        // سایت همچنان از طریق فرم/Session موجود همین صفحه انجام می‌شود.
        $preselectedProductId = null;
        if ($request->filled('module')) {
            $module = \App\Models\Module::customerFacing()
                ->where('slug', (string) $request->input('module'))
                ->first();

            if (!$module) {
                abort(404, 'ماژول یافت نشد.');
            }

            $product = Product::where('type', Product::TYPE_MODULE_ADDON)
                ->where('is_active', true)
                ->whereHas('module', fn (Builder $m) => $m->customerFacing())
                ->where('module_id', $module->id)
                ->first();

            if (!$product) {
                return redirect()->route('store.modules')
                    ->with('error', 'این ماژول در حال حاضر برای خرید موجود نیست.');
            }

            $preselectedProductId = $product->id;
        }

        $products = Product::where('is_active', true)
            ->where('type', Product::TYPE_MODULE_ADDON)
            ->whereNotNull('module_id')
            ->whereHas('module', fn (Builder $m) => $m->customerFacing())
            ->with('module')
            ->orderBy('name_fa')
            ->get();

        $reseller = auth()->user()->customer?->reseller;
        $wholesaleDiscount = $reseller && $reseller->wholesale_discount_percent > 0
            ? (float) $reseller->wholesale_discount_percent
            : 0;
        $preselectedSiteId = $request->filled('site_id') ? (int) $request->site_id : null;

        $enabledModuleIdsBySite = $this->getEnabledModuleIdsBySiteForCustomer();

        return view('store.modules', compact('products', 'reseller', 'wholesaleDiscount', 'preselectedSiteId', 'enabledModuleIdsBySite', 'preselectedProductId'));
    }

    /**
     * برای هر سایت مشتری، آرایهٔ شناسه ماژول‌های فعال (تیک‌خورده) را برمی‌گرداند.
     *
     * @return array<int, array<int>>
     */
    protected function getEnabledModuleIdsBySiteForCustomer(): array
    {
        $customer = auth()->user()->customer;
        if (!$customer) {
            return [];
        }
        $out = [];
        foreach ($customer->sites as $site) {
            $out[$site->id] = $site->modules()->wherePivot('is_active', true)->pluck('modules.id')->all();
        }
        return $out;
    }

    public function cart(CartService $cart)
    {
        $details = $cart->details();
        $customer = auth()->user()->customer ?? null;
        $sites = $customer ? $customer->sites : collect();
        return view('store.cart', array_merge($details, ['sites' => $sites]));
    }

    public function addToCart(Request $request, CartService $cart)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'site_id' => 'nullable|exists:sites,id',
        ]);

        $product = $this->excludeInternalModuleProducts(Product::where('is_active', true))
            ->findOrFail($request->product_id);
        $siteId = $request->site_id;
        if (in_array($product->type, [Product::TYPE_MODULE_ADDON, Product::TYPE_SUPPORT_PLAN]) && !$siteId) {
            return back()->with('error', 'برای این محصول باید یک سایت انتخاب کنید.');
        }

        if ($product->type === Product::TYPE_MODULE_ADDON && $product->module_id && $siteId) {
            $site = \App\Models\Site::find($siteId);
            if ($site && auth()->user()->customer && $site->customer_id === auth()->user()->customer->id) {
                $alreadyEnabled = $site->modules()->wherePivot('is_active', true)->where('modules.id', $product->module_id)->exists();
                if ($alreadyEnabled) {
                    return back()->with('error', 'این ماژول برای سایت انتخاب‌شده قبلاً خریداری شده است.');
                }
            }
        }

        $cart->add(
            (int) $request->product_id,
            (int) ($request->quantity ?? 1),
            $siteId ? (int) $siteId : null
        );

        return redirect()->route('store.cart')->with('success', 'به سبد خرید اضافه شد.');
    }

    public function removeFromCart(string $key, CartService $cart)
    {
        $cart->remove($key);
        return redirect()->route('store.cart')->with('success', 'از سبد حذف شد.');
    }
}
