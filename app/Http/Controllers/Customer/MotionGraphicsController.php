<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Site;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Services\MotionBespokeCommissionService;
use App\Modules\MotionGraphics\Services\MotionBespokeCustomerRuntimeService;
use App\Services\CartService;
use App\Services\ModuleLicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

/**
 * Phase 11 — customer-facing Motion Graphics section. Every read here goes
 * through the same Phase X services the tenant.api channel already uses
 * (MotionBespokeCustomerRuntimeService) — no parallel read path. Every
 * write (request/purchase) is scoped to a site this authenticated
 * customer actually owns, verified in this controller BEFORE any service
 * call, on top of the tenant-isolation check every motion.* call already
 * performs internally (see MotionAuthorizationAdapter::authorize()).
 *
 * Never renders Master Asset content, AI metadata, source definitions, or
 * internal version history — this controller never reads any of those; it
 * only ever calls read contracts that are structurally incapable of
 * returning them (see docs/motion-custom-assets/AUDIT_AND_ARCHITECTURE.md).
 */
class MotionGraphicsController extends Controller
{
    public function __construct(
        private readonly TenantContextContract $tenantContext,
        private readonly MotionBespokeCustomerRuntimeService $runtime,
        private readonly MotionBespokeCommissionService $commissions,
        private readonly ModuleLicenseService $licenses,
    ) {}

    public function index(Request $request)
    {
        $customer = auth()->user()->customer;
        $sites = $customer ? $customer->sites : collect();

        if ($sites->isEmpty()) {
            return view('customer.motion-graphics.index', [
                'sites' => $sites,
                'site' => null,
            ]);
        }

        $site = $this->resolveOwnedSite($request->integer('site') ?: null, $sites) ?? $sites->first();

        $hasModuleLicense = $this->licenses->hasValidLicenseBySlug($site, 'motion-graphics');
        $commissionRows = $this->runtime->commissionsStatusForSite($site);

        $runtimeAssets = [];
        $runtimeUnavailableReason = null;
        try {
            $entries = $this->runtime->bespokeAssetsForSite($site, $this->contextFor($site));
            $runtimeAssets = array_map(fn ($e) => $e->toArray(), $entries);
        } catch (MotionAdapterFailClosedException $e) {
            $runtimeUnavailableReason = $e->failureCode;
        }

        return view('customer.motion-graphics.index', [
            'sites' => $sites,
            'site' => $site,
            'hasModuleLicense' => $hasModuleLicense,
            'commissionRows' => $commissionRows,
            'runtimeAssets' => $runtimeAssets,
            'runtimeUnavailableReason' => $runtimeUnavailableReason,
        ]);
    }

    public function requestMotion(Request $request)
    {
        $data = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string|max:2000',
        ]);

        $customer = auth()->user()->customer;
        $site = $customer ? $this->resolveOwnedSite((int) $data['site_id'], $customer->sites) : null;
        if ($site === null) {
            return back()->with('error', 'سایت انتخاب‌شده متعلق به شما نیست.');
        }

        try {
            $this->commissions->requestFromCustomer($site, $data['title'], $data['description'] ?? null, $this->contextFor($site));
        } catch (MotionAdapterFailClosedException $e) {
            return back()->with('error', 'درخواست ثبت نشد: '.$e->getMessage());
        }

        return back()->with('success', 'درخواست موشن سفارشی شما ثبت شد. تیم ما به‌زودی بررسی می‌کند.');
    }

    public function purchase(Request $request, MotionBespokeCommission $commission, CartService $cart)
    {
        $customer = auth()->user()->customer;
        $site = $customer ? $this->resolveOwnedSite((int) $commission->site_id, $customer->sites) : null;

        if ($site === null || $commission->site_id !== $site->id) {
            abort(403);
        }

        if ($commission->status !== MotionBespokeCommission::STATUS_PUBLISHED || $commission->product_id === null) {
            return back()->with('error', 'این موشن هنوز برای خرید آماده نیست.');
        }

        $product = Product::where('id', $commission->product_id)
            ->where('type', Product::TYPE_MOTION_ASSET)
            ->where('is_active', true)
            ->first();
        if ($product === null) {
            return back()->with('error', 'محصول مرتبط با این موشن در دسترس نیست.');
        }

        $alreadyPurchased = $commission->purchases()->where('site_id', $site->id)->exists();
        if ($alreadyPurchased) {
            return back()->with('error', 'این موشن قبلاً خریداری شده است.');
        }

        $cart->add($product->id, 1, $site->id);

        return redirect()->route('store.cart')->with('success', 'به سبد خرید اضافه شد.');
    }

    /**
     * @param \Illuminate\Support\Collection<int, Site> $ownedSites
     */
    private function resolveOwnedSite(?int $siteId, $ownedSites): ?Site
    {
        if ($siteId === null) {
            return null;
        }

        return $ownedSites->firstWhere('id', $siteId);
    }

    private function contextFor(Site $site): MotionAdapterContext
    {
        $this->tenantContext->set($site);

        return new MotionAdapterContext(
            actorId: 'user:'.auth()->id(),
            siteId: (string) $site->id,
            correlationId: (string) Str::uuid(),
        );
    }
}
