<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Services\AIRecommendationScoreServiceInterface;
use SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;

/**
 * Deterministic AI Recommendation Scorer.
 *
 * Weights (must sum to 1.0):
 *   Knowledge Gap     40 %
 *   Recent Activity   20 %
 *   Usage History     20 %
 *   Learning Progress 20 %
 */
class AIRecommendationScoreService implements AIRecommendationScoreServiceInterface
{
    private const WEIGHT_GAP      = 0.40;
    private const WEIGHT_ACTIVITY = 0.20;
    private const WEIGHT_USAGE    = 0.20;
    private const WEIGHT_PROGRESS = 0.20;

    // Days within which activity is considered "recent"
    private const RECENT_DAYS = 30;

    public function score(
        Collection             $candidates,
        UserLearningProfileDto $profile,
        Collection             $knowledgeGaps,
    ): Collection {
        if ($candidates->isEmpty()) {
            return collect();
        }

        // Pre-compute lookup maps for O(1) access inside the loop
        $gapMap      = $knowledgeGaps->keyBy('module_slug');
        $usageMap    = $this->loadUsageMap($profile->userId, $profile->siteId);
        $progressMap = $this->loadProgressMap($profile->userId, $profile->siteId);

        $scored = $candidates->map(function (EducationRecommendation $rec) use (
            $gapMap, $usageMap, $progressMap, $profile
        ) {
            $moduleSlug = $this->resolveModuleSlug($rec);

            $gapComponent      = $this->gapComponent($moduleSlug, $gapMap);
            $activityComponent = $this->activityComponent($moduleSlug, $usageMap);
            $usageComponent    = $this->usageComponent($moduleSlug, $usageMap);
            $progressComponent = $this->progressComponent($moduleSlug, $progressMap);

            $aiScore = round(
                ($gapComponent      * self::WEIGHT_GAP)
                + ($activityComponent * self::WEIGHT_ACTIVITY)
                + ($usageComponent    * self::WEIGHT_USAGE)
                + ($progressComponent * self::WEIGHT_PROGRESS),
                2
            );

            return [
                'recommendation' => $rec,
                'ai_score'       => $aiScore,
                'score_breakdown' => [
                    'gap_component'      => $gapComponent,
                    'activity_component' => $activityComponent,
                    'usage_component'    => $usageComponent,
                    'progress_component' => $progressComponent,
                ],
            ];
        });

        return $scored->sortByDesc('ai_score')->values();
    }

    // -----------------------------------------------------------------------

    /**
     * Resolve the module slug from the recommendation's linked content.
     */
    private function resolveModuleSlug(EducationRecommendation $rec): ?string
    {
        if ($rec->article) {
            return $rec->article->module_slug;
        }
        if ($rec->walkthrough) {
            return $rec->walkthrough->module_slug ?? null;
        }
        if ($rec->learningPath) {
            return $rec->learningPath->module_slug ?? null;
        }
        return null;
    }

    /**
     * Gap component: directly the gap_score (already 0–100) for this module.
     * Higher gap → higher score → recommendation is more urgent.
     */
    private function gapComponent(?string $moduleSlug, Collection $gapMap): float
    {
        if ($moduleSlug === null) {
            return 50.0;
        }
        return (float) ($gapMap->get($moduleSlug, ['gap_score' => 50.0])['gap_score'] ?? 50.0);
    }

    /**
     * Activity component: recency score (0–100).
     * If the user visited the module recently → lower score (they're already engaged).
     * If not visited recently → higher score (nudge them back).
     */
    private function activityComponent(?string $moduleSlug, Collection $usageMap): float
    {
        if ($moduleSlug === null) {
            return 50.0;
        }

        $row = $usageMap->get($moduleSlug);
        if (! $row) {
            return 100.0; // never used → maximum nudge
        }

        $daysSince = Carbon::parse($row['last_used_at'])->diffInDays(now());

        // Exponential decay: 100 at 30+ days, 0 at 0 days
        $ratio = min($daysSince / self::RECENT_DAYS, 1.0);

        return round($ratio * 100, 2);
    }

    /**
     * Usage component: inverse of normalised usage depth.
     * Light users benefit most from recommendations.
     */
    private function usageComponent(?string $moduleSlug, Collection $usageMap): float
    {
        if ($moduleSlug === null) {
            return 50.0;
        }

        $row = $usageMap->get($moduleSlug);
        if (! $row) {
            return 100.0;
        }

        $totalUsage = (int) ($row['total_usage'] ?? 0);
        // Normalise against soft ceiling of 50 total interactions
        $normalised = min($totalUsage / 50, 1.0);

        // Inverse: low usage → high score
        return round((1.0 - $normalised) * 100, 2);
    }

    /**
     * Progress component: inverse of completion rate.
     * Incomplete modules rank higher.
     */
    private function progressComponent(?string $moduleSlug, Collection $progressMap): float
    {
        if ($moduleSlug === null) {
            return 50.0;
        }

        $completion = (float) ($progressMap->get($moduleSlug, 0.0));

        return round((1.0 - ($completion / 100)) * 100, 2);
    }

    // -----------------------------------------------------------------------

    private function loadUsageMap(int $userId, ?int $siteId): Collection
    {
        // F-04: explicit whereNull when siteId=null — prevents cross-tenant usage leakage
        return EducationFeatureUsage::where('user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->selectRaw('module_slug, SUM(usage_count) as total_usage, MAX(last_used_at) as last_used_at')
            ->groupBy('module_slug')
            ->get()
            ->keyBy('module_slug')
            ->map(fn($row) => [
                'total_usage'  => $row->total_usage,
                'last_used_at' => $row->last_used_at,
            ]);
    }

    private function loadProgressMap(int $userId, ?int $siteId): Collection
    {
        // F-04: explicit whereNull when siteId=null — prevents cross-tenant progress leakage
        $rows = EducationUserProgress::query()
            ->where('edu_user_progress.user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('edu_user_progress.site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('edu_user_progress.site_id'))
            ->where('progressable_type', 'LIKE', '%Article%')
            ->join('edu_articles', 'edu_articles.id', '=', 'edu_user_progress.progressable_id')
            ->whereNotNull('edu_articles.module_slug')
            ->selectRaw('edu_articles.module_slug,
                         AVG(edu_user_progress.progress_percent) as avg_progress')
            ->groupBy('edu_articles.module_slug')
            ->pluck('avg_progress', 'module_slug');

        return collect($rows)->map(fn($v) => (float) ($v ?? 0.0));
    }
}
