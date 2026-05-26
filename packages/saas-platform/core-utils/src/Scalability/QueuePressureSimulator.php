<?php

namespace SaaSPlatform\CoreUtils\Scalability;

use SaaSPlatform\CoreUtils\DistributedQueue\QueueShardManager;
use SaaSPlatform\CoreUtils\DistributedQueue\RuntimeQueueBalancer;
use SaaSPlatform\CoreUtils\CapacityGovernance\CapacityForecastEngine;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\QueuePressureController;

class QueuePressureSimulator
{
    public function __construct(
        private QueueShardManager       $shardManager,
        private RuntimeQueueBalancer    $balancer,
        private CapacityForecastEngine  $forecast,
        private QueuePressureController $pressureController
    ) {}

    public function simulate(array $options = []): array
    {
        $pendingJobs = $options['pending_jobs'] ?? 0;
        $failedJobs  = $options['failed_jobs'] ?? 0;

        $forecast    = $this->forecast->forecastQueueSaturation(4);
        $balance     = $this->balancer->analyze();
        $pressure    = $this->pressureController->assess($pendingJobs ?: ($forecast['current_depth'] ?? 0), $failedJobs);
        $hotspots    = [];

        foreach (array_keys($balance['partitions'] ?? []) as $partition) {
            $partitionHotspots = $this->shardManager->hotspots($partition);
            if (!empty($partitionHotspots)) {
                $hotspots[$partition] = $partitionHotspots;
            }
        }

        return [
            'current_depth'    => $forecast['current_depth'] ?? 0,
            'forecast_4h'      => $forecast,
            'pressure_level'   => $pressure,
            'balance'          => $balance,
            'hotspots'         => $hotspots,
            'recommendations'  => $this->balancer->recommendations($balance),
            'simulated_at'     => now()->toIso8601String(),
        ];
    }

    public function scalabilityScore(array $simulationResult): int
    {
        $score  = 100;
        $depth  = $simulationResult['current_depth'] ?? 0;
        $pressure = $simulationResult['pressure_level'] ?? 'normal';

        if ($depth > 1000) {
            $score -= 40;
        } elseif ($depth > 500) {
            $score -= 20;
        }

        if ($pressure === 'critical') {
            $score -= 30;
        } elseif ($pressure === 'high') {
            $score -= 15;
        }

        if (!empty($simulationResult['hotspots'])) {
            $score -= 10 * count($simulationResult['hotspots']);
        }

        return max(0, $score);
    }
}
