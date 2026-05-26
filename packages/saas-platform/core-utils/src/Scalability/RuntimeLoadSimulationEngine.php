<?php

namespace SaaSPlatform\CoreUtils\Scalability;

use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimeCapacityPlanner;
use SaaSPlatform\CoreUtils\NodeCoordination\RuntimeNodeCoordinator;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\RuntimeFairnessCoordinator;

class RuntimeLoadSimulationEngine
{
    public function __construct(
        private TenantStressSimulator       $tenantSimulator,
        private QueuePressureSimulator      $queueSimulator,
        private RuntimeCapacityPlanner      $capacityPlanner,
        private RuntimeNodeCoordinator      $nodeCoordinator,
        private RuntimeFairnessCoordinator  $fairnessCoordinator
    ) {}

    public function runFullSimulation(array $tenantIds, array $options = []): array
    {
        $queueSim   = $this->queueSimulator->simulate($options['queue'] ?? []);
        $capacity   = $this->capacityPlanner->capacityReport();
        $cluster    = $this->nodeCoordinator->clusterStatus();
        $fairness   = $this->fairnessCoordinator->fairnessScore($tenantIds);

        $queueScore   = $this->queueSimulator->scalabilityScore($queueSim);
        $capacityScore = $this->capacityScoreFromReport($capacity);
        $clusterScore  = $this->clusterScoreFromStatus($cluster);
        $overallScore  = (int) round(($queueScore + $capacityScore + $clusterScore + $fairness) / 4);

        return [
            'overall_score'      => $overallScore,
            'grade'              => $this->grade($overallScore),
            'queue_simulation'   => $queueSim,
            'queue_score'        => $queueScore,
            'capacity_report'    => $capacity,
            'capacity_score'     => $capacityScore,
            'cluster_status'     => $cluster,
            'cluster_score'      => $clusterScore,
            'fairness_score'     => $fairness,
            'tenant_count'       => count($tenantIds),
            'simulated_at'       => now()->toIso8601String(),
        ];
    }

    public function runTenantSimulation(int $tenantId, array $victimIds = []): array
    {
        $stress = $this->tenantSimulator->simulateLoad($tenantId, ['dry_run' => true]);
        $noisy  = empty($victimIds) ? null : $this->tenantSimulator->simulateNoisyNeighbor($tenantId, $victimIds);

        return [
            'tenant_stress'  => $stress,
            'noisy_neighbor' => $noisy,
        ];
    }

    private function capacityScoreFromReport(array $report): int
    {
        $readiness = $report['capacity_plan']['readiness'] ?? [];
        return $readiness['readiness_score'] ?? 70;
    }

    private function clusterScoreFromStatus(array $cluster): int
    {
        $utilization = $cluster['cluster_utilization'] ?? 0;
        return max(0, 100 - (int) $utilization);
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default      => 'F',
        };
    }
}
