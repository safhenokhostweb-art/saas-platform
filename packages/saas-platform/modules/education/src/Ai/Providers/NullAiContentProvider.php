<?php

namespace SaaSPlatform\Modules\Education\Ai\Providers;

use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;

/**
 * Default no-op provider used when AI generation is disabled.
 *
 * Returns a structurally valid but empty DTO so callers can detect the
 * disabled state via isEnabled() rather than checking for null returns.
 * The generation service will skip persistence when the provider is disabled.
 */
class NullAiContentProvider implements AiContentProviderInterface
{
    public function generate(ContentGenerationJobDto $job): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           '',
            summary:         '',
            body:            '',
            tags:            [],
            confidenceScore: 0.0,
            provider:        $this->providerName(),
        );
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function providerName(): string
    {
        return 'null';
    }
}
