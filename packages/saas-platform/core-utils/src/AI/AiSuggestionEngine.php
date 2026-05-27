<?php

namespace SaaSPlatform\CoreUtils\AI;

use SaaSPlatform\CoreUtils\AI\Contracts\AiProviderInterface;
use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;
use Illuminate\Support\Facades\Cache;

class AiSuggestionEngine
{
    private ?AiProviderInterface $provider = null;
    private array $providers = [];

    public function __construct(
        private readonly AiPromptRegistry $promptRegistry
    ) {}

    public function registerProvider(AiProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function setActiveProvider(string $name): void
    {
        if (! isset($this->providers[$name])) {
            throw new \InvalidArgumentException("AI provider not registered: {$name}");
        }
        $this->provider = $this->providers[$name];
    }

    public function getActiveProvider(): ?AiProviderInterface
    {
        if ($this->provider) {
            return $this->provider;
        }
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }
        return null;
    }

    public function suggest(string $promptKey, array $variables = [], bool $cached = true): AiCompletionResult
    {
        $cacheKey = 'ai:suggestion:' . md5($promptKey . serialize($variables));

        if ($cached) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return AiCompletionResult::success($cached, ['source' => 'cache']);
            }
        }

        $provider = $this->getActiveProvider();
        if (! $provider) {
            return $this->getFallbackResult($promptKey, $variables);
        }

        try {
            $prompt = $this->promptRegistry->resolve($promptKey, $variables);
            $result = $provider->complete($prompt);

            if ($result->success) {
                Cache::put($cacheKey, $result->text, 3600);
            }

            return $result;
        } catch (\Throwable $e) {
            return $this->getFallbackResult($promptKey, $variables);
        }
    }

    public function suggestJson(string $promptKey, array $variables = []): array
    {
        $result = $this->suggest($promptKey, $variables);
        if (! $result->success) {
            return [];
        }

        // Strip markdown code fences if present
        $text = preg_replace('/^```(?:json)?\s*/m', '', $result->text);
        $text = preg_replace('/```\s*$/m', '', $text);

        $decoded = json_decode(trim($text), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isAvailable(): bool
    {
        return $this->getActiveProvider() !== null;
    }

    public function getStatus(): array
    {
        return [
            'available'  => $this->isAvailable(),
            'provider'   => $this->provider?->getName() ?? 'none',
            'providers'  => array_keys($this->providers),
        ];
    }

    private function getFallbackResult(string $promptKey, array $variables): AiCompletionResult
    {
        // Rule-based fallbacks when no AI provider is available
        $meta = $this->promptRegistry->getMeta($promptKey);
        if (($meta['output'] ?? 'text') === 'json') {
            return AiCompletionResult::success('[]', ['source' => 'fallback'], 0.0);
        }
        return AiCompletionResult::success('', ['source' => 'fallback'], 0.0);
    }
}
