<?php

namespace SaaSPlatform\CoreUtils\AI\Observability;

use SaaSPlatform\CoreUtils\AI\AiProviderManager;
use SaaSPlatform\CoreUtils\AI\AiProviderHealthMonitor;

class AiProviderDiagnostics
{
    public function __construct(
        private readonly AiProviderManager      $manager,
        private readonly AiProviderHealthMonitor $healthMonitor,
    ) {}

    public function getDiagnosticsReport(): array
    {
        $report = [
            'generated_at'   => now()->toISOString(),
            'providers'      => [],
            'system_health'  => 'unknown',
            'recommendations'=> [],
        ];

        $healthyCount = 0;
        $health       = $this->healthMonitor->getHealthSnapshot();

        foreach ($this->manager->all() as $name => $provider) {
            $providerHealth = $health[$name] ?? [];
            $isHealthy      = $this->healthMonitor->isHealthy($name);

            if ($isHealthy) $healthyCount++;

            $report['providers'][$name] = [
                'name'         => $name,
                'enabled'      => $provider->isAvailable(),
                'circuit_open' => $this->manager->isCircuitOpen($name),
                'healthy'      => $isHealthy,
                'avg_latency'  => $providerHealth['avg_latency'] ?? 0,
                'failure_rate' => $providerHealth['failure_rate'] ?? 0,
                'total_req'    => $providerHealth['total_req'] ?? 0,
                'health_score' => $providerHealth['health'] ?? 'unknown',
                'capabilities' => $provider->getCapabilities(),
            ];

            if (! $isHealthy && $provider->isAvailable()) {
                $report['recommendations'][] = "Provider '{$name}' shows degraded health — consider checking API key or network connectivity.";
            }
        }

        if ($healthyCount === 0) {
            $report['system_health'] = 'no_providers';
            $report['recommendations'][] = 'No AI providers are available. Set AI_PROVIDER and enable a provider in config/ai.php.';
        } elseif ($healthyCount < count($this->manager->all())) {
            $report['system_health'] = 'partial';
        } else {
            $report['system_health'] = 'healthy';
        }

        return $report;
    }

    public function testProvider(string $providerName): array
    {
        $provider = $this->manager->getProvider($providerName);
        if (! $provider) {
            return ['success' => false, 'error' => 'Provider not registered'];
        }

        if (! $provider->isAvailable()) {
            return ['success' => false, 'error' => 'Provider not configured (check API key and enabled flag)'];
        }

        $start  = microtime(true);
        $result = $provider->complete('Reply with just the word: OK', ['max_tokens' => 5]);
        $ms     = round((microtime(true) - $start) * 1000, 1);

        return [
            'provider'  => $providerName,
            'success'   => $result->success,
            'latency'   => $ms,
            'response'  => $result->success ? trim($result->text) : null,
            'error'     => $result->error,
            'meta'      => $result->meta,
        ];
    }
}
