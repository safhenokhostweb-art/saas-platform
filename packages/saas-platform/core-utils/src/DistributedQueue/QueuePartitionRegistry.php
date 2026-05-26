<?php

namespace SaaSPlatform\CoreUtils\DistributedQueue;

use Illuminate\Support\Facades\Cache;

class QueuePartitionRegistry
{
    private const PARTITIONS_KEY = 'distributed.queue.partitions';
    private const TTL            = 86400;

    private const DEFAULT_PARTITIONS = [
        'default'       => ['shard_count' => 1, 'priority' => 50, 'max_workers' => 4],
        'high'          => ['shard_count' => 2, 'priority' => 90, 'max_workers' => 8],
        'low'           => ['shard_count' => 1, 'priority' => 10, 'max_workers' => 2],
        'notifications' => ['shard_count' => 2, 'priority' => 70, 'max_workers' => 4],
        'reports'       => ['shard_count' => 1, 'priority' => 30, 'max_workers' => 2],
    ];

    public function register(string $partition, array $config): void
    {
        $partitions = $this->all();
        $partitions[$partition] = $config;
        Cache::put(self::PARTITIONS_KEY, $partitions, self::TTL);
    }

    public function get(string $partition): ?array
    {
        return $this->all()[$partition] ?? null;
    }

    public function all(): array
    {
        return Cache::get(self::PARTITIONS_KEY, self::DEFAULT_PARTITIONS);
    }

    public function shardCount(string $partition): int
    {
        return $this->get($partition)['shard_count'] ?? 1;
    }

    public function maxWorkers(string $partition): int
    {
        return $this->get($partition)['max_workers'] ?? 4;
    }

    public function priority(string $partition): int
    {
        return $this->get($partition)['priority'] ?? 50;
    }

    public function shardName(string $partition, int $shard): string
    {
        $count = $this->shardCount($partition);
        if ($count <= 1) {
            return $partition;
        }
        return $partition . '_shard_' . ($shard % $count);
    }

    public function allShards(string $partition): array
    {
        $count  = $this->shardCount($partition);
        $shards = [];
        for ($i = 0; $i < $count; $i++) {
            $shards[] = $this->shardName($partition, $i);
        }
        return $shards;
    }
}
