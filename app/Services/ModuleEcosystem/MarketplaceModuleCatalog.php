<?php

namespace App\Services\ModuleEcosystem;

use App\Models\Module;

/**
 * Phase 17-I — Part 5: the Marketplace's "what may genuinely be offered
 * right now" query. Combines ModuleAvailabilityRule (the filter) with
 * ModuleCatalogService (per-module data assembly) — introduces no new
 * storage, no new source of truth, purely a read composed from existing
 * tables/files.
 *
 * Distinct from the existing TenantMarketplaceController (Phase 3-B): that
 * endpoint answers "what does this specific site's customer see, given
 * their entitlement/deployment state" (per-site, per-request). This
 * answers "what is sellable in the catalog at all, independent of any
 * site" (e.g. for a future admin Marketplace-readiness dashboard or a
 * public catalog page) — a different question, so it is additive rather
 * than a replacement for that controller.
 */
class MarketplaceModuleCatalog
{
    public function __construct(
        private readonly ModuleAvailabilityRule $availabilityRule,
        private readonly ModuleCatalogService $catalogService,
    ) {}

    /**
     * @return array<int, array{module: Module, latest_release: mixed, product: mixed, pricing: array, compatibility: array}>
     */
    public function assignableModules(): array
    {
        $catalog = [];

        foreach (Module::assignableToSite()->where('is_active', true)->get() as $module) {
            if (!$this->availabilityRule->isAssignable($module)) {
                continue;
            }

            $catalog[] = $this->catalogService->describe($module);
        }

        return $catalog;
    }
}
