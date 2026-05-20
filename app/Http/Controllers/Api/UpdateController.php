<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteUpdate;
use App\Services\LicenseServerService;
use App\Services\ModuleUpdateEligibilityService;
use App\Services\SupportLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API سرور بروزرسانی: قالب سایت و ماژول‌ها.
 */
class UpdateController extends Controller
{
    public function __construct(
        protected LicenseServerService $licenseService
    ) {}

    /**
     * GET /api/updates/check?product=site_template&current_version=1.0.0&license_key=...&domain=...
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'product' => 'required|string|max:64',
            'current_version' => 'nullable|string|max:32',
            'license_key' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
        ]);

        $valid = $this->licenseService->validate(
            $request->input('license_key'),
            $request->input('domain')
        );

        if (empty($valid['valid'])) {
            return response()->json([
                'allowed' => false,
                'error' => $valid['error'] ?? 'invalid_license',
            ], 403);
        }

        // Phase 5 / P0-5: یافتن لایسنس از طریق سرویس مرکزی (HMAC + lazy rehash از SHA256 legacy).
        $license = $this->licenseService->findByKey($request->input('license_key'));

        if (!$license || !$license->site) {
            return response()->json(['allowed' => false], 403);
        }

        $site = $license->site;

        // دریافت بروزرسانی فقط در دورهٔ فعال پشتیبانی یا grace period امکان‌پذیر است.
        $supportService = app(SupportLicenseService::class);
        if (!$supportService->isEligibleForUpdates($site)) {
            return response()->json([
                'allowed' => false,
                'error' => 'support_expired',
            ], 403);
        }

        $latest = SiteUpdate::where('site_id', $site->id)->orderByDesc('distributed_at')->first();

        if (!$latest) {
            return response()->json([
                'allowed' => true,
                'latest_version' => null,
                'update_available' => false,
            ]);
        }

        $current = $request->input('current_version', '0');
        $updateAvailable = version_compare($latest->version_label, $current, '>');

        return response()->json([
            'allowed' => true,
            'latest_version' => $latest->version_label,
            'update_available' => $updateAvailable,
            'changelog' => $latest->notes,
            'distributed_at' => $latest->distributed_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/updates/modules — بروزرسانی‌های موجود ماژول‌ها برای tenant.
     */
    public function checkModules(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
        ]);

        $valid = $this->licenseService->validate(
            $request->input('license_key'),
            $request->input('domain')
        );

        if (empty($valid['valid'])) {
            return response()->json([
                'allowed' => false,
                'error' => $valid['error'] ?? 'invalid_license',
            ], 403);
        }

        // Phase 5 / P0-5: یافتن لایسنس از طریق سرویس مرکزی (HMAC + lazy rehash از SHA256 legacy).
        $license = $this->licenseService->findByKey($request->input('license_key'));

        if (!$license || !$license->site) {
            return response()->json(['allowed' => false], 403);
        }

        $eligibilityService = app(ModuleUpdateEligibilityService::class);
        if (!$eligibilityService->isEligibleForModuleUpdates($license->site)) {
            return response()->json([
                'allowed' => false,
                'error' => 'support_expired',
            ], 403);
        }

        $updates = $eligibilityService->getAvailableModuleUpdates($license->site);

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
            'license_key' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $valid = $this->licenseService->validate(
            $request->input('license_key'),
            $request->input('domain')
        );

        if (empty($valid['valid'])) {
            return response()->json([
                'allowed' => false,
                'error' => $valid['error'] ?? 'invalid_license',
            ], 403);
        }

        $license = $this->licenseService->findByKey($request->input('license_key'));

        if (!$license || !$license->site) {
            return response()->json(['allowed' => false, 'error' => 'invalid_license'], 403);
        }

        $eligibilityService = app(ModuleUpdateEligibilityService::class);
        if (!$eligibilityService->isEligibleForModuleUpdates($license->site)) {
            return response()->json([
                'allowed' => false,
                'error' => 'support_expired',
            ], 403);
        }

        $limit = (int) $request->input('limit', 100);
        $limit = max(1, min($limit, 200));

        // فقط ماژول‌هایی که این سایت به‌صورت فعال در site_modules دارد + ماژول‌های license-driven.
        $siteModuleIds = \Illuminate\Support\Facades\DB::table('site_modules')
            ->where('site_id', $license->site->id)
            ->where('is_active', true)
            ->pluck('module_id')
            ->all();

        // اگر site_modules خالی بود (یا جدول مفقود)، fallback بر اساس license modules.
        if (empty($siteModuleIds)) {
            $licensedSlugs = is_array($license->modules ?? null) ? $license->modules : [];
            if (!empty($licensedSlugs)) {
                $siteModuleIds = \App\Models\Module::whereIn('slug', $licensedSlugs)
                    ->pluck('id')
                    ->all();
            }
        }

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
            'license_key' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'versions' => 'required|array',
            'versions.*' => 'string|max:32',
        ]);

        $valid = $this->licenseService->validate(
            $request->input('license_key'),
            $request->input('domain')
        );

        if (empty($valid['valid'])) {
            return response()->json([
                'success' => false,
                'error' => $valid['error'] ?? 'invalid_license',
            ], 403);
        }

        // Phase 5 / P0-5: یافتن لایسنس از طریق سرویس مرکزی (HMAC + lazy rehash از SHA256 legacy).
        $license = $this->licenseService->findByKey($request->input('license_key'));

        if (!$license || !$license->site) {
            return response()->json(['success' => false, 'error' => 'invalid_license'], 403);
        }

        $eligibilityService = app(ModuleUpdateEligibilityService::class);
        if (!$eligibilityService->isEligibleForModuleUpdates($license->site)) {
            return response()->json([
                'success' => false,
                'error' => 'support_expired',
            ], 403);
        }

        $versions = $request->input('versions', []);
        $eligibilityService->recordReportedVersions($license->site, $versions);

        return response()->json(['success' => true]);
    }
}
