<?php

namespace App\Services\ModuleEcosystem\Contracts;

use App\Models\Module;
use App\Models\Site;
use App\Services\ModuleEcosystem\Dto\EntitlementState;

/**
 * Resolves whether a site is allowed to use a module, based on plan,
 * purchase, license, grant, and revoke state.
 *
 * The only resolver among the three permitted to change
 * site_modules.is_active — VisibilityResolverInterface and
 * DeploymentResolverInterface must not.
 */
interface EntitlementResolverInterface
{
    public function resolve(Site $site, Module $module): EntitlementState;
}
