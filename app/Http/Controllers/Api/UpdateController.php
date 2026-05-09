<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModuleRelease;
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

        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($request->license_key)), 0, 8);
        $hash = hash('sha256', $request->input('license_key'));
        $license = \App\Models\PlatformLicense::where('license_key_prefix', $prefix)
            ->where('license_key_hash', $hash)
            ->with('site')
            ->first();

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

        $license = \App\Models\PlatformLicense::where('license_key_prefix', substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($request->license_key)), 0, 8))
            ->where('license_key_hash', hash('sha256', $request->input('license_key')))
            ->with('site')
            ->first();

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
        $updates = array_map(function (array $row) use ($license) {
            $module = $license->site->modules()->where('slug', $row['slug'])->first();
            if (!$module) {
                return $row;
            }
            $latest = ModuleRelease::latestForModule($module->id);
            if ($latest) {
                $row['latest_release'] = [
                    'version' => $latest->version,
                    'release_type' => $latest->release_type,
                    'title' => null,
                    'changelog' => $latest->changelog,
                    'released_at' => $latest->distributed_at?->toIso8601String(),
                ];
            }
            return $row;
        }, $updates);

        return response()->json([
            'allowed' => true,
            'modules' => $updates,
        ]);
    }

    /**
     * GET /api/updates/module-history — تاریخچهٔ نسخه و تغییرات ماژول‌های این سایت.
     */
    public function moduleHistory(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
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

        $license = \App\Models\PlatformLicense::where('license_key_prefix', substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($request->license_key)), 0, 8))
            ->where('license_key_hash', hash('sha256', $request->input('license_key')))
            ->with('site.modules')
            ->first();

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

        $moduleIds = $license->site->modules->pluck('id')->all();
        $moduleById = $license->site->modules->keyBy('id');
        $limit = (int) $request->input('limit', 50);

        $releases = ModuleRelease::query()
            ->whereIn('module_id', $moduleIds)
            ->where('is_active', true)
            ->orderByDesc('distributed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $rows = $releases->map(function (ModuleRelease $release) use ($moduleById) {
            $module = $moduleById[$release->module_id] ?? null;
            return [
                'module_slug' => $module?->slug,
                'module_title' => $module?->title,
                'version' => $release->version,
                'release_type' => $release->release_type,
                'title' => null,
                'changelog' => $release->changelog,
                'released_at' => $release->distributed_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'allowed' => true,
            'history' => $rows,
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

        $license = \App\Models\PlatformLicense::where('license_key_prefix', substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($request->license_key)), 0, 8))
            ->where('license_key_hash', hash('sha256', $request->input('license_key')))
            ->with('site')
            ->first();

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
