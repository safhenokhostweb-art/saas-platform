<?php

namespace SaaSPlatform\Modules\Education\Dto;

final class GeneratedContentDto
{
    public function __construct(
        public readonly string $title,
        public readonly string $summary,
        public readonly string $body,
        public readonly array  $tags,
        public readonly float  $confidenceScore,
        public readonly string $provider,
    ) {}

    public function toArray(): array
    {
        return [
            'title'            => $this->title,
            'summary'          => $this->summary,
            'body'             => $this->body,
            'tags'             => $this->tags,
            'confidence_score' => $this->confidenceScore,
            'provider'         => $this->provider,
        ];
    }
}
