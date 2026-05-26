<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeThrottleManager
{
    private const GLOBAL_KEY = 'governance.throttle.global';

    public function __construct(
        private TenantLoadGovernor    $tenantGovernor,
        private QueuePressureController $queueController,
        private TelemetryManager      $telemetry
    ) {}

    public function activateGlobal(float $factor, string $reason = 'runtime_pressure'): void
    {
        Cache::put(self::GLOBAL_KEY, [
            'active'       => true,
            'factor'       => max(0.1, min(1.0, $factor)),
            'reason'       => $reason,
            'activated_at' => now()->toIso8601String(),
        ], 600);

        $this->telemetry->warning('governance', 'governance.throttle.global_activated', [
            'factor' => $factor,
            'reason' => $reason,
        ]);
    }

    public function deactivateGlobal(): void
    {
        Cache::forget(self::GLOBAL_KEY);
        $this->telemetry->info('governance', 'governance.throttle.global_deactivated', []);
    }

    public function isGloballyThrottled(): bool
    {
        return (bool) (Cache::get(self::GLOBAL_KEY)['active'] ?? false);
    }

    public function globalFactor(): float
    {
        return (float) (Cache::get(self::GLOBAL_KEY)['factor'] ?? 1.0);
    }

    public function throttleTenant(int $tenantId, float $factor, string $reason = ''): void
    {
        $this->tenantGovernor->throttle($tenantId, $factor, $reason);
    }

    public function releaseTenant(int $tenantId): void
    {
        $this->tenantGovernor->release($tenantId);
    }

    public function isTenantThrottled(int $tenantId): bool
    {
        return $this->tenantGovernor->isThrottled($tenantId);
    }

    public function effectiveFactor(?int $tenantId = null): float
    {
        $global = $this->globalFactor();

        if ($tenantId !== null) {
            $tenant = $this->tenantGovernor->throttleFactor($tenantId);
            return min($global, $tenant);
        }

        return $global;
    }

    public function snapshot(): array
    {
        return [
            'global'         => Cache::get(self::GLOBAL_KEY, ['active' => false]),
            'queue_pressure' => $this->queueController->currentPressure(),
        ];
    }
}
