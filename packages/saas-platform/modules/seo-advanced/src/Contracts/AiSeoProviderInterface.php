<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

/**
 * Phase 18-E — Part 4: AI SEO Assistant — provider-agnostic by design
 * (never named after a specific vendor). Bound to NullAiSeoProvider by
 * default (isAvailable() === false, every suggest*() returns null/[]) so
 * this module has zero dependency on any AI vendor out of the box. A
 * real implementation (OpenAI/Claude/Gemini/a local model — whatever the
 * host or a future module configures) rebinds this same interface; the
 * calling code (AiSeoAssistantService) never changes.
 */
interface AiSeoProviderInterface
{
    public function name(): string;

    public function isAvailable(): bool;

    /** @param  array<string, mixed>  $context  optional hints: site name, industry, target keyword, language, etc. */
    public function suggestTitle(string $pageContent, ?string $currentTitle, array $context = []): ?string;

    /** @param  array<string, mixed>  $context */
    public function suggestDescription(string $pageContent, ?string $currentDescription, array $context = []): ?string;

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array{level: int, text: string}>
     */
    public function suggestHeadingStructure(string $pageContent, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     * @return string[]  short, actionable improvement suggestions
     */
    public function suggestContentImprovements(string $pageContent, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     * @return string[]
     */
    public function suggestKeywords(string $pageContent, array $context = []): array;
}
