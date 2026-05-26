<?php

namespace SaaSPlatform\CoreUtils\DistributedQueue;

use Illuminate\Support\Facades\DB;

class RuntimeQueueBalancer
{
    private const IMBALANCE_RATIO = 3.0;

    public function __construct(
        private QueuePartitionRegistry $registry,
        private QueueShardManager      $shardManager
    ) {}

    public function analyze(): array
    {
        $partitions = $this->registry->all();
        $results    = [];

        foreach (array_keys($partitions) as $partition) {
            $results[$partition] = $this->shardManager->shardStats($partition);
        }

        $overallDepth = $this->totalQueueDepth();

        return [
            'available'      => true,
            'partitions'     => $results,
            'total_depth'    => $overallDepth,
            'rebalancing_needed' => $this->anyRebalancingNeeded($results),
        ];
    }

    public function recommendations(array $analysis): array
    {
        $recs = [];

        foreach ($analysis['partitions'] ?? [] as $partition => $stats) {
            if (!($stats['available'] ?? false)) {
                continue;
            }
            if ($stats['rebalancing_needed'] ?? false) {
                $recs[] = "Partition '{$partition}' is imbalanced (avg={$stats['average']} jobs) — consider adding shards or workers";
            }
        }

        if (($analysis['total_depth'] ?? 0) > 5000) {
            $recs[] = 'Total queue depth (' . $analysis['total_depth'] . ') exceeds recommended threshold — scale workers or increase concurrency';
        }

        return $recs;
    }

    public function routeToShard(int $tenantId, string $partition): string
    {
        // Deterministic: tenant gets consistent shard, least-loaded acts as fallback
        $shardCount = $this->registry->shardCount($partition);
        if ($shardCount <= 1) {
            return $partition;
        }

        $preferredShard = $this->registry->shardName($partition, $tenantId % $shardCount);

        if ($this->shardManager->isHotspot($preferredShard)) {
            return $this->shardManager->leastLoadedShard($partition);
        }

        return $preferredShard;
    }

    private function totalQueueDepth(): int
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                return 0;
            }
            return DB::table('jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function anyRebalancingNeeded(array $partitionStats): bool
    {
        foreach ($partitionStats as $stats) {
            if ($stats['rebalancing_needed'] ?? false) {
                return true;
            }
        }
        return false;
    }
}
