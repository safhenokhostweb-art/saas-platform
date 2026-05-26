<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Sandbox;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class SandboxRuntimeManager
{
    private const STATUS_PREFIX = 'plugin.sandbox.status';
    private const STATUS_TTL    = 3600;

    public function __construct(
        private IsolatedExecutionCoordinator $coordinator,
        private ModuleResourceLimiter        $limiter,
        private TelemetryManager             $telemetry
    ) {}

    public function register(string $moduleId, array $metadata = []): void
    {
        Cache::put(self::STATUS_PREFIX . '.' . $moduleId, [
            'module_id'     => $moduleId,
            'status'        => 'registered',
            'registered_at' => now()->toIso8601String(),
            'metadata'      => $metadata,
        ], self::STATUS_TTL);

        $this->telemetry->info('plugin_governance', 'plugin.sandbox.registered', [
            'module_id' => $moduleId,
        ]);
    }

    public function deregister(string $moduleId): void
    {
        Cache::forget(self::STATUS_PREFIX . '.' . $moduleId);
        $this->telemetry->info('plugin_governance', 'plugin.sandbox.deregistered', ['module_id' => $moduleId]);
    }

    public function status(string $moduleId): array
    {
        return Cache::get(self::STATUS_PREFIX . '.' . $moduleId, [
            'module_id' => $moduleId,
            'status'    => 'unregistered',
        ]);
    }

    public function resourceSnapshot(string $moduleId): array
    {
        return $this->limiter->snapshot($moduleId);
    }

    public function coordinator(): IsolatedExecutionCoordinator
    {
        return $this->coordinator;
    }

    public function summary(): array
    {
        return [
            'sandbox_runtime' => 'active',
            'checked_at'      => now()->toIso8601String(),
        ];
    }
}
