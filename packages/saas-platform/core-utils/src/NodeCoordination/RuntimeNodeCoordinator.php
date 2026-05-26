<?php

namespace SaaSPlatform\CoreUtils\NodeCoordination;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeNodeCoordinator
{
    public function __construct(
        private NodeRegistry        $registry,
        private NodeCapacityTracker $capacityTracker,
        private DistributedRuntimeMap $runtimeMap,
        private TelemetryManager    $telemetry
    ) {}

    public function join(array $metadata = []): string
    {
        $nodeId = NodeRegistry::currentNodeId();
        $this->registry->register($nodeId, $metadata);
        $this->capacityTracker->reportCurrent($nodeId);

        $this->telemetry->info('node', 'node.coordinator.joined', [
            'node_id'  => $nodeId,
            'metadata' => $metadata,
        ]);

        return $nodeId;
    }

    public function leave(): void
    {
        $nodeId = NodeRegistry::currentNodeId();
        $this->registry->deregister($nodeId);

        $this->telemetry->info('node', 'node.coordinator.left', ['node_id' => $nodeId]);
    }

    public function heartbeat(int $activeJobs = 0, int $maxJobs = 10): void
    {
        $nodeId = NodeRegistry::currentNodeId();
        $this->registry->heartbeat($nodeId);
        $this->capacityTracker->reportCurrent($nodeId, $activeJobs, $maxJobs);
    }

    public function routeWorkload(string $workloadType = 'job'): string
    {
        $active = array_keys($this->registry->activeNodes());

        if (empty($active)) {
            return NodeRegistry::currentNodeId();
        }

        $target = $this->capacityTracker->leastLoadedNode($active);
        return $target ?? NodeRegistry::currentNodeId();
    }

    public function clusterStatus(): array
    {
        $map = $this->runtimeMap->build();

        $stale = $this->registry->staleNodes();
        if (!empty($stale)) {
            $this->telemetry->warning('node', 'node.coordinator.stale_nodes', [
                'stale_count' => count($stale),
                'node_ids'    => array_keys($stale),
            ]);
        }

        return [
            'distributed'     => $this->runtimeMap->isDistributed(),
            'node_count'      => $map['node_count'],
            'active_count'    => $map['active_count'],
            'stale_count'     => $map['stale_count'],
            'cluster_utilization' => $map['cluster_utilization'],
            'workload_map'    => $this->runtimeMap->workloadDistribution(),
            'status_at'       => now()->toIso8601String(),
        ];
    }
}
