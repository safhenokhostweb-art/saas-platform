<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

interface RecommendationRefreshServiceInterface
{
    /**
     * Refresh recommendations for a single user.
     *
     * Returns ['refreshed' => int, 'expired' => int, 'skipped' => int].
     */
    public function refreshForUser(int $userId, ?int $siteId, bool $dryRun = false): array;

    /**
     * Refresh recommendations for every active user on a site (or globally).
     *
     * Returns aggregate ['refreshed' => int, 'expired' => int, 'skipped' => int, 'users' => int].
     */
    public function refreshForSite(?int $siteId, bool $dryRun = false): array;

    /**
     * Stale threshold constant key — reads from config('education.recommendation.stale_days', 7).
     */
    public const DEFAULT_STALE_DAYS = 7;

    /**
     * Maximum active recommendations per user — reads from config('education.recommendation.max_active_per_user', 10).
     */
    public const DEFAULT_MAX_ACTIVE = 10;
}
