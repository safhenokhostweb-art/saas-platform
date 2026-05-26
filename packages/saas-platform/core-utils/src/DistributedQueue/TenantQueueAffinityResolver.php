<?php

namespace SaaSPlatform\CoreUtils\DistributedQueue;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantQuotaManager;

class TenantQueueAffinityResolver
{
    private const AFFINITY_PREFIX = 'distributed.queue.affinity.tenant.';
    private const AFFINITY_TTL    = 3600;

    public function __construct(
        private TenantQuotaManager      $quotaManager,
        private QueuePartitionRegistry  $partitionRegistry
    ) {}

    public function resolveQueue(int $tenantId, string $jobType = 'default'): string
    {
        $affinityKey = $this->affinityKey($tenantId, $jobType);
        $cached      = Cache::get($affinityKey);

        if ($cached) {
            return $cached;
        }

        $queue = $this->computeQueue($tenantId, $jobType);
        Cache::put($affinityKey, $queue, self::AFFINITY_TTL);
        return $queue;
    }

    public function resolveShardedQueue(int $tenantId, string $jobType = 'default'): string
    {
        $baseQueue  = $this->resolveQueue($tenantId, $jobType);
        $shardCount = $this->partitionRegistry->shardCount($baseQueue);

        if ($shardCount <= 1) {
            return $baseQueue;
        }

        // Deterministic shard assignment: tenant_id mod shard_count
        $shard = $tenantId % $shardCount;
        return $this->partitionRegistry->shardName($baseQueue, $shard);
    }

    public function setAffinity(int $tenantId, string $jobType, string $queue): void
    {
        Cache::put($this->affinityKey($tenantId, $jobType), $queue, self::AFFINITY_TTL);
    }

    public function clearAffinity(int $tenantId): void
    {
        foreach (['default', 'high', 'low', 'notifications', 'reports'] as $jobType) {
            Cache::forget($this->affinityKey($tenantId, $jobType));
        }
    }

    private function computeQueue(int $tenantId, string $jobType): string
    {
        $plan     = $this->quotaManager->getPlan($tenantId);
        $priority = $this->quotaManager->getPriority($tenantId);

        // High-priority plans get dedicated high queue
        if ($priority >= 90 && $jobType === 'default') {
            return 'high';
        }

        // Trial/low-priority tenants get low queue
        if ($priority <= 20) {
            return 'low';
        }

        return $jobType === 'default' ? 'default' : $jobType;
    }

    private function affinityKey(int $tenantId, string $jobType): string
    {
        return self::AFFINITY_PREFIX . $tenantId . '.' . $jobType;
    }
}
