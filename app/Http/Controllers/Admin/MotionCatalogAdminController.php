<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Services\MotionCatalogAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;

/**
 * ADR-086: the central motion catalog page. Platform-Admin-only (the
 * `admin` route group, like every motion admin route). Lists every motion
 * with its visibility, tier, delivered version and availability, and hosts
 * the two catalog-wide controls: the availability switch and the per-site
 * grants of private motions. All writes go through MotionCatalogAdminService.
 */
class MotionCatalogAdminController extends Controller
{
    public function __construct(
        private readonly MotionCatalogAdminService $catalog,
    ) {}

    public function index(): View
    {
        $motions = MotionGraphic::query()
            ->with(['category', 'versions' => fn ($q) => $q->select(['id', 'motion_graphic_id', 'version', 'version_label', 'preview_metadata', 'compiled_assets', 'status'])])
            ->whereNull('archived_at')
            ->orderBy('id')
            ->get();

        $uuids = $motions->pluck('uuid')->all();
        $grants = MotionSiteAssetGrant::query()
            ->whereIn('asset_id', $uuids)
            ->with('site:id,title,domain')
            ->orderBy('id')
            ->get()
            ->groupBy('asset_id');
        $boundSites = SiteAssetPackageItem::query()
            ->whereIn('asset_id', $uuids)
            ->where('is_active', true)
            ->join('motion_site_asset_packages', 'motion_site_asset_packages.id', '=', 'motion_site_asset_package_items.site_asset_package_id')
            ->where('motion_site_asset_packages.activation_status', 'active')
            ->selectRaw('motion_site_asset_package_items.asset_id, COUNT(DISTINCT motion_site_asset_packages.site_id) AS sites')
            ->groupBy('motion_site_asset_package_items.asset_id')
            ->pluck('sites', 'asset_id');

        return view('admin.motion-graphics.catalog', [
            'motions' => $motions,
            'grants' => $grants,
            'boundSites' => $boundSites,
            'sites' => Site::query()->orderByDesc('id')->limit(500)->get(['id', 'title', 'domain']),
        ]);
    }

    public function availability(Request $request, MotionGraphic $motion): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $data['enabled'];

        $this->catalog->setRuntimeEnabled($motion, $enabled, $this->actor());

        return redirect()->route('admin.motion-graphics.catalog.index')->with('success', $enabled
            ? "موشن «{$motion->name}» دوباره فعال شد و از دریافت بعدی مانیفست به سایت‌ها برمی‌گردد."
            : "موشن «{$motion->name}» غیرفعال شد و از دریافت بعدی مانیفست از همه سایت‌ها حذف می‌شود.");
    }

    public function grant(Request $request, MotionGraphic $motion): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'source' => ['required', Rule::in(MotionSiteAssetGrant::SOURCES)],
            'reference' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $site = Site::query()->findOrFail($data['site_id']);

        try {
            $this->catalog->grant($motion, $site, $data['source'], $data['reference'] ?? null, $data['note'] ?? null, $this->actor());
        } catch (MotionAdapterFailClosedException $e) {
            return redirect()->route('admin.motion-graphics.catalog.index')->with('error', 'اعطای موشن انجام نشد: '.$e->getMessage());
        }

        return redirect()->route('admin.motion-graphics.catalog.index')
            ->with('success', "موشن «{$motion->name}» به سایت #{$site->id} اعطا شد.");
    }

    public function revoke(MotionSiteAssetGrant $grant): RedirectResponse
    {
        $this->catalog->revoke($grant, $this->actor());

        return redirect()->route('admin.motion-graphics.catalog.index')
            ->with('success', "دسترسی سایت #{$grant->site_id} به این موشن لغو شد.");
    }

    private function actor(): string
    {
        return 'user:'.Auth::id();
    }
}
