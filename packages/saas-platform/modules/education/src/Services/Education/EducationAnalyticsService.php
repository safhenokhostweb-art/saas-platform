<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use SaaSPlatform\Modules\Education\Contracts\Services\ContentReadinessServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAnalyticsServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\UserLearningProfileServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;

/**
 * Aggregates education analytics at user, site, and platform levels.
 *
 * Performance contract (EDU-14B Phase 7):
 *   — No N+1 queries. All aggregates use GROUP BY or single scalar queries.
 *   — userAnalytics():    5 queries.
 *   — siteAnalytics():    4 queries + ContentReadinessService (5 queries = batch).
 *   — platformAnalytics(): 4 queries.
 */
class EducationAnalyticsService implements EducationAnalyticsServiceInterface
{
    public function __construct(
        private readonly UserLearningProfileServiceInterface $profileService,
        private readonly ContentReadinessServiceInterface    $readinessService,
    ) {}

    // -----------------------------------------------------------------------
    // User analytics
    // -----------------------------------------------------------------------

    public function userAnalytics(int $userId, ?int $siteId): array
    {
        $profile = $this->profileService->buildProfile($userId, $siteId);

        // Query 1 — progress aggregate
        $progressRow = EducationUserProgress::query()
            ->where('user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [EducationUserProgress::STATUS_COMPLETED])
            ->first();

        $totalProgress = (int) ($progressRow->total ?? 0);
        $completedProgress = (int) ($progressRow->completed ?? 0);
        $completionRate = $totalProgress > 0 ? round($completedProgress / $totalProgress, 4) : 0.0;

        // Query 2 — recommendation aggregate
        $recRow = EducationRecommendation::forUser($userId)
            ->forSite($siteId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN dismissed_at IS NOT NULL THEN 1 ELSE 0 END) as dismissed,
                SUM(CASE WHEN dismissed_at IS NULL AND completed_at IS NULL THEN 1 ELSE 0 END) as active
            ')
            ->first();

        $totalRecs       = (int) ($recRow->total ?? 0);
        $completedRecs   = (int) ($recRow->completed ?? 0);
        $dismissedRecs   = (int) ($recRow->dismissed ?? 0);
        $activeRecs      = (int) ($recRow->active ?? 0);
        $resolved        = $completedRecs + $dismissedRecs;
        $acceptanceRate  = $resolved > 0 ? round($completedRecs / $resolved, 4) : 0.0;

        return [
            'profile_scores' => [
                'skill_score'       => $profile->skillScore,
                'onboarding_score'  => $profile->onboardingScore,
                'adoption_score'    => $profile->adoptionScore,
                'engagement_score'  => $profile->engagementScore,
                'overall_score'     => $profile->overallScore(),
                'strongest_modules' => $profile->strongestModules,
                'weakest_modules'   => $profile->weakestModules,
                'last_activity_at'  => $profile->lastActivityAt?->toIso8601String(),
            ],
            'completion_rate'                 => $completionRate,
            'recommendation_acceptance_rate'  => $acceptanceRate,
            'recommendations' => [
                'total'     => $totalRecs,
                'active'    => $activeRecs,
                'completed' => $completedRecs,
                'dismissed' => $dismissedRecs,
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Site analytics
    // -----------------------------------------------------------------------

    public function siteAnalytics(?int $siteId): array
    {
        // Query 1 — module adoption: unique users per module
        $adoptionRows = EducationFeatureUsage::query()
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->selectRaw('module_slug, COUNT(DISTINCT user_id) as user_count')
            ->groupBy('module_slug')
            ->get();

        $moduleAdoption = $adoptionRows->pluck('user_count', 'module_slug')->toArray();

        // Query 2 — gap distribution approximation: for each module, count users below 50% usage depth
        // Approximated from feature usage ratio rather than per-user gap service calls (avoids O(n) service calls).
        $gapRows = EducationFeatureUsage::query()
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->selectRaw('module_slug, AVG(usage_count) as avg_usage')
            ->groupBy('module_slug')
            ->get();

        // Approximate gap level from avg_usage per module
        $gapDistribution = ['Critical' => 0, 'High' => 0, 'Medium' => 0, 'Low' => 0];
        foreach ($gapRows as $row) {
            $level = $this->approximateGapLevel((float) $row->avg_usage);
            $gapDistribution[$level]++;
        }

        // Content readiness — uses ContentReadinessService batch (5 queries)
        $readinessModules = $this->readinessService->evaluate($siteId);
        $readinessScores  = $readinessModules->pluck('readiness_score');

        $avgReadiness    = $readinessScores->isNotEmpty() ? round($readinessScores->avg(), 2) : 0.0;
        $aboveThreshold  = $readinessScores->filter(fn($s) => $s >= 75)->count();
        $belowThreshold  = $readinessScores->filter(fn($s) => $s < 25)->count();

        return [
            'module_adoption'     => $moduleAdoption,
            'gap_distribution'    => $gapDistribution,
            'readiness_distribution' => [
                'average_score'       => $avgReadiness,
                'modules_above_75'    => $aboveThreshold,
                'modules_below_25'    => $belowThreshold,
                'total_modules'       => $readinessScores->count(),
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Platform analytics
    // -----------------------------------------------------------------------

    public function platformAnalytics(): array
    {
        // Query 1 — global unique users with any feature usage
        $globalAdoption = EducationFeatureUsage::query()
            ->selectRaw('COUNT(DISTINCT user_id) as user_count')
            ->value('user_count') ?? 0;

        // Query 2 — content coverage: modules with ≥1 published article / total distinct modules
        $modulesWithContent = EducationArticle::published()
            ->whereNotNull('module_slug')
            ->distinct()
            ->count('module_slug');

        $totalModules = EducationFeature::query()
            ->whereNotNull('module_slug')
            ->distinct()
            ->count('module_slug');

        $coveragePercent = $totalModules > 0
            ? round(($modulesWithContent / $totalModules) * 100, 2)
            : 0.0;

        // Query 3 — recommendation effectiveness: completed / (completed + dismissed)
        $recRow = EducationRecommendation::query()
            ->selectRaw('
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN dismissed_at IS NOT NULL THEN 1 ELSE 0 END) as dismissed
            ')
            ->first();

        $completed      = (int) ($recRow->completed ?? 0);
        $dismissed      = (int) ($recRow->dismissed ?? 0);
        $resolved       = $completed + $dismissed;
        $effectiveness  = $resolved > 0 ? round($completed / $resolved, 4) : 0.0;

        return [
            'global_adoption'             => (int) $globalAdoption,
            'content_coverage_percent'    => $coveragePercent,
            'recommendation_effectiveness' => $effectiveness,
            'modules_with_content'        => $modulesWithContent,
            'total_modules'               => $totalModules,
        ];
    }

    // -----------------------------------------------------------------------
    // Generation analytics
    // -----------------------------------------------------------------------

    public function generationAnalytics(?int $siteId): array
    {
        // Query 1 — generation log aggregate: total, success, failed
        $logRow = EducationGenerationLog::forSite($siteId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as validation_failed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as provider_error
            ', [
                EducationGenerationLog::STATUS_SUCCESS,
                EducationGenerationLog::STATUS_VALIDATION_FAILED,
                EducationGenerationLog::STATUS_PROVIDER_ERROR,
            ])
            ->first();

        $total           = (int) ($logRow->total ?? 0);
        $successCount    = (int) ($logRow->success_count ?? 0);
        $successRate     = $total > 0 ? round($successCount / $total, 4) : 0.0;

        // Query 2 — provider usage breakdown
        $providerRows = EducationGenerationLog::forSite($siteId)
            ->selectRaw('provider, COUNT(*) as cnt')
            ->groupBy('provider')
            ->get();

        $providerUsage = $providerRows->pluck('cnt', 'provider')->map(fn($v) => (int) $v)->toArray();

        // Query 3 — approved drafts (auto_generated articles that are now published)
        $approved = EducationArticle::query()
            ->where('auto_generated', true)
            ->where('status', EducationArticle::STATUS_PUBLISHED)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->count();

        // Query 4 — rejected drafts (auto_generated articles that are archived)
        $rejected = EducationArticle::query()
            ->where('auto_generated', true)
            ->where('status', EducationArticle::STATUS_ARCHIVED)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->count();

        return [
            'drafts_created'          => $successCount,
            'drafts_approved'         => $approved,
            'drafts_rejected'         => $rejected,
            'generation_success_rate' => $successRate,
            'provider_usage'          => $providerUsage,
        ];
    }

    // -----------------------------------------------------------------------

    /**
     * Approximate gap level from average usage count per module.
     * Used in siteAnalytics() to avoid per-user gap service calls.
     *
     * avg_usage < 2  → Critical (almost no usage)
     * avg_usage < 5  → High
     * avg_usage < 15 → Medium
     * avg_usage >= 15 → Low
     */
    private function approximateGapLevel(float $avgUsage): string
    {
        return match (true) {
            $avgUsage < 2  => KnowledgeGapServiceInterface::PRIORITY_CRITICAL,
            $avgUsage < 5  => KnowledgeGapServiceInterface::PRIORITY_HIGH,
            $avgUsage < 15 => KnowledgeGapServiceInterface::PRIORITY_MEDIUM,
            default        => KnowledgeGapServiceInterface::PRIORITY_LOW,
        };
    }
}
