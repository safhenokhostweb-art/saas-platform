<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseServerService;
use App\Services\ModuleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API فروشگاه ماژول برای پنل tenant: لیست ماژول‌های قابل خرید + وضعیت نصب.
 */
class TenantMarketplaceController extends Controller
{
    public function __construct(
        protected LicenseServerService $licenseService,
        protected ModuleAssignmentService $assignmentService
    ) {}

    /**
     * GET /api/tenant-marketplace/modules?license_key=...&domain=...
     * ماژول‌های پولی (فقط مارکت‌پلیس) را برمی‌گرداند با وضعیت نصب برای این سایت.
     */
    public function modules(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
        ]);

        $result = $this->licenseService->validate(
            $request->input('license_key'),
            $request->input('domain')
        );

        if (empty($result['valid'])) {
            return response()->json([
                'allowed' => false,
                'error' => $result['error'] ?? 'invalid_license',
                'modules' => [],
            ], 403);
        }

        $siteId = $result['site_id'] ?? null;
        if (! $siteId) {
            return response()->json(['allowed' => true, 'modules' => []]);
        }

        $site = \App\Models\Site::find($siteId);
        if (! $site) {
            return response()->json(['allowed' => true, 'modules' => []]);
        }

        $enabledSlugs = $this->assignmentService->getEnabledSlugsForSite($site);

        // P2-5: مالکیت تاریخی (owned) فراتر از is_active فعلی است.
        // منبع: site_module_purchases (هر خرید موفق) + module_licenses فعال.
        // اگر این مالکیت وجود داشت ولی site_modules.is_active=false بود →
        // وضعیت owned_disabled گزارش می‌شود تا مشتری ماژول قبلاً خریداری‌شدهٔ
        // غیرفعال را به‌جای خرید دوباره، فقط فعال کند.
        $ownedModuleIds = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('site_module_purchases')) {
            $ownedModuleIds = array_values(array_unique(array_merge(
                $ownedModuleIds,
                \Illuminate\Support\Facades\DB::table('site_module_purchases')
                    ->where('site_id', $site->id)
                    ->pluck('module_id')
                    ->map(fn ($v) => (int) $v)
                    ->all()
            )));
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('module_licenses')) {
            $ownedModuleIds = array_values(array_unique(array_merge(
                $ownedModuleIds,
                \Illuminate\Support\Facades\DB::table('module_licenses')
                    ->where('site_id', $site->id)
                    ->where('status', \App\Models\ModuleLicense::STATUS_ACTIVE)
                    ->whereNull('revoked_at')
                    ->pluck('module_id')
                    ->map(fn ($v) => (int) $v)
                    ->all()
            )));
        }
        $ownedSet = array_flip($ownedModuleIds);

        $modules = \App\Models\Module::paidMarketplace()
            ->orderBy('title')
            ->get()
            ->map(function ($m) use ($enabledSlugs, $ownedSet) {
                $installed = in_array($m->slug, $enabledSlugs, true);
                $owned = $installed || isset($ownedSet[(int) $m->id]);
                $status = $installed
                    ? 'installed'
                    : ($owned ? 'owned_disabled' : 'available');

                return [
                    'id' => $m->id,
                    'slug' => $m->slug,
                    'title' => $m->title,
                    'short_description' => $m->short_description,
                    'description' => $m->description,
                    'price_irr' => $m->price_irr,
                    'price_promo_irr' => $m->price_promo_irr,
                    'display_price' => $m->display_price,
                    'currency' => $m->currency ?? 'IRR',
                    'category' => $m->category,
                    'installed' => $installed,
                    'owned' => $owned,
                    'status' => $status,
                ];
            });

        return response()->json([
            'allowed' => true,
            'modules' => $modules->values()->all(),
            'platform_store_url' => rtrim(config('app.url'), '/') . '/store/modules',
        ]);
    }
}
