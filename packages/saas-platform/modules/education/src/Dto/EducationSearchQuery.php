<?php

namespace SaaSPlatform\Modules\Education\Dto;

class EducationSearchQuery
{
    public function __construct(
        public readonly string  $term,
        public readonly ?int    $siteId       = null,
        public readonly ?string $role         = null,
        public readonly ?string $moduleSlug   = null,
        public readonly ?string $contentType  = null,
        public readonly ?string $routeName    = null,
        public readonly int     $limit        = 20,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            term:        $data['term'] ?? '',
            siteId:      isset($data['site_id']) ? (int) $data['site_id'] : null,
            role:        $data['role'] ?? null,
            moduleSlug:  $data['module_slug'] ?? null,
            contentType: $data['content_type'] ?? null,
            routeName:   $data['route_name'] ?? null,
            limit:       (int) ($data['limit'] ?? 20),
        );
    }
}
