<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class TenantPenaltyEngine
{
    private const PENALTY_PREFIX = 'isolation.penalty.tenant.';
    private const MAX_STRIKES    = 3;

    private const PENALTY_FACTORS = [
        1 => 0.8,  // first offense: 20% slowdown
        2 => 0.5,  // second: 50% throttle
        3 => 0.2,  // third: severe throttle
    ];

    public function __construct(private TelemetryManager $telemetry) {}

    public function recordStrike(int $tenantId, string $reason): int
    {
        $key     = self::PENALTY_PREFIX . $tenantId . '.strikes';
        $strikes = (int) Cache::get($key, 0);
        $strikes = min($strikes + 1, self::MAX_STRIKES);
        Cache::put($key, $strikes, 3600);

        $this->telemetry->warning('isolation', 'isolation.penalty.strike_recorded', [
            'tenant_id' => $tenantId,
            'strikes'   => $strikes,
            'reason'    => $reason,
        ]);

        return $strikes;
    }

    public function strikes(int $tenantId): int
    {
        return (int) Cache::get(self::PENALTY_PREFIX . $tenantId . '.strikes', 0);
    }

    public function penaltyFactor(int $tenantId): float
    {
        $strikes = $this->strikes($tenantId);
        return self::PENALTY_FACTORS[$strikes] ?? 1.0;
    }

    public function clearStrikes(int $tenantId): void
    {
        Cache::forget(self::PENALTY_PREFIX . $tenantId . '.strikes');
    }

    public function isMaxPenalized(int $tenantId): bool
    {
        return $this->strikes($tenantId) >= self::MAX_STRIKES;
    }

    public function penaltyLevel(int $tenantId): string
    {
        return match ($this->strikes($tenantId)) {
            0       => 'none',
            1       => 'minor',
            2       => 'moderate',
            default => 'severe',
        };
    }
}
