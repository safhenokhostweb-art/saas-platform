<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationRecommendationRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;

class EducationRecommendationRepository implements EducationRecommendationRepositoryInterface
{
    public function activeForUser(int $userId, ?int $siteId, int $limit = 5): Collection
    {
        return EducationRecommendation::forUser($userId)
            ->forSite($siteId)
            ->active()
            ->orderBy('priority')
            ->limit($limit)
            ->with(['article', 'walkthrough', 'learningPath'])
            ->get();
    }

    public function create(array $data): EducationRecommendation
    {
        return EducationRecommendation::create($data);
    }

    public function dismiss(int $recommendationId, int $userId): bool
    {
        return (bool) EducationRecommendation::forUser($userId)
            ->where('id', $recommendationId)
            ->whereNull('dismissed_at')
            ->update(['dismissed_at' => now()]);
    }

    public function complete(int $recommendationId, int $userId): bool
    {
        return (bool) EducationRecommendation::forUser($userId)
            ->where('id', $recommendationId)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);
    }

    public function existsForUser(int $userId, ?int $siteId, string $type, ?int $articleId, ?int $walkthroughId, ?int $pathId): bool
    {
        return EducationRecommendation::forUser($userId)
            ->forSite($siteId)
            ->where('recommendation_type', $type)
            ->where('article_id', $articleId)
            ->where('walkthrough_id', $walkthroughId)
            ->where('learning_path_id', $pathId)
            ->active()
            ->exists();
    }

    public function countActiveForUser(int $userId, ?int $siteId): int
    {
        return EducationRecommendation::forUser($userId)->forSite($siteId)->active()->count();
    }

    public function purgeCompleted(int $daysOld = 30): int
    {
        return EducationRecommendation::where(function ($q) use ($daysOld) {
            $q->where('completed_at', '<', now()->subDays($daysOld))
              ->orWhere('dismissed_at', '<', now()->subDays($daysOld));
        })->delete();
    }
}
