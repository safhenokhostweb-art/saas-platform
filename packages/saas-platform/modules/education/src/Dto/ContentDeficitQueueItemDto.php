<?php

namespace SaaSPlatform\Modules\Education\Dto;

final class ContentDeficitQueueItemDto
{
    public function __construct(
        public readonly string $moduleSlug,
        public readonly string $deficitType,
        public readonly float  $readinessScore,
        public readonly string $priority,
    ) {}

    public function toArray(): array
    {
        return [
            'module_slug'     => $this->moduleSlug,
            'deficit_type'    => $this->deficitType,
            'readiness_score' => $this->readinessScore,
            'priority'        => $this->priority,
        ];
    }
}
