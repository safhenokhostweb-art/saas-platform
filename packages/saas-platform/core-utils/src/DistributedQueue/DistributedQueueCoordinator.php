<?php

namespace SaaSPlatform\CoreUtils\DistributedQueue;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\Queue\QueueTelemetryService;

class DistributedQueueCoordinator
{
    public function __construct(
        private QueuePartitionRegistry    $partitionRegistry,
        private TenantQueueAffinityResolver $affinityResolver,
        private QueueShardManager         $shardManager,
        private RuntimeQueueBalancer      $balancer,
        private QueueTelemetryService     $queueTelemetry,
        private TelemetryManager          $telemetry
    ) {}

    public function resolveQueue(int $tenantId, string $jobType = 'default'): string
    {
        $baseQueue = $this->affinityResolver->resolveQueue($tenantId, $jobType);
        return $this->balancer->routeToShard($tenantId, $baseQueue);
    }

    public function coordinationStatus(): array
    {
        $balance = $this->balancer->analyze();
        $hotspots = $this->detectHotspots();

        if (!empty($hotspots)) {
            $this->telemetry->warning('distributed_queue', 'distributed.queue.hotspots_detected', [
                'hotspots' => $hotspots,
            ]);
        }

        return [
            'balance'      => $balance,
            'hotspots'     => $hotspots,
            'recommendations' => $this->balancer->recommendations($balance),
            'telemetry'    => $this->queueTelemetry->snapshot(),
        ];
    }

    public function registerPartition(string $name, int $shardCount, int $priority, int $maxWorkers): void
    {
        $this->partitionRegistry->register($name, [
            'shard_count' => $shardCount,
            'priority'    => $priority,
            'max_workers' => $maxWorkers,
        ]);

        $this->telemetry->info('distributed_queue', 'distributed.queue.partition_registered', [
            'partition'   => $name,
            'shard_count' => $shardCount,
        ]);
    }

    public function pinTenantToQueue(int $tenantId, string $queue, string $jobType = 'default'): void
    {
        $this->affinityResolver->setAffinity($tenantId, $jobType, $queue);
    }

    public function unpinTenant(int $tenantId): void
    {
        $this->affinityResolver->clearAffinity($tenantId);
    }

    private function detectHotspots(): array
    {
        $hotspots = [];
        foreach (array_keys($this->partitionRegistry->all()) as $partition) {
            $partitionHotspots = $this->shardManager->hotspots($partition);
            if (!empty($partitionHotspots)) {
                $hotspots[$partition] = $partitionHotspots;
            }
        }
        return $hotspots;
    }
}
