<?php

namespace App\Services\ModuleEcosystem\Contracts;

use App\Models\Module;
use App\Models\Site;
use App\Services\ModuleEcosystem\Dto\DeploymentState;

/**
 * Resolves whether a module is installed on a site and whether it is
 * up to date, based on site_modules.installed_version / version_reported_at
 * versus the module's latest release.
 *
 * Must NOT change site_modules.is_active.
 * Must NOT evaluate license/entitlement — that is EntitlementResolverInterface.
 */
interface DeploymentResolverInterface
{
    public function resolve(Site $site, Module $module): DeploymentState;
}
