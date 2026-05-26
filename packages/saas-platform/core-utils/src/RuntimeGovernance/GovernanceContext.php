<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance;

use Illuminate\Support\Facades\Cache;

class GovernanceContext
{
    public const STATE_HEALTHY   = 'healthy';
    public const STATE_DEGRADED  = 'degraded';
    public const STATE_CRITICAL  = 'critical';
    public const STATE_EMERGENCY = 'emergency';

    private const STATE_CACHE_KEY       = 'governance.runtime.state';
    private const DEGRADATION_KEY_PREFIX = 'governance.degradation.tenant.';
    private const CIRCUIT_KEY_PREFIX     = 'governance.circuit.';

    public function currentState(): string
    {
        return Cache::get(self::STATE_CACHE_KEY, self::STATE_HEALTHY);
    }

    public function setState(string $state): void
    {
        Cache::forever(self::STATE_CACHE_KEY, $state);
    }

    public function isHealthy(): bool
    {
        return $this->currentState() === self::STATE_HEALTHY;
    }

    public function isCritical(): bool
    {
        return in_array($this->currentState(), [self::STATE_CRITICAL, self::STATE_EMERGENCY]);
    }

    public function tenantDegradationLevel(int $tenantId): int
    {
        return (int) Cache::get(self::DEGRADATION_KEY_PREFIX . $tenantId, 0);
    }

    public function setTenantDegradationLevel(int $tenantId, int $level): void
    {
        Cache::forever(self::DEGRADATION_KEY_PREFIX . $tenantId, max(0, min(5, $level)));
    }

    public function circuitState(string $key): string
    {
        return Cache::get(self::CIRCUIT_KEY_PREFIX . $key . '.state', 'CLOSED');
    }

    public function setCircuitState(string $key, string $state): void
    {
        Cache::forever(self::CIRCUIT_KEY_PREFIX . $key . '.state', $state);
    }

    public function toSnapshot(): array
    {
        return [
            'state'       => $this->currentState(),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    public static function validStates(): array
    {
        return [self::STATE_HEALTHY, self::STATE_DEGRADED, self::STATE_CRITICAL, self::STATE_EMERGENCY];
    }
}
