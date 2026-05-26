<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor;

use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RuntimeThrottleManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class AdaptiveTenantThrottler
{
    public function __construct(
        private TenantPressureAnalyzer $analyzer,
        private TenantPenaltyEngine    $penaltyEngine,
        private RuntimeThrottleManager $throttleManager,
        private TelemetryManager       $telemetry
    ) {}

    public function adapt(int $tenantId): float
    {
        $pressureScore = $this->analyzer->pressureScore($tenantId);
        $penalty       = $this->penaltyEngine->penaltyFactor($tenantId);
        $factor        = $this->computeFactor($pressureScore, $penalty);

        if ($factor < 1.0) {
            $this->throttleManager->activateTenant($tenantId, $factor, 'adaptive_pressure:' . round($pressureScore, 1));

            $this->telemetry->info('isolation', 'isolation.adaptive_throttle.applied', [
                'tenant_id'      => $tenantId,
                'pressure_score' => $pressureScore,
                'penalty_factor' => $penalty,
                'effective_factor' => $factor,
            ]);
        } else {
            $this->throttleManager->releaseTenant($tenantId);
        }

        return $factor;
    }

    public function adaptMultiple(array $tenantIds): array
    {
        $results = [];
        foreach ($tenantIds as $id) {
            $results[$id] = $this->adapt($id);
        }
        return $results;
    }

    public function release(int $tenantId): void
    {
        $this->throttleManager->releaseTenant($tenantId);
        $this->penaltyEngine->clearStrikes($tenantId);
    }

    private function computeFactor(float $pressureScore, float $penalty): float
    {
        if ($pressureScore >= 90.0) {
            $base = 0.2;
        } elseif ($pressureScore >= 70.0) {
            $base = 0.5;
        } elseif ($pressureScore >= 50.0) {
            $base = 0.75;
        } else {
            $base = 1.0;
        }

        return round(max(0.1, $base * $penalty), 2);
    }
}
