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
