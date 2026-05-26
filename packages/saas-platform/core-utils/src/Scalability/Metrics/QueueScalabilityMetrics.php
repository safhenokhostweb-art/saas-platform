<?php

namespace SaaSPlatform\CoreUtils\Scalability\Metrics;

use SaaSPlatform\CoreUtils\DistributedQueue\DistributedQueueCoordinator;
use SaaSPlatform\CoreUtils\DistributedQueue\RuntimeQueueBalancer;
use SaaSPlatform\CoreUtils\CapacityGovernance\CapacityForecastEngine;

class QueueScalabilityMetrics
{
    public function __construct(
        private DistributedQueueCoordinator $coordinator,
        private RuntimeQueueBalancer        $balancer,
        private CapacityForecastEngine      $forecast
    ) {}

    public function collect(): array
    {
        $balance    = $this->balancer->analyze();
        $forecast   = $this->forecast->forecastQueueSaturation(4);
        $recs       = $this->balancer->recommendations($balance);

        return [
            'available'             => true,
            'current_depth'         => $forecast['current_depth'] ?? 0,
            'projected_depth_4h'    => $forecast['projected_depth'] ?? 0,
            'saturation_risk'       => $forecast['saturation_risk'] ?? 'normal',
            'growth_rate_per_hour'  => $forecast['growth_rate_per_hour'] ?? 0,
            'hours_to_critical'     => $forecast['hours_to_critical'],
            'total_partitions'      => count($balance['partitions'] ?? []),
            'rebalancing_needed'    => $balance['rebalancing_needed'] ?? false,
            'recommendation_count'  => count($recs),
        ];
    }

    public function saturationIndex(): int
    {
        $forecast = $this->forecast->forecastQueueSaturation(4);
        return match ($forecast['saturation_risk'] ?? 'normal') {
            'critical' => 90,
            'warning'  => 60,
            'elevated' => 40,
            default    => 10,
        };
    }
}
