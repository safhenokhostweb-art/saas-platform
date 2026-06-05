<?php

namespace SaaSPlatform\Modules\Education\Ai\Providers;

use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;

/**
 * Stub — OpenAI provider.
 *
 * Wire the OpenAI SDK here when EDUCATION_AI_PROVIDER=openai.
 * This class intentionally contains no provider SDK imports so the
 * module can be installed without the openai-php/client package.
 */
class OpenAiContentProvider implements AiContentProviderInterface
{
    public function __construct(
        private readonly string $apiKey   = '',
        private readonly string $model    = 'gpt-4o',
        private readonly int    $maxTokens = 2048,
    ) {}

    public function generate(ContentGenerationJobDto $job): GeneratedContentDto
    {
        if (! $this->isEnabled()) {
            throw new AiProviderException('OpenAI provider is not enabled — EDUCATION_OPENAI_API_KEY is not set.');
        }

        // TODO: Inject OpenAI SDK client and call Chat Completions API.
        // Prompt template should request: title, summary, body (HTML), tags[], confidence_score (0-100).
        // Example:
        //   $client = \OpenAI::client($this->apiKey);
        //   $response = $client->chat()->create([...]);
        //   $parsed = json_decode($response->choices[0]->message->content, true);

        throw new AiProviderException('OpenAiContentProvider::generate() is not yet implemented.');
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '';
    }

    public function providerName(): string
    {
        return 'openai';
    }
}
