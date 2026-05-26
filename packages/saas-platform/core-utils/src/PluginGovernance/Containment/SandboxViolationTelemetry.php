<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Containment;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class SandboxViolationTelemetry
{
    private const VIOLATION_PREFIX = 'plugin.violation';
    private const VIOLATION_TTL   = 86400;

    public function __construct(
        private TelemetryManager $telemetry
    ) {}

    public function record(string $moduleId, string $violationType, array $context = []): void
    {
        $key      = self::VIOLATION_PREFIX . '.' . $moduleId;
        $existing = Cache::get($key, []);

        $existing[] = [
            'type'        => $violationType,
            'context'     => $context,
            'recorded_at' => now()->toIso8601String(),
        ];

        if (count($existing) > 50) {
            $existing = array_slice($existing, -50);
        }

        Cache::put($key, $existing, self::VIOLATION_TTL);

        $this->telemetry->critical('plugin_governance', 'plugin.sandbox.violation', array_merge([
            'module_id'      => $moduleId,
            'violation_type' => $violationType,
        ], $context));
    }

    public function getViolations(string $moduleId): array
    {
        return Cache::get(self::VIOLATION_PREFIX . '.' . $moduleId, []);
    }

    public function violationCount(string $moduleId): int
    {
        return count($this->getViolations($moduleId));
    }

    public function clearViolations(string $moduleId): void
    {
        Cache::forget(self::VIOLATION_PREFIX . '.' . $moduleId);
    }
}
