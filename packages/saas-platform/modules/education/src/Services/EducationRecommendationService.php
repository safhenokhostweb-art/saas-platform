<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationRecommendationRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationRecommendationServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;

class EducationRecommendationService implements EducationRecommendationServiceInterface
{
    /** Features with fewer visits than this are considered "low adoption". */
    private const LOW_USAGE_THRESHOLD = 3;

    public function __construct(
        private readonly EducationFeatureUsageRepositoryInterface  $usage,
        private readonly EducationRecommendationRepositoryInterface $recommendations,
    ) {}

    public function generateForUser(int $userId, ?int $siteId): int
    {
        // C-03: Enforce max_active_per_user — never exceed the configured cap.
        $maxActive = (int) config('education.recommendation.max_active_per_user', 10);
        $current   = $this->recommendations->countActiveForUser($userId, $siteId);

        if ($current >= $maxActive) {
            return 0;
        }

        $created = 0;
        $created += $this->recommendUnusedFeatures($userId, $siteId);
        $created += $this->recommendAvailableArticles($userId, $siteId);
        $created += $this->recommendLearningPaths($userId, $siteId);

        return $created;
    }

    public function activeForUser(int $userId, ?int $siteId, int $limit = 5): Collection
    {
        return $this->recommendations->activeForUser($userId, $siteId, $limit);
    }

    public function dismiss(int $recommendationId, int $userId): bool
    {
        return $this->recommendations->dismiss($recommendationId, $userId);
    }

    public function complete(int $recommendationId, int $userId): bool
    {
        return $this->recommendations->complete($recommendationId, $userId);
    }

    // -----------------------------------------------------------------------
    // Private rule engine
    // -----------------------------------------------------------------------

    private function recommendUnusedFeatures(int $userId, ?int $siteId): int
    {
        $created  = 0;
        $unused   = $this->usage->neverUsedFeatures($userId, $siteId)->take(5);

        foreach ($unused as $feature) {
            $article = EducationArticle::published()
                ->where('module_slug', $feature->module_slug)
                ->first();

            if (! $article) {
                continue;
            }

            if ($this->recommendations->existsForUser($userId, $siteId, EducationRecommendation::TYPE_FEATURE_INTRO, $article->id, null, null)) {
                continue;
            }

            $this->recommendations->create([
                'user_id'             => $userId,
                'site_id'             => $siteId,
                'recommendation_type' => EducationRecommendation::TYPE_FEATURE_INTRO,
                'article_id'          => $article->id,
                'priority'            => 20,
                'reason'              => "شما هنوز از «{$feature->feature_name}» استفاده نکرده‌اید. این راهنما کمک می‌کند شروع کنید.",
            ]);
            $created++;
        }

        return $created;
    }

    private function recommendAvailableArticles(int $userId, ?int $siteId): int
    {
        $created = 0;

        // C-05: Use per-user unused features — not site-wide aggregate.
        // neverUsedFeatures() is scoped to $userId so each user sees personalised recommendations.
        $lowUsage = $this->usage->neverUsedFeatures($userId, $siteId)->take(3);

        foreach ($lowUsage as $feature) {
            $article = EducationArticle::published()
                ->where('route_pattern', $feature->route_name)
                ->first();

            if (! $article) {
                continue;
            }

            if ($this->recommendations->existsForUser($userId, $siteId, EducationRecommendation::TYPE_ARTICLE, $article->id, null, null)) {
                continue;
            }

            $this->recommendations->create([
                'user_id'             => $userId,
                'site_id'             => $siteId,
                'recommendation_type' => EducationRecommendation::TYPE_ARTICLE,
                'article_id'          => $article->id,
                'priority'            => 40,
                'reason'              => "مقاله آموزشی برای «{$feature->feature_name}» موجود است.",
            ]);
            $created++;
        }

        return $created;
    }

    private function recommendLearningPaths(int $userId, ?int $siteId): int
    {
        $created = 0;

        $paths = EducationLearningPath::active()
            ->forSite($siteId)
            ->where('path_type', EducationLearningPath::TYPE_ROLE)
            ->limit(2)
            ->get();

        foreach ($paths as $path) {
            if ($this->recommendations->existsForUser($userId, $siteId, EducationRecommendation::TYPE_LEARNING_PATH, null, null, $path->id)) {
                continue;
            }

            $this->recommendations->create([
                'user_id'             => $userId,
                'site_id'             => $siteId,
                'recommendation_type' => EducationRecommendation::TYPE_LEARNING_PATH,
                'learning_path_id'    => $path->id,
                'priority'            => 60,
                'reason'              => "مسیر یادگیری «{$path->title}» برای شما توصیه می‌شود.",
            ]);
            $created++;
        }

        return $created;
    }
}
