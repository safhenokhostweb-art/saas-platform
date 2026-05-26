<?php

namespace SaaSPlatform\CoreUtils\NodeCoordination;

use Illuminate\Support\Facades\Cache;

class DistributedRuntimeMap
{
    private const MAP_KEY    = 'node.distributed.runtime_map';
    private const MAP_TTL    = 300;

    public function __construct(
        private NodeRegistry      $registry,
        private NodeCapacityTracker $capacityTracker
    ) {}

    public function build(): array
    {
        $activeNodes = $this->registry->activeNodes();
        $nodeMap     = [];

        foreach ($activeNodes as $nodeId => $meta) {
            $capacity = $this->capacityTracker->get($nodeId);
            $snapshot = NodeHealthSnapshot::capture($nodeId, $meta);

            $nodeMap[$nodeId] = [
                'metadata' => $meta,
                'capacity' => $capacity,
                'health'   => $snapshot->toArray(),
            ];
        }

        $map = [
            'built_at'     => now()->toIso8601String(),
            'node_count'   => count($nodeMap),
            'active_count' => count($activeNodes),
            'stale_count'  => count($this->registry->staleNodes()),
            'nodes'        => $nodeMap,
            'cluster_utilization' => $this->capacityTracker->clusterUtilization(array_keys($activeNodes)),
        ];

        Cache::put(self::MAP_KEY, $map, self::MAP_TTL);
        return $map;
    }

    public function cached(): ?array
    {
        return Cache::get(self::MAP_KEY);
    }

    public function get(): array
    {
        return $this->cached() ?? $this->build();
    }

    public function isDistributed(): bool
    {
        return $this->registry->activeCount() > 1;
    }

    public function nodeCount(): int
    {
        return $this->registry->activeCount();
    }

    public function workloadDistribution(): array
    {
        $map = $this->get();
        $distribution = [];

        foreach ($map['nodes'] as $nodeId => $data) {
            $distribution[$nodeId] = [
                'utilization_pct' => $data['capacity']['utilization_pct'] ?? 0,
                'active_jobs'     => $data['capacity']['active_jobs'] ?? 0,
                'status'          => $data['health']['status'] ?? 'unknown',
            ];
        }

        return $distribution;
    }
}
