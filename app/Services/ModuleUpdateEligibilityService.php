<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;

/**
 * تعیین واجد شرایط بودن و بروزرسانی‌های موجود برای ماژول‌ها به‌صورت tenant-aware.
 */
class ModuleUpdateEligibilityService
{
    public const UPDATE_TYPE_PATCH = 'patch';
    public const UPDATE_TYPE_MINOR = 'minor';
    public const UPDATE_TYPE_MAJOR = 'major';

    public function __construct(
        protected SupportLicenseService $supportService
    ) {}

    /**
     * آیا این سایت (tenant) واجد دریافت بروزرسانی ماژول است؟
     */
    public function isEligibleForModuleUpdates(Site $site): bool
    {
        return $this->supportService->isEligibleForUpdates($site);
    }

    /**
     * لیست ماژول‌های دارای بروزرسانی برای این سایت.
     *
     * @return array<int, array{slug: string, title: string, installed_version: string|null, available_version: string, update_type: string}>
     */
    public function getAvailableModuleUpdates(Site $site): array
    {
        if (!$this->isEligibleForModuleUpdates($site)) {
            return [];
        }

        $site->load(['modules' => fn ($q) => $q->whereIn('type', [Module::TYPE_CORE, Module::TYPE_PAID])]);
        $updates = [];

        foreach ($site->modules as $module) {
            $installed = $module->pivot->installed_version ?? null;
            $available = ModuleRelease::getLatestVersionForModule($module);

            if (!$this->hasUpdateAvailable($installed, $available)) {
                continue;
            }

            $updateType = $this->getUpdateType($installed ?? '0', $available);
            $updates[] = [
                'slug' => $module->slug,
                'title' => $module->title,
                'installed_version' => $installed,
                'available_version' => $available,
                'update_type' => $updateType,
            ];
        }

        return $updates;
    }

    /**
     * نوع بروزرسانی بر اساس semver: patch، minor، major.
     */
    public function getUpdateType(string $installed, string $available): string
    {
        $installedParts = $this->parseSemver($installed);
        $availableParts = $this->parseSemver($available);

        if ($availableParts['major'] > $installedParts['major']) {
            return self::UPDATE_TYPE_MAJOR;
        }
        if ($availableParts['major'] === $installedParts['major'] && $availableParts['minor'] > $installedParts['minor']) {
            return self::UPDATE_TYPE_MINOR;
        }
        return self::UPDATE_TYPE_PATCH;
    }

    /**
     * آیا نسخهٔ نصب‌شده قدیمی‌تر از نسخهٔ موجود است؟
     */
    protected function hasUpdateAvailable(?string $installed, string $available): bool
    {
        if ($installed === null || $installed === '') {
            return true;
        }
        return version_compare($available, $installed, '>');
    }

    /**
     * پارس نسخهٔ semver به major.minor.patch.
     *
     * @return array{major: int, minor: int, patch: int}
     */
    protected function parseSemver(string $version): array
    {
        $parts = explode('.', preg_replace('/[^0-9.]/', '', $version) ?: '0');
        return [
            'major' => (int) ($parts[0] ?? 0),
            'minor' => (int) ($parts[1] ?? 0),
            'patch' => (int) ($parts[2] ?? 0),
        ];
    }

    /**
     * ثبت نسخه‌های گزارش‌شده از tenant در site_modules.
     *
     * @param  array<string, string>  $versions  slug => version
     */
    public function recordReportedVersions(Site $site, array $versions): void
    {
        if (empty($versions) || !\Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
            return;
        }

        $moduleIds = Module::whereIn('slug', array_keys($versions))
            ->pluck('id', 'slug');

        foreach ($versions as $slug => $version) {
            $moduleId = $moduleIds[$slug] ?? null;
            if ($moduleId === null) {
                continue;
            }

            \Illuminate\Support\Facades\DB::table('site_modules')
                ->where('site_id', $site->id)
                ->where('module_id', $moduleId)
                ->update([
                    'installed_version' => $version,
                    'version_reported_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }
}
