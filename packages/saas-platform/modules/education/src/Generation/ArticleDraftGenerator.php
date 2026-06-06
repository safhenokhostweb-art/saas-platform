<?php

namespace SaaSPlatform\Modules\Education\Generation;

use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;

/**
 * Generates article draft DTOs via the configured AI provider.
 *
 * Forces contentType='articles' so the provider knows to produce
 * a structured article (title, body, summary, tags).
 *
 * This class MUST NOT write to the database.
 *
 * @throws AiProviderException            when the provider fails
 * @throws InvalidGeneratedContentException when validation fails
 */
class ArticleDraftGenerator
{
    public function __construct(
        private readonly AiContentProviderInterface $provider,
        private readonly GeneratedContentValidator  $validator,
    ) {}

    public function generate(ContentGenerationJobDto $job): GeneratedContentDto
    {
        $articleJob = new ContentGenerationJobDto(
            moduleSlug:   $job->moduleSlug,
            contentType:  ContentDeficitQueueServiceInterface::TYPE_ARTICLES,
            priority:     $job->priority,
            siteId:       $job->siteId,
            sourceReason: $job->sourceReason,
            metadata:     $job->metadata,
        );

        $result = $this->provider->generate($articleJob);
        $this->validator->validate($result);

        return $result;
    }

    public function isEnabled(): bool
    {
        return $this->provider->isEnabled();
    }

    public function providerName(): string
    {
        return $this->provider->providerName();
    }
}
