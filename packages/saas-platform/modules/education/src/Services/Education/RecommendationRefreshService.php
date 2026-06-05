<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Contracts\Services\RecommendationRefreshServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Services\EducationRecommendationService;

/**
 * Orchestrates recommendation lifecycle: expire stale → regenerate fresh.
 *
 * Rules (EDU-14B):
 *   — Dismissed recommendations are NEVER regenerated.
 *   — Completed recommendations are NEVER regenerated.
 *   — Stale threshold is configurable (education.recommendation.stale_days).
 *   — Max active recommendations per user is respected (education.recommendation.max_active_per_user).
 *   — No duplicate creation: EducationRecommendationService.generateForUser() already guards this.
 */
class RecommendationRefreshService implements RecommendationRefreshServiceInterface
{
    public function __construct(
        private readonly EducationRecommendationService $recommendationService,
    ) {}

    // -----------------------------------------------------------------------
    // Public interface
    // -----------------------------------------------------------------------

    public function refreshForUser(int $userId, ?int $siteId, bool $dryRun = false): array
    {
        $staleDays = (int) config('education.recommendation.stale_days', self::DEFAULT_STALE_DAYS);
        $staleAt   = Carbon::now()->subDays($staleDays);

        // Active stale: not dismissed, not completed, created before stale threshold
        $staleQuery = EducationRecommendation::forUser($userId)
            ->forSite($siteId)
            ->whereNull('dismissed_at')
            ->whereNull('completed_at')
            ->where('created_at', '<', $staleAt);

        $expiredCount = $staleQuery->count();

        $refreshedCount = 0;
        if (! $dryRun) {
            // Transaction: if generateForUser() throws, the delete is rolled back so
            // the user is never left with fewer recommendations than before.
            DB::transaction(function () use ($staleQuery, $userId, $siteId, &$refreshedCount) {
                if ($staleQuery->count() > 0) {
                    $staleQuery->delete();
                }
                $refreshedCount = $this->recommendationService->generateForUser($userId, $siteId);
            });
        }

        $skippedCount = $this->countSkipped($userId, $siteId);

        return [
            'refreshed' => $refreshedCount,
            'expired'   => $expiredCount,
            'skipped'   => $skippedCount,
        ];
    }

    public function refreshForSite(?int $siteId, bool $dryRun = false): array
    {
        $userIds = $this->activeUserIds($siteId);

        $totals = ['refreshed' => 0, 'expired' => 0, 'skipped' => 0, 'users' => $userIds->count()];

        foreach ($userIds as $userId) {
            $result = $this->refreshForUser($userId, $siteId, $dryRun);
            $totals['refreshed'] += $result['refreshed'];
            $totals['expired']   += $result['expired'];
            $totals['skipped']   += $result['skipped'];
        }

        return $totals;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Count dismissed + completed recommendations for this user — these are permanently skipped.
     */
    private function countSkipped(int $userId, ?int $siteId): int
    {
        return EducationRecommendation::forUser($userId)
            ->forSite($siteId)
            ->where(function ($q) {
                $q->whereNotNull('dismissed_at')
                  ->orWhereNotNull('completed_at');
            })
            ->count();
    }

    /**
     * Distinct user IDs who have interacted with the education system on this site.
     * Derived from feature usage so we only refresh users who are actually active.
     */
    private function activeUserIds(?int $siteId): Collection
    {
        return EducationFeatureUsage::select('user_id')
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->distinct()
            ->pluck('user_id');
    }
}
