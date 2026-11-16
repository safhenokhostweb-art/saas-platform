<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAssetPolicy;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use App\Services\AssetProvisioning\SiteAssetEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessPolicy;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use SaaSPlatform\ThemeRuntime\Access\BusinessVerticals;

/**
 * ADR-087: the central panel for platform-asset access.
 *
 *   admin.asset-access.index   the catalog: every restricted asset, its
 *                              declared and effective rule, how many sites
 *                              hold a grant, and the per-asset override form.
 *   admin.sites.asset-access   one site: business vertical, entitlement
 *                              keys, and each restricted asset's decision
 *                              with grant / revoke.
 *
 * Validation and writes live in SiteAssetEntitlementService; this
 * controller only translates requests and results.
 */
class AssetAccessController extends Controller
{
    public function __construct(
        protected SiteAssetEntitlementService $service,
    ) {}

    public function index()
    {
        $overrides = PlatformAssetPolicy::query()->orderBy('asset_kind')->orderBy('asset_key')->get()->keyBy(fn (PlatformAssetPolicy $p) => $p->ref());
        $grantCounts = SiteAssetGrant::query()->active()
            ->selectRaw('asset_kind, asset_key, COUNT(*) as aggregate')
            ->groupBy('asset_kind', 'asset_key')->get()
            ->mapWithKeys(fn ($row) => [$row->asset_kind . ':' . $row->asset_key => (int) $row->aggregate]);

        return view('admin.asset-access.index', [
            'restricted' => AssetAccessCatalog::restrictedAssets(),
            'families' => AssetAccessCatalog::families(),
            'listed' => AssetAccessCatalog::listedAssets(),
            'overrides' => $overrides,
            'grantCounts' => $grantCounts,
            'verticals' => BusinessVerticals::LABELS,
            'motionCatalogUrl' => Route::has('admin.motion-graphics.catalog.index') ? route('admin.motion-graphics.catalog.index') : null,
        ]);
    }

    public function site(Site $site)
    {
        $entitlements = $this->service->forSite($site);
        $grants = $site->assetGrants()->get()->keyBy(fn (SiteAssetGrant $g) => $g->ref());

        // Restricted in the manifest, plus anything an override made restricted.
        $refs = array_keys(AssetAccessCatalog::restrictedAssets());
        foreach (PlatformAssetPolicy::query()->where('is_public', false)->get() as $override) {
            $refs[] = $override->ref();
        }
        $rows = [];
        foreach (array_unique($refs) as $ref) {
            [$kind, $key] = AssetRef::parse($ref);
            $rule = AssetAccessCatalog::rule($kind, $key, $entitlements);
            if ($rule === null) {
                continue;
            }
            $rows[$ref] = [
                'kind' => $kind,
                'key' => $key,
                'name' => AssetAccessCatalog::displayName($kind, $key),
                'rule' => $rule,
                'decision' => AssetAccessPolicy::decide($rule, $ref, $entitlements),
                'grant' => $grants->get($ref),
            ];
        }
        uasort($rows, fn (array $a, array $b) => [$a['rule']->family, $a['kind'], $a['key']] <=> [$b['rule']->family, $b['kind'], $b['key']]);

        return view('admin.asset-access.site', [
            'site' => $site,
            'entitlements' => $entitlements,
            'rows' => $rows,
            'verticals' => BusinessVerticals::LABELS,
            'sourceLabels' => SiteAssetGrant::SOURCE_LABELS,
        ]);
    }

    public function updateVertical(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate(['business_vertical' => 'nullable|string|max:64']);

        return $this->attempt(fn () => $this->service->setVertical($site, $data['business_vertical'] ?? null, Auth::id()), 'حوزهٔ کسب‌وکار سایت ذخیره شد.');
    }

    public function grant(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'asset' => 'required|string|max:160',
            'source' => 'required|string|in:' . implode(',', SiteAssetGrant::SOURCES),
            'reference' => 'nullable|string|max:191',
        ]);

        return $this->attempt(fn () => $this->service->grant($site, $data['asset'], $data['source'], $data['reference'] ?? null, Auth::id()), 'دسترسی اختصاص داده شد.');
    }

    public function revoke(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate(['asset' => 'required|string|max:160']);

        return $this->attempt(
            fn () => $this->service->revoke($site, $data['asset'], Auth::id()) ?: throw new \DomainException('اختصاص فعالی برای این مورد وجود ندارد.'),
            'دسترسی لغو شد. سایت در همگام‌سازی بعدی آن را از دست می‌دهد.',
        );
    }

    public function saveOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset' => 'required|string|max:160',
            'visibility' => 'required|in:public,restricted',
            'required_vertical' => 'nullable|string|max:64',
            'entitlement_keys' => 'nullable|string|max:500',
            'locked_display' => 'required|in:teaser,hidden',
            'note' => 'nullable|string|max:500',
        ]);
        $keys = array_values(array_filter(array_map('trim', preg_split('/[\s,،]+/u', (string) ($data['entitlement_keys'] ?? '')) ?: [])));

        return $this->attempt(fn () => $this->service->setOverride($data['asset'], [
            'is_public' => $data['visibility'] === 'public',
            'required_vertical' => $data['required_vertical'] ?? null,
            'entitlement_keys' => $keys,
            'locked_display' => $data['locked_display'],
        ], $data['note'] ?? null, Auth::id()), 'سیاست دسترسی این مورد بازنویسی شد و در همگام‌سازی بعدی سایت‌ها اعمال می‌شود.');
    }

    public function clearOverride(Request $request): RedirectResponse
    {
        $data = $request->validate(['asset' => 'required|string|max:160']);

        return $this->attempt(fn () => $this->service->clearOverride($data['asset'], Auth::id()), 'سیاست پیش‌فرض کاتالوگ برگردانده شد.');
    }

    private function attempt(callable $write, string $success): RedirectResponse
    {
        try {
            $write();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
