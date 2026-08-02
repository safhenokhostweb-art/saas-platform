<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services\AiSeo;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\AiSeoProviderInterface;

/**
 * Phase 18-E — Part 4: "Analyze Page" — the single entry point the admin
 * UI calls. Delegates every suggestion to whichever AiSeoProviderInterface
 * is bound (NullAiSeoProvider by default) and always returns a complete,
 * predictable shape so the view never has to special-case "AI not
 * configured" beyond checking $result['available'].
 */
class AiSeoAssistantService
{
    public function __construct(
        private readonly AiSeoProviderInterface $provider,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     available: bool,
     *     provider: string,
     *     title_suggestion: ?string,
     *     description_suggestion: ?string,
     *     heading_suggestions: array,
     *     content_improvements: string[],
     *     keyword_suggestions: string[],
     * }
     */
    public function analyzePage(string $pageContent, ?string $currentTitle = null, ?string $currentDescription = null, array $context = []): array
    {
        if (!$this->provider->isAvailable()) {
            return [
                'available' => false,
                'provider' => $this->provider->name(),
                'title_suggestion' => null,
                'description_suggestion' => null,
                'heading_suggestions' => [],
                'content_improvements' => [],
                'keyword_suggestions' => [],
            ];
        }

        return [
            'available' => true,
            'provider' => $this->provider->name(),
            'title_suggestion' => $this->provider->suggestTitle($pageContent, $currentTitle, $context),
            'description_suggestion' => $this->provider->suggestDescription($pageContent, $currentDescription, $context),
            'heading_suggestions' => $this->provider->suggestHeadingStructure($pageContent, $context),
            'content_improvements' => $this->provider->suggestContentImprovements($pageContent, $context),
            'keyword_suggestions' => $this->provider->suggestKeywords($pageContent, $context),
        ];
    }
}
