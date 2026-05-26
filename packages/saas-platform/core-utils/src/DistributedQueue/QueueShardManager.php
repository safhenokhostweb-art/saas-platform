<?php

namespace SaaSPlatform\CoreUtils\DistributedQueue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class QueueShardManager
{
    private const HOTSPOT_PREFIX    = 'distributed.queue.hotspot.';
    private const HOTSPOT_THRESHOLD = 200;

    public function __construct(private QueuePartitionRegistry $registry) {}

    public function shardDepths(string $partition): array
    {
        $shards = $this->registry->allShards($partition);
        $depths = [];

        foreach ($shards as $shard) {
            $depths[$shard] = $this->queueDepth($shard);
        }

        return $depths;
    }

    public function hotspots(string $partition): array
    {
        $depths   = $this->shardDepths($partition);
        $hotspots = [];

        foreach ($depths as $shard => $depth) {
            if ($depth >= self::HOTSPOT_THRESHOLD) {
                $hotspots[$shard] = $depth;
                Cache::put(self::HOTSPOT_PREFIX . $shard, $depth, 300);
            }
        }

        return $hotspots;
    }

    public function isHotspot(string $shard): bool
    {
        return (bool) Cache::get(self::HOTSPOT_PREFIX . $shard);
    }

    public function leastLoadedShard(string $partition): string
    {
        $depths = $this->shardDepths($partition);
        if (empty($depths)) {
            return $partition;
        }

        asort($depths);
        return array_key_first($depths);
    }

    public function rebalancingNeeded(string $partition): bool
    {
        $depths = $this->shardDepths($partition);
        if (count($depths) <= 1) {
            return false;
        }

        $max = max($depths);
        $min = min($depths);
        $avg = array_sum($depths) / count($depths);

        return $avg > 0 && ($max / $avg) >= 3.0;
    }

    public function shardStats(string $partition): array
    {
        $depths = $this->shardDepths($partition);

        if (empty($depths)) {
            return ['available' => false];
        }

        return [
            'available'          => true,
            'partition'          => $partition,
            'shard_count'        => count($depths),
            'depths'             => $depths,
            'total'              => array_sum($depths),
            'average'            => round(array_sum($depths) / count($depths), 1),
            'rebalancing_needed' => $this->rebalancingNeeded($partition),
        ];
    }

    private function queueDepth(string $queue): int
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                return 0;
            }
            return DB::table('jobs')->where('queue', $queue)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
