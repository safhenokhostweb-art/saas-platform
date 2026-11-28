<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteUpdate;
use App\Services\LicenseServerService;
use App\Services\ModuleUpdateEligibilityService;
use App\Services\SupportLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/**
 * API سرور بروزرسانی: قالب سایت و ماژول‌ها.
 */
class UpdateController extends Controller
{
    public function __construct(
        protected LicenseServerService $licenseService,
        protected SiteContextContract $siteContext,
    ) {}

    /**
     * GET /api/updates/check?product=site_template&current_version=1.0.0&license_key=...&domain=...
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'product' => 'required|string|max:64',
            'current_version' => 'nullable|string|max:32',
        ]);
        $site = $this->currentSite();

        // دریافت بروزرسانی فقط در دورهٔ فعال پشتیبانی یا grace period امکان‌پذیر است.
        $supportService = app(SupportLicenseService::class);
        if (!$supportService->isEligibleForUpdates($site)) {
            return response()->json([
                'allowed' => false,
                'error' => 'support_expired',
            ], 403);
        }

        $latest = SiteUpdate::query()
            ->forSite($site->id)
            ->published()
            ->orderByDesc('release_date')
            ->orderByDesc('distributed_at')
            ->first();

        if (!$latest) {
            return response()->json([
                'allowed' => true,
                'latest_version' => null,
                'update_available' => false,
            ]);
        }

        $current = (string) $request->input('current_version', '0');
        $targetVersion = $latest->version ?: $latest->version_label;
        $cleanTarget = ltrim($targetVersion, 'vV');
        $cleanCurrent = ltrim($current, 'vV');
        $updateAvailable = version_compare($cleanTarget, $cleanCurrent, '>');

        return response()->json([
            'allowed' => true,
            'latest_version' => $targetVersion,
            'title' => $latest->title ?: $targetVersion,
            'shamsi_date' => $latest->shamsi_date ?: ($latest->distributed_at ? \App\Helpers\Jalali::formatFa($latest->distributed_at) : null),
            'update_available' => $updateAvailable,
            'changelog' => $latest->notes,
            'features' => $latest->features ?: [],
            'update_type' => $latest->update_type ?? 'feature',
            'type_label' => $latest->getTypeLabel(),
            'distributed_at' => $latest->distributed_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/updates/history — تاریخچهٔ بروزرسانی‌های سامانه و امکانات جدید برای tenant.
     */
    public function history(Request $request): JsonResponse
    {
        $site = $this->currentSite();

        $limit = (int) $request->input('limit', 20);
        $limit = max(1, min($limit, 50));

        $updates = SiteUpdate::query()
            ->forSite($site->id)
            ->published()
            ->orderByDesc('release_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $history = $updates->map(function (SiteUpdate $u) {
            return [
                'id' => $u->id,
                'title' => $u->title ?: ($u->version ?: $u->version_label),
                'version' => $u->version ?: $u->version_label,
                'version_label' => $u->version_label,
                'shamsi_date' => $u->shamsi_date ?: ($u->distributed_at ? \App\Helpers\Jalali::formatFa($u->distributed_at) : null),
                'release_date' => $u->release_date?->format('Y-m-d') ?: $u->distributed_at?->format('Y-m-d'),
                'update_type' => $u->update_type ?? 'feature',
                'type_label' => $u->getTypeLabel(),
                'features' => $u->features ?: [],
                'notes' => $u->notes,
                'is_fleet' => $u->isFleet(),
            ];
        })->values();

