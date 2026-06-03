<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;

interface WalkthroughTriggerServiceInterface
{
    /**
     * Return walkthroughs that should auto-trigger on the given route for this user.
     * Filters out walkthroughs the user has already completed.
     * Applies condition: usage_count === 0 for the associated feature (first-visit trigger).
     */
    public function triggersForRoute(string $routeName, int $userId, ?int $siteId): Collection;

    /** Mark a walkthrough as completed for the user so it is never triggered again. */
    public function markCompleted(int $walkthroughId, int $userId): void;
}
