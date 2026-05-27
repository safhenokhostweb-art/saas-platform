<?php

namespace SaaSPlatform\CoreUtils\AI;

use SaaSPlatform\CoreUtils\AI\Contracts\AiProviderInterface;
use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;
use Illuminate\Support\Facades\Cache;

class AiProviderManager
{
    /** @var AiProviderInterface[] */
    private array $providers = [];
    private ?string $activeProviderName = null;

    public function register(AiProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function setActive(string $name): void
    {
        if (! isset($this->providers[$name])) {
            throw new \InvalidArgumentException("AI provider not registered: {$name}");
        }
        $this->activeProviderName = $name;
    }

    public function getActive(): ?AiProviderInterface
    {
        if ($this->activeProviderName && isset($this->providers[$this->activeProviderName])) {
            $p = $this->providers[$this->activeProviderName];
            if ($p->isAvailable() && ! $this->isCircuitOpen($p->getName())) {
                return $p;
            }
        }

        // Auto-select: primary from config, then fallback chain
        $candidates = array_merge(
            [config('ai.provider', 'none')],
            config('ai.fallback_chain', [])
        );

        foreach ($candidates as $name) {
            if (isset($this->providers[$name])
                && $this->providers[$name]->isAvailable()
                && ! $this->isCircuitOpen($name)) {
                return $this->providers[$name];
            }
        }

        return null;
    }

    public function getProvider(string $name): ?AiProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    public function all(): array
    {
        return $this->providers;
    }

    public function isAnyAvailable(): bool
    {
        return $this->getActive() !== null;
    }

    public function recordFailure(string $providerName): void
    {
        $key = "ai:circuit:{$providerName}:failures";
        $failures = (int) Cache::get($key, 0) + 1;
        $threshold = config('ai.health.failure_threshold', 3);
        Cache::put($key, $failures, config('ai.health.recovery_window', 120));

        if ($failures >= $threshold) {
            $ttl = config('ai.health.circuit_open_ttl', 60);
            Cache::put("ai:circuit:{$providerName}:open", true, $ttl);
        }
    }

    public function recordSuccess(string $providerName): void
    {
        Cache::forget("ai:circuit:{$providerName}:failures");
        Cache::forget("ai:circuit:{$providerName}:open");
    }

    public function isCircuitOpen(string $providerName): bool
    {
        return (bool) Cache::get("ai:circuit:{$providerName}:open", false);
    }

    public function getStatus(): array
    {
        return array_map(function (AiProviderInterface $p) {
            return [
                'name'          => $p->getName(),
                'available'     => $p->isAvailable(),
                'circuit_open'  => $this->isCircuitOpen($p->getName()),
                'capabilities'  => $p->getCapabilities(),
            ];
        }, $this->providers);
    }

    public function completeWithFallback(array $messages, array $options = []): AiCompletionResult
    {
        $candidates = $this->buildCandidateList();

        foreach ($candidates as $name) {
            if (! isset($this->providers[$name])) continue;
            $provider = $this->providers[$name];
            if (! $provider->isAvailable() || $this->isCircuitOpen($name)) continue;

            $result = $provider->completeChat($messages, $options);

            if ($result->success) {
                $this->recordSuccess($name);
                return $result;
            }
            $this->recordFailure($name);
        }

        return AiCompletionResult::failure('All AI providers failed or unavailable');
    }

    private function buildCandidateList(): array
    {
        return array_unique(array_filter(array_merge(
            [$this->activeProviderName, config('ai.provider', 'none')],
            config('ai.fallback_chain', [])
        )));
    }
}
