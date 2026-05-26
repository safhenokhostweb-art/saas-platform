<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor;

use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantQuotaManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeFairnessCoordinator
{
    public function __construct(
        private NoisyNeighborDetector   $detector,
        private TenantPressureAnalyzer  $analyzer,
        private AdaptiveTenantThrottler $throttler,
        private TenantPenaltyEngine     $penaltyEngine,
        private TenantQuotaManager      $quotaManager,
        private TelemetryManager        $telemetry
    ) {}

    public function enforeFairness(array $tenantIds): array
    {
        $noisyTenants = $this->detector->detect($tenantIds);
        $actions      = [];

        foreach ($noisyTenants as $tenantId => $info) {
            $strikes = $this->penaltyEngine->recordStrike($tenantId, 'noisy_neighbor_detected');
            $factor  = $this->throttler->adapt($tenantId);

            $actions[$tenantId] = [
                'action'         => 'throttled',
                'strikes'        => $strikes,
                'throttle_factor' => $factor,
                'pressure_score'  => $info['pressure_score'],
            ];
        }

        // Restore tenants that were previously throttled but are now normal
        $throttledTenants = $this->findPreviouslyThrottled($tenantIds);
        foreach ($throttledTenants as $tenantId) {
            if (!isset($noisyTenants[$tenantId]) && !$this->analyzer->isHighPressure($tenantId)) {
                $this->throttler->release($tenantId);
                $actions[$tenantId] = ['action' => 'released'];
            }
        }

        $platformAnalysis = $this->analyzer->analyzePlatformPressure($tenantIds);

        if ($platformAnalysis['platform_pressure'] === 'critical') {
            $this->telemetry->error('isolation', 'isolation.fairness.platform_critical_pressure', [
                'noisy_count' => $platformAnalysis['noisy_count'],
                'tenant_count' => count($tenantIds),
            ]);
        }

        return [
            'tenant_actions'     => $actions,
            'platform_analysis'  => $platformAnalysis,
            'enforced_at'        => now()->toIso8601String(),
        ];
    }

    public function fairnessScore(array $tenantIds): int
    {
        if (empty($tenantIds)) {
            return 100;
        }

        $analysis   = $this->analyzer->analyzePlatformPressure($tenantIds);
        $noisyPct   = $analysis['noisy_count'] / count($tenantIds) * 100;
        $highPct    = $analysis['high_count'] / count($tenantIds) * 100;

        return max(0, (int) round(100 - ($noisyPct * 2) - ($highPct * 1)));
    }

    private function findPreviouslyThrottled(array $tenantIds): array
    {
        return array_filter($tenantIds, fn($id) => $this->penaltyEngine->strikes($id) > 0);
    }
}
