<?php

namespace SaaSPlatform\Modules\Education\Contracts\Repositories;

use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;

interface EducationProgressRepositoryInterface
{
    public function findProgress(int $userId, int $siteId, int $progressableId, string $progressableType): ?EducationUserProgress;
    public function upsertProgress(int $userId, int $siteId, int $progressableId, string $progressableType, array $data): EducationUserProgress;
    public function findWalkthroughProgress(int $userId, int $siteId, int $walkthroughId): ?EducationWalkthroughProgress;
    public function upsertWalkthroughProgress(int $userId, int $siteId, int $walkthroughId, array $data): EducationWalkthroughProgress;
    public function userCompletedCount(int $userId, int $siteId): int;
}
