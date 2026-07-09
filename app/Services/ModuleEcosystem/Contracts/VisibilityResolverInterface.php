<?php

namespace App\Services\ModuleEcosystem\Contracts;

use App\Models\Module;
use App\Models\Site;
use App\Services\ModuleEcosystem\Dto\VisibilityState;

/**
 * Resolves the display state a customer runtime should show for a
 * module, by combining deployment state, entitlement state, and module
 * metadata.
 *
 * Pure computation only. Must NOT perform a purchase, trigger an
 * install, or issue a license.
 */
interface VisibilityResolverInterface
{
    public function resolve(Site $site, Module $module): VisibilityState;
}
