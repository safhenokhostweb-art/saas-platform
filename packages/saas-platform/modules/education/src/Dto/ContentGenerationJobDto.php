<?php

namespace SaaSPlatform\Modules\Education\Dto;

final class ContentGenerationJobDto
{
    public function __construct(
        public readonly string $moduleSlug,
        public readonly string $contentType,
        public readonly string $priority,
        public readonly ?int   $siteId,
        public readonly string $sourceReason,
        public readonly array  $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'module_slug'   => $this->moduleSlug,
            'content_type'  => $this->contentType,
            'priority'      => $this->priority,
            'site_id'       => $this->siteId,
            'source_reason' => $this->sourceReason,
            'metadata'      => $this->metadata,
        ];
    }
}
