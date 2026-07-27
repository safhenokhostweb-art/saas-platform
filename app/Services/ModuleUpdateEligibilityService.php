<?php
namespace App\Services;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Services\PlatformCompatibilityService;

/**
 * تعیین واجد شرایط بودن و بروزرسانی‌های موجود برای ماژول‌ها به‌صورت tenant-aware.
 */
class ModuleUpdateEligibilityService
{
    public const UPDATE_TYPE_PATCH = 'patch';
    public const UPDATE_TYPE_MINOR = 'minor';
    public const UPDATE_TYPE_MAJOR = 'major';

    public function __construct(
        protected SupportLicenseService $supportService,
        protected PlatformCompatibilityService $compatibilityService,
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
     * Commit 12-K: نسخهٔ جدید فقط در صورتی پیشنهاد می‌شود که با محیط پلتفرم
     * (PHP/Laravel/saas-platform، طبق module.json) سازگار باشد — طبق
     * PlatformCompatibilityService::check(). این بررسی جدا از تعیین
     * installed/available version است و منطق آن را تغییر نمی‌دهد.
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
            // Phase 17-H — Part 7: an upgrade must never be offered to a
            // draft/deprecated release, no matter its version number.
            $available = ModuleRelease::getLatestPublishedVersionForModule($module);

            if (!$this->hasUpdateAvailable($installed, $available)) {
                continue;
            }

            if (!$this->isCompatibleRelease($module, $available, $site)) {
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
     * Compatibility gate (Commit 12-K). Suppresses an otherwise-available
     * update when PlatformCompatibilityService reports a hard error
     * (e.g. PHP/Laravel/platform version constraint not met by this
     * server). Warnings (e.g. rollback safety) never block eligibility —
     * only PlatformCompatibilityService::check()'s own `errors` do.
     */
    protected function isCompatibleRelease(Module $module, string $available, Site $site): bool
    {
        $result = $this->compatibilityService->check($module->slug, $available);

        if (!empty($result['passed'])) {
            return true;
        }

        Log::warning('module_update.incompatible_release_suppressed', [
            'site_id' => $site->id,
            'module' => $module->slug,
            'available_version' => $available,
            'errors' => $result['errors'] ?? [],
        ]);

        return false;
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

    /**
     * Phase 13-M4 — optimistic write of installed_version right after a
     * MODE_MODULE_UPDATE/MODE_MODULE_INSTALL pipeline completes successfully,
     * so the platform UI doesn't show a stale version until the tenant's
     * next heartbeat.
     *
     * Deliberately does NOT touch version_reported_at — that column stays
     * exclusively owned by recordReportedVersions() (the tenant's own
     * self-report). Leaving it untouched is what makes this write provably
     * a cache/projection rather than a new source of truth: whenever the
     * tenant reports again, recordReportedVersions() unconditionally
     * overwrites installed_version and refreshes version_reported_at,
     * reconciling any drift. Mirrors the module-version-authority ADR's
     * treatment of modules.version as "cache, never a decision point."
     *
     * Phase 17-C — Part 5: also sets deployment_status='installed' and
     * last_installation_id, closing the gap where is_active could be true
     * while nothing tracked whether the code was actually deployed.
     *
     * @return bool  true if a site_modules row was actually updated
     */
    public function recordPipelineAssertedVersion(int $siteId, string $moduleSlug, string $version, ?int $installationId = null): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
            return false;
        }

        $moduleId = Module::where('slug', $moduleSlug)->value('id');
        if ($moduleId === null) {
            return false;
        }

        $update = [
            'installed_version' => $version,
            'deployment_status' => 'installed',
            'updated_at' => now(),
        ];
        if ($installationId !== null) {
            $update['last_installation_id'] = $installationId;
        }

        $updated = \Illuminate\Support\Facades\DB::table('site_modules')
            ->where('site_id', $siteId)
            ->where('module_id', $moduleId)
            ->update($update);

        return $updated > 0;
    }

    /**
     * Phase 17-C — Part 5: marks a module's deployment as failed after a
     * MODE_MODULE_UPDATE/MODE_MODULE_INSTALL pipeline ends in any non-success
     * status (failed, rollback_failed, rolled_back). Never leaves the row
     * sitting in 'pending_install'/'installing'/'upgrading' after the
     * pipeline has actually stopped running — see
     * CustomerInstallationPipelineJob, the only caller.
     *
     * Entitlement (is_active) is deliberately left untouched here — a
     * failed deployment doesn't retroactively revoke access the admin
     * granted; it only means the code isn't there yet. An admin/operator
     * decides separately whether to retry or disable.
     *
     * @return bool  true if a site_modules row was actually updated
     */
    public function markDeploymentFailed(int $siteId, string $moduleSlug): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('site_modules')) {
            return false;
        }

        $moduleId = Module::where('slug', $moduleSlug)->value('id');
        if ($moduleId === null) {
            return false;
        }

        $updated = \Illuminate\Support\Facades\DB::table('site_modules')
            ->where('site_id', $siteId)
            ->where('module_id', $moduleId)
            ->whereNotIn('deployment_status', ['installed'])
            ->update([
                'deployment_status' => 'failed',
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }
}
