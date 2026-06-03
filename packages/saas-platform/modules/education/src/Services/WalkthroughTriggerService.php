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

        // Filter out already-completed walkthroughs for this user
        $completedIds = EducationWalkthroughProgress::where('user_id', $userId)
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

    public function markCompleted(int $walkthroughId, int $userId): void
    {
        EducationWalkthroughProgress::updateOrCreate(
            [
                'edu_walkthrough_id' => $walkthroughId,
                'user_id'            => $userId,
            ],
            [
                'completed_at' => now(),
            ]
        );
    }
}
