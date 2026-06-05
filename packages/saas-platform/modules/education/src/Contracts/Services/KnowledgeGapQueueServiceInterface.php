<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;

interface KnowledgeGapQueueServiceInterface
{
    /**
     * Build a remediation queue for a single user — Critical + High gaps only.
     *
     * @return Collection<int, \SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto>
     */
    public function buildForUser(int $userId, ?int $siteId): Collection;

    /**
     * Build a remediation queue for every active user on a site.
     *
     * @return Collection<int, \SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto>
     */
    public function buildForSite(?int $siteId): Collection;

    /**
     * Priorities surfaced into the queue.
     */
    public const QUEUE_PRIORITIES = [
        KnowledgeGapServiceInterface::PRIORITY_CRITICAL,
        KnowledgeGapServiceInterface::PRIORITY_HIGH,
    ];
}
