<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\WalkthroughTriggerServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;

class WalkthroughTriggerService implements WalkthroughTriggerServiceInterface
{
    public function __construct(
        private readonly EducationFeatureUsageRepositoryInterface $usage,
    ) {}

    public function triggersForRoute(string $routeName, int $userId, ?int $siteId): Collection
    {
        // Load active walkthroughs configured for this route
        $walkthroughs = EducationWalkthrough::active()
            ->forRoute($routeName)
            ->forSite($siteId)
            ->with('steps')
            ->get();

        if ($walkthroughs->isEmpty()) {
            return collect();
        }

        // F-15: Scope completed walkthrough lookup by site_id to prevent cross-tenant suppression
        $completedIds = EducationWalkthroughProgress::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->whereNotNull('completed_at')
            ->pluck('edu_walkthrough_id')
            ->toArray();

        return $walkthroughs
            ->reject(fn($wt) => in_array($wt->id, $completedIds, true))
            ->filter(function (EducationWalkthrough $wt) use ($userId, $siteId) {
                // Only trigger when the user has never used this feature (first-visit rule)
                if (! $wt->trigger_route) {
                    return true;
                }
                $feature = \SaaSPlatform\Modules\Education\Models\EducationFeature::where('route_name', $wt->trigger_route)
                    ->where('site_id', null)
                    ->first();

                if (! $feature) {
                    return true; // no feature record → always trigger
                }

                $count = $this->usage->usageCountForFeature(
                    $userId, $siteId, $feature->module_slug, $feature->feature_slug
                );

                // Trigger only on the very first visit (count = 0 before middleware increments it,
                // or count = 1 after increment — middleware runs first, so we use <= 1)
                return $count <= 1;
            })
            ->values();
    }

    public function markCompleted(int $walkthroughId, int $userId, int $siteId): void
    {
        // F-01: site_id is part of the unique key — prevents cross-tenant progress leakage.
        // The DB has a unique index on (user_id, site_id, edu_walkthrough_id).
        EducationWalkthroughProgress::updateOrCreate(
            [
                'edu_walkthrough_id' => $walkthroughId,
                'user_id'            => $userId,
                'site_id'            => $siteId,
            ],
            [
                'status'       => EducationWalkthroughProgress::STATUS_COMPLETED,
                'completed_at' => now(),
            ]
        );
    }
}
