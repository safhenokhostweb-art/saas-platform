<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Site;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Services\MotionBespokeAssignmentService;
use App\Modules\MotionGraphics\Services\MotionBespokeCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

/**
 * Phase X (docs/motion-custom-assets/): admin management of bespoke/custom
 * Motion commissions — create, advance the business lifecycle, attach the
 * module-side asset reference, link a sellable Product, assign/rollback a
 * version to the owning site, and see which site is currently using it.
 * Platform-Admin-only (the `admin` route middleware, same as every other
 * route in this group) — mirrors MotionGraphicsAdminController's exact
 * TenantContext::set(site)-before-delegating pattern.
 */
class MotionBespokeAdminController extends Controller
{
    public function __construct(
        private readonly MotionBespokeCommissionService $commissions,
        private readonly MotionBespokeAssignmentService $assignments,
        private readonly TenantContextContract $tenantContext,
    ) {}

    public function index(Site $site): View
    {
        $commissions = MotionBespokeCommission::query()
            ->where('site_id', $site->id)
            ->with('assignment', 'product')
            ->latest()
            ->get();

        return view('admin.motion-graphics.bespoke.index', [
            'site' => $site,
            'commissions' => $commissions,
        ]);
    }

    public function store(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate(['title' => 'required|string|max:191']);
        $context = $this->contextFor($site);

        try {
            $this->commissions->create($site, $data['title'], $context);

            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('success', 'کمیسیون موشن اختصاصی ایجاد شد.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('error', 'ایجاد کمیسیون انجام نشد: '.$e->getMessage());
        }
    }

    public function transition(Request $request, Site $site, MotionBespokeCommission $commission): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|string']);
        $context = $this->contextFor($site);

        try {
            $this->commissions->transition($commission, $data['status'], $context);

            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('success', 'وضعیت کمیسیون به‌روزرسانی شد.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('error', 'تغییر وضعیت انجام نشد: '.$e->getMessage());
        }
    }

    public function attachAsset(Request $request, Site $site, MotionBespokeCommission $commission): RedirectResponse
    {
        $data = $request->validate(['asset_id' => 'required|string|max:191']);
        $context = $this->contextFor($site);

        try {
            $this->commissions->attachAsset($commission, $data['asset_id'], $context);

            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('success', 'مرجع دارایی ماژول متصل شد.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('error', 'اتصال دارایی انجام نشد: '.$e->getMessage());
        }
    }

    public function linkProduct(Request $request, Site $site, MotionBespokeCommission $commission): RedirectResponse
    {
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id']);
        $context = $this->contextFor($site);
        $product = Product::query()->findOrFail($data['product_id']);

        try {
            $this->commissions->linkProduct($commission, $product, $context);

            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('success', 'محصول فروش متصل شد.');
        } catch (\InvalidArgumentException|MotionAdapterFailClosedException $e) {
            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('error', 'اتصال محصول انجام نشد: '.$e->getMessage());
        }
    }

    public function assign(Request $request, Site $site, MotionBespokeCommission $commission): RedirectResponse
    {
        $data = $request->validate(['version' => 'required|integer|min:1']);
        $context = $this->contextFor($site);

        try {
            $this->assignments->assign($commission, (int) $data['version'], $context);

            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('success', 'نسخه به سایت اختصاص یافت.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('error', 'تخصیص انجام نشد: '.$e->getMessage());
        }
    }

    public function rollback(Request $request, Site $site, MotionBespokeCommission $commission): RedirectResponse
    {
        $data = $request->validate(['version' => 'required|integer|min:1']);
        $context = $this->contextFor($site);

        try {
            $this->assignments->rollback($commission, (int) $data['version'], $context);

            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('success', 'بازگردانی به نسخه قبلی انجام شد.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('error', 'بازگردانی انجام نشد: '.$e->getMessage());
        }
    }

    public function revoke(Site $site, MotionBespokeCommission $commission): RedirectResponse
    {
        $context = $this->contextFor($site);

        try {
            $this->assignments->revoke($commission, $context);

            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('success', 'تخصیص لغو شد.');
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()->route('admin.motion-graphics.bespoke.index', $site)
                ->with('error', 'لغو تخصیص انجام نشد: '.$e->getMessage());
        }
    }

    private function contextFor(Site $site): MotionAdapterContext
    {
        $this->tenantContext->set($site);

        return new MotionAdapterContext(
            actorId: 'user:'.Auth::id(),
            siteId: (string) $site->id,
            correlationId: (string) Str::uuid(),
        );
    }
}
