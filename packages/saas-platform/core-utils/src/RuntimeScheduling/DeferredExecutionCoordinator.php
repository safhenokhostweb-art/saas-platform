<?php

namespace SaaSPlatform\CoreUtils\RuntimeScheduling;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class DeferredExecutionCoordinator
{
    private const DEFERRED_QUEUE_KEY = 'scheduling.deferred.queue';
    private const MAX_DEFERRED       = 500;
    private const DEFERRED_TTL       = 3600;

    public function __construct(private TelemetryManager $telemetry) {}

    public function defer(ScheduledWork $work): void
    {
        $queue = Cache::get(self::DEFERRED_QUEUE_KEY, []);

        if (count($queue) >= self::MAX_DEFERRED) {
            $this->telemetry->warning('scheduling', 'scheduling.deferred.queue_full', [
                'tenant_id'  => $work->tenantId,
                'queue_size' => count($queue),
            ]);
            return;
        }

        $queue[] = $work->toArray() + ['deferred_at' => now()->toIso8601String()];

        // Sort by priority descending so highest priority deferred runs first
        usort($queue, fn($a, $b) => $b['priority'] <=> $a['priority']);

        Cache::put(self::DEFERRED_QUEUE_KEY, $queue, self::DEFERRED_TTL);

        $this->telemetry->info('scheduling', 'scheduling.deferred.enqueued', [
            'tenant_id'  => $work->tenantId,
            'job_class'  => $work->jobClass,
            'priority'   => $work->priority,
        ]);
    }

    public function pending(): array
    {
        return Cache::get(self::DEFERRED_QUEUE_KEY, []);
    }

    public function pendingCount(): int
    {
        return count($this->pending());
    }

    public function drain(int $limit = 10): array
    {
        $queue   = Cache::get(self::DEFERRED_QUEUE_KEY, []);
        $drained = array_splice($queue, 0, $limit);

        Cache::put(self::DEFERRED_QUEUE_KEY, $queue, self::DEFERRED_TTL);

        return $drained;
    }

    public function clearForTenant(int $tenantId): int
    {
        $queue   = Cache::get(self::DEFERRED_QUEUE_KEY, []);
        $before  = count($queue);
        $queue   = array_values(array_filter($queue, fn($w) => $w['tenant_id'] !== $tenantId));
        Cache::put(self::DEFERRED_QUEUE_KEY, $queue, self::DEFERRED_TTL);
        return $before - count($queue);
    }
}
