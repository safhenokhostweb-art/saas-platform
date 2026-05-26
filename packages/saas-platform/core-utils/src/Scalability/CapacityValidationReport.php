<?php

namespace SaaSPlatform\CoreUtils\Scalability;

use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimeCapacityPlanner;
use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimeScalingAdvisor;
use SaaSPlatform\CoreUtils\NodeCoordination\RuntimeNodeCoordinator;
use SaaSPlatform\CoreUtils\StorageGovernance\StoragePressureAnalyzer;
use SaaSPlatform\CoreUtils\CacheGovernance\TenantCachePressureMonitor;

class CapacityValidationReport
{
    public function __construct(
        private RuntimeCapacityPlanner   $capacityPlanner,
        private RuntimeScalingAdvisor    $scalingAdvisor,
        private RuntimeNodeCoordinator   $nodeCoordinator,
        private StoragePressureAnalyzer  $storagePressure,
        private TenantCachePressureMonitor $cachePressure
    ) {}

    public function generate(array $tenantIds = []): array
    {
        $plan      = $this->capacityPlanner->plan(24);
        $readiness = $this->scalingAdvisor->scalabilityReadiness();
        $advisor   = $this->scalingAdvisor->recommend();
        $cluster   = $this->nodeCoordinator->clusterStatus();

        $storagePressure = $this->storagePressure->platformStoragePressure($tenantIds);
        $cachePressure   = $this->cachePressure->platformCachePressure($tenantIds);

        $dimensions = [
            'capacity'    => $this->dimensionScore($readiness['readiness_score']),
            'cluster'     => $this->dimensionScore(100 - ($cluster['cluster_utilization'] ?? 0)),
            'storage'     => $this->pressureLevelScore($storagePressure),
            'cache'       => $this->pressureLevelScore($cachePressure),
            'queue'       => $this->pressureLevelScore($plan['pressure']['pressure_level'] ?? 'normal'),
        ];

        $overallScore = (int) round(array_sum($dimensions) / count($dimensions));

        return [
            'report_at'     => now()->toIso8601String(),
            'overall_score' => $overallScore,
            'grade'         => $readiness['grade'],
            'dimensions'    => $dimensions,
            'plan'          => $plan,
            'readiness'     => $readiness,
            'advisor'       => $advisor,
            'cluster'       => $cluster,
            'blockers'      => $readiness['blockers'] ?? [],
        ];
    }

    private function dimensionScore(float|int $rawScore): int
    {
        return max(0, min(100, (int) $rawScore));
    }

    private function pressureLevelScore(string $level): int
    {
        return match ($level) {
            'normal'   => 100,
            'elevated' => 75,
            'warning'  => 60,
            'high'     => 40,
            'critical' => 10,
            default    => 70,
        };
    }
}
