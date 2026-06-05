<?php

namespace SaaSPlatform\Modules\Education\Ai\Providers;

use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;

/**
 * Stub — Claude (Anthropic) provider.
 *
 * Wire the Anthropic SDK here when EDUCATION_AI_PROVIDER=claude.
 * This class intentionally contains no provider SDK imports so the
 * module can be installed without the anthropic/sdk package.
 */
class ClaudeAiContentProvider implements AiContentProviderInterface
{
    public function __construct(
        private readonly string $apiKey   = '',
        private readonly string $model    = 'claude-sonnet-4-6',
        private readonly int    $maxTokens = 2048,
    ) {}

    public function generate(ContentGenerationJobDto $job): GeneratedContentDto
    {
        if (! $this->isEnabled()) {
            throw new AiProviderException('Claude provider is not enabled — EDUCATION_CLAUDE_API_KEY is not set.');
        }

        // TODO: Inject Anthropic SDK client and call Messages API.
        // Prompt template should request: title, summary, body (HTML), tags[], confidence_score (0-100).
        // Example:
        //   $client = new \Anthropic\Client($this->apiKey);
        //   $response = $client->messages()->create([...]);
        //   $parsed = json_decode($response->content[0]->text, true);

        throw new AiProviderException('ClaudeAiContentProvider::generate() is not yet implemented.');
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '';
    }

    public function providerName(): string
    {
        return 'claude';
    }
}
