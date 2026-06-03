<?php

namespace SaaSPlatform\Modules\Education\Contracts\Repositories;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;

interface EducationRecommendationRepositoryInterface
{
    public function activeForUser(int $userId, ?int $siteId, int $limit = 5): Collection;
    public function create(array $data): EducationRecommendation;
    public function dismiss(int $recommendationId, int $userId): bool;
    public function complete(int $recommendationId, int $userId): bool;
    public function existsForUser(int $userId, ?int $siteId, string $type, ?int $articleId, ?int $walkthroughId, ?int $pathId): bool;
    public function countActiveForUser(int $userId, ?int $siteId): int;
    public function purgeCompleted(int $daysOld = 30): int;
}
