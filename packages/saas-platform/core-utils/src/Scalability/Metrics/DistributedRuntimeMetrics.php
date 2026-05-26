<?php

namespace SaaSPlatform\CoreUtils\Scalability\Metrics;

use SaaSPlatform\CoreUtils\NodeCoordination\RuntimeNodeCoordinator;
use SaaSPlatform\CoreUtils\NodeCoordination\DistributedRuntimeMap;
use SaaSPlatform\CoreUtils\DistributedQueue\DistributedQueueCoordinator;

class DistributedRuntimeMetrics
{
    public function __construct(
        private RuntimeNodeCoordinator    $nodeCoordinator,
        private DistributedRuntimeMap     $runtimeMap,
        private DistributedQueueCoordinator $queueCoordinator
    ) {}

    public function collect(): array
    {
        $cluster      = $this->nodeCoordinator->clusterStatus();
        $distribution = $this->runtimeMap->workloadDistribution();
        $queueStatus  = $this->queueCoordinator->coordinationStatus();

        return [
            'node_count'           => $cluster['active_count'],
            'stale_nodes'          => $cluster['stale_count'],
            'cluster_utilization'  => $cluster['cluster_utilization'],
            'is_distributed'       => $cluster['distributed'],
            'workload_distribution' => $distribution,
            'queue_hotspots'       => $queueStatus['hotspots'] ?? [],
            'queue_rebalancing_needed' => !empty($queueStatus['recommendations']),
        ];
    }

    public function nodeUtilizationScore(): int
    {
        $cluster = $this->nodeCoordinator->clusterStatus();
        return max(0, 100 - (int) ($cluster['cluster_utilization'] ?? 0));
    }
}
