<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Lifecycle;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginHealthMonitor
{
    private const HEALTH_PREFIX    = 'plugin.health';
    private const FAILURE_PREFIX   = 'plugin.health.failures';
    private const MAX_FAILURES     = 5;
    private const HEALTH_TTL       = 300;

    public function __construct(
        private TelemetryManager $telemetry
    ) {}

    public function recordSuccess(string $moduleId): void
    {
        Cache::put(self::HEALTH_PREFIX . '.' . $moduleId, [
            'status'       => 'healthy',
            'last_success' => now()->toIso8601String(),
            'failures'     => 0,
        ], self::HEALTH_TTL);
    }

    public function recordFailure(string $moduleId, string $error): void
    {
        $failures = $this->failureCount($moduleId) + 1;

        Cache::put(self::FAILURE_PREFIX . '.' . $moduleId, $failures, 3600);

        Cache::put(self::HEALTH_PREFIX . '.' . $moduleId, [
            'status'       => $failures >= self::MAX_FAILURES ? 'critical' : 'degraded',
            'last_failure' => now()->toIso8601String(),
            'failures'     => $failures,
            'last_error'   => $error,
        ], self::HEALTH_TTL);

        $this->telemetry->warning('plugin_governance', 'plugin.health.failure_recorded', [
            'module_id' => $moduleId,
            'failures'  => $failures,
            'error'     => $error,
        ]);

        if ($failures >= self::MAX_FAILURES) {
            $this->telemetry->critical('plugin_governance', 'plugin.health.critical_threshold', [
                'module_id' => $moduleId,
                'failures'  => $failures,
            ]);
        }
    }

    public function isHealthy(string $moduleId): bool
    {
        $health = $this->getHealth($moduleId);
        return ($health['status'] ?? 'unknown') === 'healthy';
    }

    public function isCritical(string $moduleId): bool
    {
        return $this->failureCount($moduleId) >= self::MAX_FAILURES;
    }

    public function getHealth(string $moduleId): array
    {
        return Cache::get(self::HEALTH_PREFIX . '.' . $moduleId, [
            'status'   => 'unknown',
            'failures' => 0,
        ]);
    }

    public function resetFailures(string $moduleId): void
    {
        Cache::forget(self::FAILURE_PREFIX . '.' . $moduleId);
        $this->recordSuccess($moduleId);
    }

    private function failureCount(string $moduleId): int
    {
        return (int) Cache::get(self::FAILURE_PREFIX . '.' . $moduleId, 0);
    }
}
