<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentReadinessServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;

class ContentReadinessService implements ContentReadinessServiceInterface
{
    // Weights — must sum to 100
    private const WEIGHT_ARTICLES     = 40;
    private const WEIGHT_WALKTHROUGHS = 30;
    private const WEIGHT_PATHS        = 20;
    private const WEIGHT_FRESHNESS    = 10;

    // Documentation freshness thresholds (days)
    private const FRESH_DAYS  = 30;
    private const STALE_DAYS  = 60;

    // Minimum counts to consider coverage adequate
    private const MIN_ARTICLES     = 2;
    private const MIN_WALKTHROUGHS = 1;
    private const MIN_PATHS        = 1;

    // -----------------------------------------------------------------------
    // Public interface
    // -----------------------------------------------------------------------

    /**
     * Evaluate all modules for a site.
     *
     * F-02: Batch strategy — 5 total queries regardless of module count:
     *   1. Distinct module slugs
     *   2. Article counts  (GROUP BY module_slug)
     *   3. Walkthrough counts (GROUP BY module_slug)
     *   4. Learning-path counts (GROUP BY module_slug)
     *   5. Freshness data (GROUP BY module_slug)
     */
    public function evaluate(?int $siteId): Collection
    {
        $modules = EducationFeature::forSite($siteId)
            ->select('module_slug')
            ->distinct()
            ->pluck('module_slug');

        if ($modules->isEmpty()) {
            return collect();
        }

        // F-02: Pre-load all aggregates in 4 batch queries
        $articleCounts     = $this->batchArticleCounts($modules, $siteId);
        $walkthroughCounts = $this->batchWalkthroughCounts($modules, $siteId);
        $pathCounts        = $this->batchPathCounts($modules, $siteId);
        $freshnessMap      = $this->batchFreshnessData($modules, $siteId);

        return $modules->map(function (string $slug) use ($articleCounts, $walkthroughCounts, $pathCounts, $freshnessMap) {
            return $this->computeModuleResult(
                moduleSlug:       $slug,
                articleCount:     (int) ($articleCounts->get($slug, 0)),
                walkthroughCount: (int) ($walkthroughCounts->get($slug, 0)),
                pathCount:        (int) ($pathCounts->get($slug, 0)),
                lastGenerated:    $freshnessMap->get($slug),
            );
        })
            ->sortByDesc('readiness_score')
            ->values();
    }

    /**
     * Evaluate a single module.
     * PA-08: Article count computed once — no redundant second query.
     */
    public function evaluateModule(string $moduleSlug, ?int $siteId): array
    {
        return $this->computeModuleResult(
            moduleSlug:       $moduleSlug,
            articleCount:     $this->queryArticleCount($moduleSlug, $siteId),
            walkthroughCount: $this->queryWalkthroughCount($moduleSlug, $siteId),
            pathCount:        $this->queryPathCount($moduleSlug, $siteId),
            lastGenerated:    $this->queryLastGenerated($moduleSlug, $siteId),
        );
    }

    // -----------------------------------------------------------------------
    // Core computation (pure — no DB calls)
    // -----------------------------------------------------------------------

    /**
     * Shared computation kernel used by both evaluate() and evaluateModule().
     *
     * F-01: readiness_score = 0–100 (no /100 division — weights already sum to 100).
     * PA-08: articleCount threaded in — collectMissing never re-queries.
     */
    private function computeModuleResult(
        string  $moduleSlug,
        int     $articleCount,
        int     $walkthroughCount,
        int     $pathCount,
        ?string $lastGenerated,
    ): array {
        $articleScore     = $this->articleScoreFromCount($articleCount);
        $walkthroughScore = $walkthroughCount >= self::MIN_WALKTHROUGHS ? 1.0 : 0.0;
        $pathScore        = $pathCount        >= self::MIN_PATHS        ? 1.0 : 0.0;
        $freshnessScore   = $this->freshnessScoreFromDate($lastGenerated);

        // F-01: Weights sum to 100; components are 0.0/0.5/1.0
        // Result range: 0.0 (all components 0) to 100.0 (all components 1.0)
        $readiness = round(
            ($articleScore     * self::WEIGHT_ARTICLES)
            + ($walkthroughScore * self::WEIGHT_WALKTHROUGHS)
            + ($pathScore        * self::WEIGHT_PATHS)
            + ($freshnessScore   * self::WEIGHT_FRESHNESS),
            2
        );

        return [
            'module_slug'     => $moduleSlug,
            'readiness_score' => $readiness,
            // PA-08: articleCount passed in — no second article query
            'missing_content' => $this->collectMissing(
                $moduleSlug, $articleCount, $walkthroughScore, $pathScore, $freshnessScore
            ),
        ];
    }

    // -----------------------------------------------------------------------
    // Batch query methods (used by evaluate())
    // -----------------------------------------------------------------------

