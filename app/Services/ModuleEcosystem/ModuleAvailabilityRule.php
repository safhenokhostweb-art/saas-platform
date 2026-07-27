<?php

namespace App\Services\ModuleEcosystem;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Product;

/**
 * Phase 17-I — Part 3: the formal "Assignable" formula.
 *
 * Module (type ∈ {core, paid}, not internal, not retired)
 *   AND ModuleRelease.status = published (a package genuinely exists)
 *   AND, only for paid-marketplace modules, an active Product row sells it
 *   = Assignable
 *
 * Deliberately NOT wired into the existing VisibilityResolver/
 * TenantMarketplaceController (Phase 3-A/3-B): those already have their
 * own tested "available" semantics, and the actual enforcement — can a
 * deploy for an unpublished release ever succeed — is already complete
 * and correct at the ModuleDeploymentRequestService layer (Phase 17-H;
 * see the Phase 17-I audit report). This class is the new, additive,
 * stricter query used by MarketplaceModuleCatalog (Part 5) for "what
 * should genuinely be offered as sellable right now" — a discovery-layer
 * concern, not a re-litigation of the existing per-site runtime
 * visibility contract.
 */
class ModuleAvailabilityRule
{
    /**
     * @return array{assignable: bool, reasons: string[]}
     */
    public function evaluate(Module $module): array
    {
        $reasons = [];

        if ($module->isInternal()) {
            $reasons[] = 'internal_module';
        }

        if ($module->isRetired()) {
            $reasons[] = 'retired';
        }

        if (!$module->isCore() && !$module->isPaid()) {
            $reasons[] = 'not_customer_facing_type';
        }

        if (ModuleRelease::latestPublishedForModule($module->id) === null) {
            $reasons[] = 'no_published_release';
        }

        if ($module->isPaidMarketplace() && !$this->hasActiveProduct($module)) {
            $reasons[] = 'no_active_product';
        }

        return ['assignable' => empty($reasons), 'reasons' => $reasons];
    }

    public function isAssignable(Module $module): bool
    {
        return $this->evaluate($module)['assignable'];
    }

    private function hasActiveProduct(Module $module): bool
    {
        return Product::where('module_id', $module->id)
            ->where('type', Product::TYPE_MODULE_ADDON)
            ->where('is_active', true)
            ->exists();
    }
}
