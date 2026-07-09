<?php

namespace App\Services\ModuleEcosystem;

use App\Models\Module;
use App\Models\Site;
use App\Services\ModuleEcosystem\Contracts\VisibilityResolverInterface;
use App\Services\ModuleEcosystem\Dto\DeploymentState;
use App\Services\ModuleEcosystem\Dto\VisibilityState;

/**
 * Read-only. Answers "how should this module be displayed to the
 * customer?" by combining DeploymentResolver + EntitlementResolver +
 * Module catalog metadata (type, distribution mode, retired_at).
 *
 * Depends on the concrete DeploymentResolver/EntitlementResolver (not
 * their interfaces) so it auto-wires through the container without any
 * new binding — consistent with how those two resolvers are consumed
 * elsewhere in Phase 3-A so far. Never purchases, installs, licenses,
 * writes is_active, or performs any database write.
 */
class VisibilityResolver implements VisibilityResolverInterface
{
    public function __construct(
        protected DeploymentResolver $deploymentResolver,
        protected EntitlementResolver $entitlementResolver,
    ) {}

    public function resolve(Site $site, Module $module): VisibilityState
    {
        // Rule 1: platform-only modules never enter customer visibility.
        if ($module->isInternal()) {
            return new VisibilityState(
                state: VisibilityState::STATE_HIDDEN,
                metadata: ['reason' => 'platform_only'],
            );
        }

        // Rule 2: module not ready for general availability (disabled
        // platform-wide, e.g. a roadmap/not-yet-launched catalog entry).
        // Hidden also satisfies "must not allow direct purchase" — there
        // is no separate not_purchasable state in VisibilityState (its
        // Commit-1 contract only defines active/locked/available/hidden).
        if ($module->isDistributionDisabled()) {
            return new VisibilityState(
                state: VisibilityState::STATE_HIDDEN,
                metadata: ['reason' => 'not_ready'],
            );
        }

        $deployment = $this->deploymentResolver->resolve($site, $module);
        $entitlement = $this->entitlementResolver->resolve($site, $module);
        $isInstalled = $deployment->status !== DeploymentState::STATUS_NOT_INSTALLED;

        // Rule 3 / Rule 4: installed (or installed-but-outdated) — the
        // update-required detail is a DeploymentState concern, not a
        // separate visibility category.
        if ($isInstalled) {
            if ($entitlement->allowed) {
                return new VisibilityState(state: VisibilityState::STATE_ACTIVE);
            }

            return new VisibilityState(
                state: VisibilityState::STATE_LOCKED,
                metadata: [
                    'title' => $module->title,
                    'description' => $module->description,
                    'features' => $module->features()->pluck('title')->all(),
                    'deny_reason' => $entitlement->reason,
                ],
            );
        }

        // Rule 5: not installed — only purchasable/assignable, non-retired
        // modules are "available"; everything else stays hidden (retired
        // modules must not gain new entitlements, per Module::isRetired()).
        $isPurchasable = ! $module->isRetired()
            && ($module->isFreeAssignable() || $module->isPaidMarketplace() || $module->isLicenseOnly());

        if ($isPurchasable) {
            return new VisibilityState(state: VisibilityState::STATE_AVAILABLE);
        }

        return new VisibilityState(state: VisibilityState::STATE_HIDDEN);
    }
}
