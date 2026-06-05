<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;

class KnowledgeGapService implements KnowledgeGapServiceInterface
{
    private const CACHE_TTL_SECONDS = 900; // 15 minutes — PA-09

    // Minimum articles per module to consider documentation adequate
    private const ARTICLE_ADEQUACY_THRESHOLD = 2;

    // Weight distribution for gap calculation (must sum to 1.0)
    private const WEIGHT_USAGE      = 0.40;
    private const WEIGHT_COMPLETION = 0.35;
    private const WEIGHT_DOCS       = 0.25;

    // -----------------------------------------------------------------------
    // Public interface
    // -----------------------------------------------------------------------

    public function detectForUser(int $userId, ?int $siteId): Collection
    {
        // PA-09: 15-minute cache keyed per user + site
        return Cache::remember(
            $this->cacheKey($userId, $siteId),
            self::CACHE_TTL_SECONDS,
            fn() => $this->compute($userId, $siteId)
        );
    }

    public function invalidate(int $userId, ?int $siteId): void
    {
        Cache::forget($this->cacheKey($userId, $siteId));
    }

    // -----------------------------------------------------------------------
    // Core computation
    // -----------------------------------------------------------------------

    private function compute(int $userId, ?int $siteId): Collection
    {
        $allFeatures = EducationFeature::forSite($siteId)->get()->groupBy('module_slug');

        // F-03: explicit whereNull when siteId=null — prevents cross-tenant usage leakage
        $usageRows = EducationFeatureUsage::where('user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->get()
            ->groupBy('module_slug');

        $articleCounts   = $this->articleCountsByModule($siteId);
        $completionRates = $this->completionRatesByModule($userId, $siteId);

        return $allFeatures->map(function (Collection $features, string $moduleSlug) use (
            $usageRows, $articleCounts, $completionRates
        ) {
            $total      = $features->count();
            $used       = $usageRows->get($moduleSlug, collect())->count();
            $usageScore = $total > 0 ? ($used / $total) * 100 : 0.0;

            $completionScore = $completionRates->get($moduleSlug, 0.0);
            $docsScore       = $this->documentationScore($moduleSlug, $articleCounts);

            $gapScore = 100 - (
                ($usageScore     * self::WEIGHT_USAGE)
                + ($completionScore * self::WEIGHT_COMPLETION)
                + ($docsScore       * self::WEIGHT_DOCS)
            );

            $clamped = round(max(0.0, min(100.0, $gapScore)), 2);

            return [
                'module_slug' => $moduleSlug,
                'gap_score'   => $clamped,
                'priority'    => $this->resolvePriority($clamped), // use clamped value
            ];
        })->values()->sortByDesc('gap_score')->values();
    }

    // -----------------------------------------------------------------------
    // Data loading helpers
    // -----------------------------------------------------------------------

    private function articleCountsByModule(?int $siteId): Collection
    {
        return EducationArticle::query()
            ->select('module_slug')
            ->selectRaw('COUNT(*) as cnt')
            ->published()
            ->when($siteId !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->whereNotNull('module_slug')
            ->groupBy('module_slug')
            ->pluck('cnt', 'module_slug');
    }

    private function completionRatesByModule(int $userId, ?int $siteId): Collection
    {
        $rows = EducationUserProgress::query()
            ->where('edu_user_progress.user_id', $userId)
            ->when($siteId !== null, fn($q) => $q->where('edu_user_progress.site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('edu_user_progress.site_id'))
            ->where('progressable_type', 'LIKE', '%Article%')
            // PA-07: exclude soft-deleted articles from completion rate calculations
            ->join('edu_articles', function ($join) {
                $join->on('edu_articles.id', '=', 'edu_user_progress.progressable_id')
                     ->whereNull('edu_articles.deleted_at');
            })
            ->whereNotNull('edu_articles.module_slug')
            ->selectRaw(
                'edu_articles.module_slug,
                 SUM(CASE WHEN edu_user_progress.status = ? THEN 1 ELSE 0 END) as done,
                 COUNT(*) as total',
                [EducationUserProgress::STATUS_COMPLETED]
            )
            ->groupBy('edu_articles.module_slug')
            ->get();

        $result = collect();
        foreach ($rows as $row) {
            $rate = $row->total > 0 ? ($row->done / $row->total) * 100 : 0.0;
            $result->put($row->module_slug, round($rate, 2));
        }

        return $result;
    }

    private function documentationScore(string $moduleSlug, Collection $articleCounts): float
    {
        $count = $articleCounts->get($moduleSlug, 0);

        if ($count >= self::ARTICLE_ADEQUACY_THRESHOLD) {
            return 100.0;
        }

        return round(($count / self::ARTICLE_ADEQUACY_THRESHOLD) * 100, 2);
    }

    private function resolvePriority(float $gapScore): string
    {
        return match (true) {
            $gapScore >= 75 => self::PRIORITY_CRITICAL,
            $gapScore >= 50 => self::PRIORITY_HIGH,
            $gapScore >= 25 => self::PRIORITY_MEDIUM,
            default         => self::PRIORITY_LOW,
        };
    }

    private function cacheKey(int $userId, ?int $siteId): string
    {
        return "edu_gap.{$userId}." . ($siteId ?? 'null');
    }
}
