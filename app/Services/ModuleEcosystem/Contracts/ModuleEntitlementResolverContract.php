<?php

namespace App\Services\ModuleEcosystem\Contracts;

use App\Models\Module;
use App\Models\Site;
use App\Services\ModuleEcosystem\Dto\EntitlementState;

/** Canonical, read-only commercial entitlement and customer projection contract. */
interface ModuleEntitlementResolverContract
{
    public function resolve(Site $site, Module $module): EntitlementState;
    public function resolveForCurrentSite(Module $module): EntitlementState;
    /** @return array<string> */
    public function projectionForSite(Site $site): array;
    /** @return array<string> */
    public function projectionForCurrentSite(): array;
}
