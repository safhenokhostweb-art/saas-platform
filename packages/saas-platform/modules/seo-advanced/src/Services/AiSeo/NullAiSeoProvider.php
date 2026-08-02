<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services\AiSeo;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\AiSeoProviderInterface;

/**
 * Phase 18-E — Part 4: the default, zero-dependency AiSeoProviderInterface
 * binding. Every method returns an empty/null result and isAvailable()
 * is false — AiSeoAssistantService checks this before calling anything,
 * so the AI Assistant UI shows "not configured" instead of crashing.
 */
class NullAiSeoProvider implements AiSeoProviderInterface
{
    public function name(): string
    {
        return 'none';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function suggestTitle(string $pageContent, ?string $currentTitle, array $context = []): ?string
    {
        return null;
    }

    public function suggestDescription(string $pageContent, ?string $currentDescription, array $context = []): ?string
    {
        return null;
    }

    public function suggestHeadingStructure(string $pageContent, array $context = []): array
    {
        return [];
    }

    public function suggestContentImprovements(string $pageContent, array $context = []): array
    {
        return [];
    }

    public function suggestKeywords(string $pageContent, array $context = []): array
    {
        return [];
    }
}
