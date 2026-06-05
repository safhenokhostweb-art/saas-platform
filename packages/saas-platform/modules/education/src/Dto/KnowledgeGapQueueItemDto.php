<?php

namespace SaaSPlatform\Modules\Education\Dto;

use Carbon\Carbon;

final class KnowledgeGapQueueItemDto
{
    public function __construct(
        public readonly int    $userId,
        public readonly ?int   $siteId,   // null = global/platform-admin context
        public readonly string $moduleSlug,
        public readonly float  $gapScore,
        public readonly string $priority,
        public readonly Carbon $createdAt,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id'     => $this->userId,
            'site_id'     => $this->siteId,
            'module_slug' => $this->moduleSlug,
            'gap_score'   => $this->gapScore,
            'priority'    => $this->priority,
            'created_at'  => $this->createdAt->toIso8601String(),
        ];
    }
}
