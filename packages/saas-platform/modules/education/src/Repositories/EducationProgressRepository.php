<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationProgressRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;

class EducationProgressRepository implements EducationProgressRepositoryInterface
{
    public function findProgress(int $userId, int $siteId, int $progressableId, string $progressableType): ?EducationUserProgress
    {
        return EducationUserProgress::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('progressable_id', $progressableId)
            ->where('progressable_type', $progressableType)
            ->first();
    }

    public function upsertProgress(int $userId, int $siteId, int $progressableId, string $progressableType, array $data): EducationUserProgress
    {
        $record = $this->findProgress($userId, $siteId, $progressableId, $progressableType);

        if ($record) {
            $record->update($data);
            return $record->fresh();
        }

        return EducationUserProgress::create(array_merge($data, [
            'user_id'          => $userId,
            'site_id'          => $siteId,
            'progressable_id'  => $progressableId,
            'progressable_type'=> $progressableType,
        ]));
    }

    public function findWalkthroughProgress(int $userId, int $siteId, int $walkthroughId): ?EducationWalkthroughProgress
    {
        return EducationWalkthroughProgress::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('edu_walkthrough_id', $walkthroughId)
            ->first();
    }

    public function upsertWalkthroughProgress(int $userId, int $siteId, int $walkthroughId, array $data): EducationWalkthroughProgress
    {
        $record = $this->findWalkthroughProgress($userId, $siteId, $walkthroughId);

        if ($record) {
            $record->update($data);
            return $record->fresh();
        }

        return EducationWalkthroughProgress::create(array_merge($data, [
            'user_id'              => $userId,
            'site_id'              => $siteId,
            'edu_walkthrough_id'   => $walkthroughId,
        ]));
    }

    public function userCompletedCount(int $userId, int $siteId): int
    {
        return EducationUserProgress::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->where('status', EducationUserProgress::STATUS_COMPLETED)
            ->count();
    }
}
