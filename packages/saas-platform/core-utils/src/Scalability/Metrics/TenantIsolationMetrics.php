<?php

namespace SaaSPlatform\CoreUtils\Scalability\Metrics;

use SaaSPlatform\CoreUtils\RuntimeIsolation\RuntimeIsolationCoordinator;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\RuntimeFairnessCoordinator;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\TenantPressureAnalyzer;

class TenantIsolationMetrics
{
    public function __construct(
        private RuntimeIsolationCoordinator $isolationCoordinator,
        private RuntimeFairnessCoordinator  $fairnessCoordinator,
        private TenantPressureAnalyzer      $pressureAnalyzer
    ) {}

    public function collect(array $tenantIds): array
    {
        if (empty($tenantIds)) {
            return ['available' => false, 'reason' => 'no_tenants'];
        }

        $health       = $this->isolationCoordinator->platformIsolationHealth($tenantIds);
        $fairness     = $this->fairnessCoordinator->fairnessScore($tenantIds);
        $analysis     = $this->pressureAnalyzer->analyzePlatformPressure($tenantIds);

        return [
            'available'          => true,
            'tenant_count'       => $health['tenant_count'],
            'throttled_count'    => $health['throttled_count'],
            'emergency_count'    => $health['emergency_count'],
            'fairness_score'     => $fairness,
            'noisy_tenant_count' => $analysis['noisy_count'],
            'high_pressure_count' => $analysis['high_count'],
            'platform_pressure'  => $analysis['platform_pressure'],
            'top_consumers'      => array_keys($analysis['top_consumers'] ?? []),
        ];
    }

    public function tenantPressureScore(int $tenantId): float
    {
        return $this->pressureAnalyzer->pressureScore($tenantId);
    }
}
