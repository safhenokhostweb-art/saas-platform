<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class TenantLoadGovernor
{
    private const THROTTLE_KEY_PREFIX = 'governance.throttle.tenant.';
    private const DEFAULT_TTL         = 600;

    public function __construct(private TelemetryManager $telemetry) {}

    public function throttle(int $tenantId, float $factor, string $reason = 'load_governance'): void
    {
        Cache::put(self::THROTTLE_KEY_PREFIX . $tenantId, [
            'active'        => true,
            'factor'        => max(0.1, min(1.0, $factor)),
            'reason'        => $reason,
            'activated_at'  => now()->toIso8601String(),
        ], self::DEFAULT_TTL);

        $this->telemetry->warning('governance', 'governance.tenant.throttled', [
            'tenant_id' => $tenantId,
            'factor'    => $factor,
            'reason'    => $reason,
        ]);
    }

    public function release(int $tenantId): void
    {
        Cache::forget(self::THROTTLE_KEY_PREFIX . $tenantId);

        $this->telemetry->info('governance', 'governance.tenant.throttle_released', [
            'tenant_id' => $tenantId,
        ]);
    }

    public function isThrottled(int $tenantId): bool
    {
        return (bool) Cache::get(self::THROTTLE_KEY_PREFIX . $tenantId . '.active') ||
               ($this->getThrottleConfig($tenantId)['active'] ?? false);
    }

    public function throttleFactor(int $tenantId): float
    {
        return (float) ($this->getThrottleConfig($tenantId)['factor'] ?? 1.0);
    }

    public function getThrottleConfig(int $tenantId): array
    {
        return Cache::get(self::THROTTLE_KEY_PREFIX . $tenantId, []);
    }

    public function throttledTenants(): array
    {
        // In production, this would use a Redis SCAN or an index key
        return [];
    }
}
