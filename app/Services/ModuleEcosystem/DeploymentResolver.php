<?php

namespace App\Services\ModuleEcosystem;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Services\ModuleEcosystem\Contracts\DeploymentResolverInterface;
use App\Services\ModuleEcosystem\Dto\DeploymentState;

/**
 * Read-only. Answers "what is the deployment state of this module on this
 * site?" by comparing site_modules.installed_version against the module's
 * latest release.
 *
 * Delegates version comparison to the existing SemVer logic — does not
 * re-implement it. Never reads is_active, never checks license/purchase/
 * plan, never writes to the database.
 */
class DeploymentResolver implements DeploymentResolverInterface
{
    public function resolve(Site $site, Module $module): DeploymentState
    {
        $installedVersion = $this->installedVersion($site, $module);
        $latestVersion = ModuleRelease::getLatestVersionForModule($module);

        if ($installedVersion === null || $installedVersion === '') {
            return new DeploymentState(
                moduleSlug: $module->slug,
                status: DeploymentState::STATUS_NOT_INSTALLED,
                installedVersion: null,
                latestVersion: $latestVersion,
            );
        }

        if (version_compare($latestVersion, $installedVersion, '>')) {
            return new DeploymentState(
                moduleSlug: $module->slug,
                status: DeploymentState::STATUS_UPDATE_REQUIRED,
                installedVersion: $installedVersion,
                latestVersion: $latestVersion,
            );
        }

        return new DeploymentState(
            moduleSlug: $module->slug,
            status: DeploymentState::STATUS_INSTALLED,
            installedVersion: $installedVersion,
            latestVersion: $latestVersion,
        );
    }

    /**
     * Reads site_modules.installed_version via the existing Site::modules()
     * pivot relation. No raw query, no write.
     */
    protected function installedVersion(Site $site, Module $module): ?string
    {
        $pivotModule = $site->modules()->where('modules.id', $module->id)->first();

        return $pivotModule?->pivot->installed_version;
    }
}
