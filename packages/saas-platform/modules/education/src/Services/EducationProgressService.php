<?php

namespace SaaSPlatform\Modules\Education\Services;

use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationProgressRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;

class EducationProgressService
{
    public function __construct(
        private readonly EducationProgressRepositoryInterface $progress,
    ) {}

    public function markArticleViewed(int $userId, int $siteId, EducationArticle $article): EducationUserProgress
    {
        $existing = $this->progress->findProgress(
            $userId, $siteId, $article->id, EducationArticle::class
        );

        if ($existing?->isCompleted()) {
            return $existing;
        }

        return $this->progress->upsertProgress(
            $userId, $siteId, $article->id, EducationArticle::class,
            [
                'status'           => EducationUserProgress::STATUS_COMPLETED,
                'progress_percent' => 100,
                'started_at'       => $existing?->started_at ?? now(),
                'completed_at'     => now(),
            ]
        );
    }

    public function advanceLearningPath(int $userId, int $siteId, EducationLearningPath $path, int $completedStepNumber): EducationUserProgress
    {
        $total    = $path->steps()->count();
        $required = $path->steps()->where('is_required', true)->count();
        $done     = min($completedStepNumber, $total);
        $percent  = $total > 0 ? (int) round($done / $total * 100) : 0;
        $status   = $done >= $total
            ? EducationUserProgress::STATUS_COMPLETED
            : EducationUserProgress::STATUS_IN_PROGRESS;

        return $this->progress->upsertProgress(
            $userId, $siteId, $path->id, EducationLearningPath::class,
            [
                'status'           => $status,
                'progress_percent' => $percent,
                'started_at'       => now(),
                'completed_at'     => $status === EducationUserProgress::STATUS_COMPLETED ? now() : null,
            ]
        );
    }

    public function advanceWalkthrough(int $userId, int $siteId, EducationWalkthrough $walkthrough, int $step): EducationWalkthroughProgress
    {
        $totalSteps = $walkthrough->steps()->count();
        $status = $step >= $totalSteps
            ? EducationWalkthroughProgress::STATUS_COMPLETED
            : EducationWalkthroughProgress::STATUS_IN_PROGRESS;

        return $this->progress->upsertWalkthroughProgress(
            $userId, $siteId, $walkthrough->id,
            [
                'current_step' => $step,
                'status'       => $status,
                'completed_at' => $status === EducationWalkthroughProgress::STATUS_COMPLETED ? now() : null,
            ]
        );
    }

    public function dismissWalkthrough(int $userId, int $siteId, EducationWalkthrough $walkthrough): EducationWalkthroughProgress
    {
        $existing = $this->progress->findWalkthroughProgress($userId, $siteId, $walkthrough->id);
        if ($existing?->isCompleted()) {
            return $existing;
        }

        return $this->progress->upsertWalkthroughProgress(
            $userId, $siteId, $walkthrough->id,
            ['status' => EducationWalkthroughProgress::STATUS_DISMISSED]
        );
    }

    public function getProgress(int $userId, int $siteId, int $progressableId, string $progressableType): ?EducationUserProgress
    {
        return $this->progress->findProgress($userId, $siteId, $progressableId, $progressableType);
    }

    public function completedCount(int $userId, int $siteId): int
    {
        return $this->progress->userCompletedCount($userId, $siteId);
    }
}