        return response()->json([
            'allowed' => true,
            'history' => $history,
            'latest' => $history->first(),
        ]);
    }

    /**
     * GET /api/updates/modules — بروزرسانی‌های موجود ماژول‌ها برای tenant.
     */
    public function checkModules(Request $request): JsonResponse
    {
        $request->validate([
        ]);
        $site = $this->currentSite();

        $eligibilityService = app(ModuleUpdateEligibilityService::class);
        if (!$eligibilityService->isEligibleForModuleUpdates($site)) {
            return response()->json([
                'allowed' => false,
                'error' => 'support_expired',
            ], 403);
        }

        $updates = $eligibilityService->getAvailableModuleUpdates($site);

        return response()->json([
            'allowed' => true,
            'modules' => $updates,
        ]);
    }

    /**
     * GET /api/updates/module-history — تاریخچهٔ نسخه‌های ماژول‌های مجاز برای این tenant.
     *
     * Phase 5 / Wave 3 / P0-6.
     * Contract سازگار با customer-site/UpdateCheckService::syncModuleChangeLogs():
     *   query: license_key, domain, limit (اختیاری 1..200، پیش‌فرض 100)
     *   پاسخ موفق: { allowed: true, history: [
     *       { module_slug, version, release_type, title, changelog, released_at }
     *   ] }
     *   پاسخ ناموفق: { allowed: false, error: '...' } با کد 403.
     */
    public function moduleHistory(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:200',
        ]);
        $site = $this->currentSite();

        $eligibilityService = app(ModuleUpdateEligibilityService::class);
        if (!$eligibilityService->isEligibleForModuleUpdates($site)) {
            return response()->json([
                'allowed' => false,
                'error' => 'support_expired',
            ], 403);
        }

        $limit = (int) $request->input('limit', 100);
        $limit = max(1, min($limit, 200));

        // فقط ماژول‌هایی که این سایت به‌صورت فعال در site_modules دارد + ماژول‌های license-driven.
        $siteModuleIds = \Illuminate\Support\Facades\DB::table('site_modules')
            ->where('site_id', $site->id)
            ->where('is_active', true)
            ->pluck('module_id')
            ->all();

        if (empty($siteModuleIds)) {
            return response()->json(['allowed' => true, 'history' => []]);
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('module_releases')) {
            return response()->json(['allowed' => true, 'history' => []]);
        }

        $rows = \Illuminate\Support\Facades\DB::table('module_releases')
            ->join('modules', 'modules.id', '=', 'module_releases.module_id')
            ->whereIn('module_releases.module_id', $siteModuleIds)
            ->where('module_releases.is_active', true)
            ->orderByDesc('module_releases.distributed_at')
            ->orderByDesc('module_releases.id')
            ->limit($limit)
            ->get([
                'modules.slug as module_slug',
                'module_releases.version',
                'module_releases.release_type',
                'module_releases.changelog',
                'module_releases.distributed_at',
            ]);

        $history = $rows->map(function ($r) {
            $releasedAt = null;
            if (!empty($r->distributed_at)) {
                try {
                    $releasedAt = \Carbon\Carbon::parse($r->distributed_at)->toIso8601String();
                } catch (\Throwable) {
                    $releasedAt = null;
                }
            }

            return [
                'module_slug' => (string) $r->module_slug,
                'version' => (string) $r->version,
                'release_type' => (string) ($r->release_type ?? 'patch'),
                'title' => null,
                'changelog' => $r->changelog !== null ? (string) $r->changelog : null,
                'released_at' => $releasedAt,
            ];
        })->all();

        return response()->json([
            'allowed' => true,
            'history' => $history,
        ]);
    }

    /**
     * POST /api/updates/report-versions — گزارش نسخه‌های نصب‌شدهٔ ماژول‌ها از tenant.
     */
    public function reportVersions(Request $request): JsonResponse
    {
        $request->validate([
            'versions' => 'required|array',
            'versions.*' => 'string|max:32',
        ]);

        $site = $this->currentSite();

        $eligibilityService = app(ModuleUpdateEligibilityService::class);
        if (!$eligibilityService->isEligibleForModuleUpdates($site)) {
            return response()->json([
                'success' => false,
                'error' => 'support_expired',
            ], 403);
        }

        $versions = $request->input('versions', []);
        $eligibilityService->recordReportedVersions($site, $versions);

        return response()->json(['success' => true]);
    }

    private function currentSite(): \App\Models\Site
    {
        return \App\Models\Site::query()->findOrFail($this->siteContext->requireSiteId());
    }
}
