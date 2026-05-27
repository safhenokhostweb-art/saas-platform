<?php

namespace SaaSPlatform\CoreUtils\AI;

use SaaSPlatform\CoreUtils\AI\Contracts\AiCompletionResult;
use Illuminate\Support\Facades\Cache;

class AiProviderHealthMonitor
{
    private const HEALTH_KEY_PREFIX = 'ai:health:';
    private const LATENCY_KEY_PREFIX = 'ai:latency:';
    private const METRICS_TTL = 3600;

    public function __construct(
        private readonly AiProviderManager $manager
    ) {}

    public function recordLatency(string $provider, float $latencyMs): void
    {
        $key     = self::LATENCY_KEY_PREFIX . $provider;
        $history = Cache::get($key, []);
        $history[] = $latencyMs;
        // Keep last 100 samples
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        Cache::put($key, $history, self::METRICS_TTL);
    }

    public function recordRequest(string $provider, bool $success): void
    {
        $statsKey = self::HEALTH_KEY_PREFIX . $provider . ':stats';
        $stats    = Cache::get($statsKey, ['total' => 0, 'success' => 0, 'failures' => 0]);
        $stats['total']++;
        $success ? $stats['success']++ : $stats['failures']++;
        Cache::put($statsKey, $stats, self::METRICS_TTL);

        if ($success) {
            $this->manager->recordSuccess($provider);
        } else {
            $this->manager->recordFailure($provider);
        }
    }

    public function getHealthSnapshot(): array
    {
        $snapshot = [];
        foreach ($this->manager->all() as $name => $provider) {
            $statsKey = self::HEALTH_KEY_PREFIX . $name . ':stats';
            $stats    = Cache::get($statsKey, ['total' => 0, 'success' => 0, 'failures' => 0]);
            $latency  = $this->getAverageLatency($name);
            $failRate = $stats['total'] > 0 ? round(($stats['failures'] / $stats['total']) * 100, 1) : 0;

            $snapshot[$name] = [
                'available'    => $provider->isAvailable(),
                'circuit_open' => $this->manager->isCircuitOpen($name),
                'total_req'    => $stats['total'],
                'success'      => $stats['success'],
                'failures'     => $stats['failures'],
                'failure_rate' => $failRate,
                'avg_latency'  => $latency,
                'health'       => $this->computeHealth($failRate, $latency, $provider->isAvailable()),
            ];
        }
        return $snapshot;
    }

    public function getAverageLatency(string $provider): float
    {
        $history = Cache::get(self::LATENCY_KEY_PREFIX . $provider, []);
        return count($history) > 0 ? round(array_sum($history) / count($history), 1) : 0.0;
    }

    public function isHealthy(string $provider): bool
    {
        if (! isset($this->manager->all()[$provider])) return false;
        if (! $this->manager->all()[$provider]->isAvailable()) return false;
        if ($this->manager->isCircuitOpen($provider)) return false;

        $statsKey = self::HEALTH_KEY_PREFIX . $provider . ':stats';
        $stats    = Cache::get($statsKey, ['total' => 0, 'failures' => 0]);
        $failRate = $stats['total'] > 10
            ? ($stats['failures'] / $stats['total']) * 100
            : 0;

        return $failRate < 50;
    }

    public function runProbeAll(): array
    {
        $results = [];
        foreach ($this->manager->all() as $name => $provider) {
            if (! $provider->isAvailable()) {
                $results[$name] = ['probed' => false, 'reason' => 'not_configured'];
                continue;
            }
            $start  = microtime(true);
            $result = $provider->complete('Say OK in one word.', ['max_tokens' => 5]);
            $ms     = round((microtime(true) - $start) * 1000, 1);

            $this->recordRequest($name, $result->success);
            $this->recordLatency($name, $ms);

            $results[$name] = [
                'probed'  => true,
                'success' => $result->success,
                'latency' => $ms,
                'error'   => $result->error,
            ];
        }
        return $results;
    }

    private function computeHealth(float $failRate, float $latency, bool $available): string
    {
        if (! $available) return 'disabled';
        if ($failRate >= 50 || $latency > 10000) return 'critical';
        if ($failRate >= 20 || $latency > 5000) return 'degraded';
        return 'healthy';
    }
}
