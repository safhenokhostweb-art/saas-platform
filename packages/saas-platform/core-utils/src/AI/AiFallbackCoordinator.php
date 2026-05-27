<?php

namespace SaaSPlatform\CoreUtils\AI;

use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;

class AiFallbackCoordinator
{
    /** @var callable[] */
    private array $ruleBasedFallbacks = [];

    public function __construct(
        private readonly AiProviderManager $manager
    ) {}

    public function registerFallback(string $domain, callable $fallback): void
    {
        $this->ruleBasedFallbacks[$domain] = $fallback;
    }

    public function execute(
        string $domain,
        array  $messages,
        array  $options = [],
        array  $fallbackArgs = []
    ): AiCompletionResult {
        // Try live provider
        if ($this->manager->isAnyAvailable()) {
            $result = $this->manager->completeWithFallback($messages, $options);
            if ($result->success) {
                return $result;
            }
        }

        // Try domain-specific rule-based fallback
        if (isset($this->ruleBasedFallbacks[$domain])) {
            try {
                $text = ($this->ruleBasedFallbacks[$domain])(...$fallbackArgs);
                if (is_array($text)) {
                    $text = json_encode($text);
                }
                return AiCompletionResult::success((string) $text, ['source' => 'fallback', 'domain' => $domain], 0.0);
            } catch (\Throwable) {}
        }

        // Last resort: empty fallback
        $isJson = ($options['json_mode'] ?? false) || str_contains($options['output'] ?? '', 'json');
        return AiCompletionResult::success(
            $isJson ? '[]' : '',
            ['source' => 'empty_fallback', 'domain' => $domain],
            0.0
        );
    }

    public function executeWithPrompt(
        string $promptText,
        string $domain = 'general',
        array  $options = [],
        array  $fallbackArgs = []
    ): AiCompletionResult {
        return $this->execute(
            $domain,
            [['role' => 'user', 'content' => $promptText]],
            $options,
            $fallbackArgs
        );
    }

    public function getFallbackDomains(): array
    {
        return array_keys($this->ruleBasedFallbacks);
    }
}