    private function batchArticleCounts(Collection $moduleSlugs, ?int $siteId): Collection
    {
        return EducationArticle::published()
            ->whereIn('module_slug', $moduleSlugs)
            ->when($siteId !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->selectRaw('module_slug, COUNT(*) as cnt')
            ->groupBy('module_slug')
            ->pluck('cnt', 'module_slug');
    }

    private function batchWalkthroughCounts(Collection $moduleSlugs, ?int $siteId): Collection
    {
        return EducationWalkthrough::query()
            ->whereIn('module_slug', $moduleSlugs)
            ->where('is_active', true)
            ->when($siteId !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->selectRaw('module_slug, COUNT(*) as cnt')
            ->groupBy('module_slug')
            ->pluck('cnt', 'module_slug');
    }

    private function batchPathCounts(Collection $moduleSlugs, ?int $siteId): Collection
    {
        return EducationLearningPath::active()
            ->whereIn('module_slug', $moduleSlugs)
            ->forSite($siteId)
            ->selectRaw('module_slug, COUNT(*) as cnt')
            ->groupBy('module_slug')
            ->pluck('cnt', 'module_slug');
    }

    private function batchFreshnessData(Collection $moduleSlugs, ?int $siteId): Collection
    {
        return EducationArticle::published()
            ->whereIn('module_slug', $moduleSlugs)
            ->when($siteId !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->whereNotNull('last_generated_at')
            ->selectRaw('module_slug, MAX(last_generated_at) as last_generated')
            ->groupBy('module_slug')
            ->pluck('last_generated', 'module_slug');
    }

    // -----------------------------------------------------------------------
    // Individual query methods (used by evaluateModule())
    // -----------------------------------------------------------------------

    private function queryArticleCount(string $moduleSlug, ?int $siteId): int
    {
        return EducationArticle::published()
            ->where('module_slug', $moduleSlug)
            ->when($siteId !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->count();
    }

    private function queryWalkthroughCount(string $moduleSlug, ?int $siteId): int
    {
        return EducationWalkthrough::query()
            ->where('module_slug', $moduleSlug)
            ->where('is_active', true)
            ->when($siteId !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->count();
    }

    private function queryPathCount(string $moduleSlug, ?int $siteId): int
    {
        return EducationLearningPath::active()
            ->where('module_slug', $moduleSlug)
            ->forSite($siteId)
            ->count();
    }

    private function queryLastGenerated(string $moduleSlug, ?int $siteId): ?string
    {
        return EducationArticle::published()
            ->where('module_slug', $moduleSlug)
            ->when($siteId !== null, fn($q) => $q->where(fn($q2) => $q2->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->whereNotNull('last_generated_at')
            ->max('last_generated_at');
    }

    // -----------------------------------------------------------------------
    // Score helpers (pure)
    // -----------------------------------------------------------------------

    private function articleScoreFromCount(int $count): float
    {
        return match (true) {
            $count >= self::MIN_ARTICLES => 1.0,
            $count === 1                 => 0.5,
            default                      => 0.0,
        };
    }

    private function freshnessScoreFromDate(?string $lastGenerated): float
    {
        if (! $lastGenerated) {
            return 0.0;
        }

        $days = now()->diffInDays(Carbon::parse($lastGenerated));

        return match (true) {
            $days <= self::FRESH_DAYS => 1.0,
            $days <= self::STALE_DAYS => 0.5,
            default                   => 0.0,
        };
    }

    // -----------------------------------------------------------------------
    // Missing-content label builder (pure — no DB calls)
    // PA-08: takes pre-computed $articleCount instead of re-querying
    // -----------------------------------------------------------------------

    private function collectMissing(
        string $moduleSlug,
        int    $articleCount,
        float  $walkthroughScore,
        float  $pathScore,
        float  $freshnessScore,
    ): array {
        $missing = [];

        if ($articleCount < self::MIN_ARTICLES) {
            $needed    = self::MIN_ARTICLES - $articleCount;
            $missing[] = "نیاز به {$needed} مقاله منتشرشده برای ماژول {$moduleSlug}";
        }

        if ($walkthroughScore < 1.0) {
            $missing[] = "هیچ walkthrough فعالی برای ماژول {$moduleSlug} وجود ندارد";
        }

        if ($pathScore < 1.0) {
            $missing[] = "هیچ مسیر یادگیری فعالی برای ماژول {$moduleSlug} وجود ندارد";
        }

        if ($freshnessScore < 1.0) {
            $label     = $freshnessScore === 0.0
                ? 'قدیمی (بیش از 60 روز)'
                : 'نیمه‌قدیمی (بیش از 30 روز)';
            $missing[] = "مستندات ماژول {$moduleSlug} {$label} است";
        }

        return $missing;
    }
}
