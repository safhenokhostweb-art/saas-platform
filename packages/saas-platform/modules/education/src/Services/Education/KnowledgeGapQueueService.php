<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;

/**
 * Builds an in-memory remediation queue from KnowledgeGapService output.
 *
 * Only Critical and High priority gaps enter the queue.
 * No AI generation — pure queue building.
 */
class KnowledgeGapQueueService implements KnowledgeGapQueueServiceInterface
{
    public function __construct(
        private readonly KnowledgeGapServiceInterface $gapService,
    ) {}

    // -----------------------------------------------------------------------
    // Public interface
    // -----------------------------------------------------------------------

    public function buildForUser(int $userId, ?int $siteId): Collection
    {
        $gaps = $this->gapService->detectForUser($userId, $siteId);
        $now  = Carbon::now();

        return $gaps
            ->filter(fn(array $gap) => in_array($gap['priority'], self::QUEUE_PRIORITIES, true))
            ->map(fn(array $gap) => new KnowledgeGapQueueItemDto(
                userId:     $userId,
                siteId:     $siteId,   // null preserved — represents global/platform-admin scope
                moduleSlug: $gap['module_slug'],
                gapScore:   $gap['gap_score'],
                priority:   $gap['priority'],
                createdAt:  $now,
            ))
            ->sortByDesc(fn(KnowledgeGapQueueItemDto $item) => $item->gapScore)
            ->values();
    }

    public function buildForSite(?int $siteId): Collection
    {
        $userIds = $this->activeUserIds($siteId);
        $queue   = collect();

        foreach ($userIds as $userId) {
            $queue = $queue->merge($this->buildForUser($userId, $siteId));
        }

        // Sort combined queue: Critical first, then High; within priority by gap_score desc
        return $queue
            ->sortByDesc(fn(KnowledgeGapQueueItemDto $item) => $item->gapScore)
            ->sortBy(fn(KnowledgeGapQueueItemDto $item) => $item->priority === KnowledgeGapServiceInterface::PRIORITY_CRITICAL ? 0 : 1)
            ->values();
    }

    // -----------------------------------------------------------------------

    private function activeUserIds(?int $siteId): Collection
    {
        return EducationFeatureUsage::select('user_id')
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->distinct()
            ->pluck('user_id');
    }
}
