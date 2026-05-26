<?php

namespace SaaSPlatform\CoreUtils\Scalability\Metrics;

use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimeCapacityPlanner;
use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimePressurePredictor;
use SaaSPlatform\CoreUtils\NodeCoordination\RuntimeNodeCoordinator;

class RuntimeCapacitySnapshot
{
    public function __construct(
        private RuntimeCapacityPlanner   $planner,
        private RuntimePressurePredictor $predictor,
        private RuntimeNodeCoordinator   $nodeCoordinator
    ) {}

    public function capture(): array
    {
        $prediction = $this->predictor->predict();
        $cluster    = $this->nodeCoordinator->clusterStatus();
        $plan       = $this->planner->plan(4);

        return [
            'captured_at'          => now()->toIso8601String(),
            'pressure_level'       => $prediction['pressure_level'],
            'pressure_score'       => $this->predictor->pressureScore(),
            'is_saturating'        => $this->predictor->isSaturating(),
            'node_count'           => $cluster['active_count'],
            'cluster_utilization'  => $cluster['cluster_utilization'],
            'queue_depth'          => $plan['queue']['current_depth'] ?? 0,
            'queue_forecast_4h'    => $plan['queue']['projected_depth'] ?? 0,
            'saturation_risk'      => $plan['queue']['saturation_risk'] ?? 'normal',
            'storage_forecast_30d_gb' => $plan['storage']['projected_growth_gb'] ?? 0,
        ];
    }
}
