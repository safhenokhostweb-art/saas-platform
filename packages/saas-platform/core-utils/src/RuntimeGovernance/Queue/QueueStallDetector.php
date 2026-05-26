<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Queue;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueStallDetector
{
    private const LAST_PROCESSED_KEY = 'governance.queue.last_processed_at';
    private const STALL_THRESHOLD    = 300; // seconds without processing = stall

    public function recordProcessed(string $queue = 'default'): void
    {
        Cache::put(self::LAST_PROCESSED_KEY . '.' . $queue, time(), self::STALL_THRESHOLD * 3);
    }

    public function isStalled(string $queue = 'default'): bool
    {
        $lastProcessed = Cache::get(self::LAST_PROCESSED_KEY . '.' . $queue);

        if (!$lastProcessed) {
            // Never processed — only stall if there are pending jobs
            return $this->hasPendingJobs($queue);
        }

        return (time() - $lastProcessed) > self::STALL_THRESHOLD && $this->hasPendingJobs($queue);
    }

    public function stalledQueues(): array
    {
        $stalled = [];

        try {
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                return [];
            }

            $queues = DB::table('jobs')->distinct('queue')->pluck('queue')->toArray();

            foreach ($queues as $queue) {
                if ($this->isStalled($queue)) {
                    $stalled[] = $queue;
                }
            }
        } catch (\Throwable) {}

        return $stalled;
    }

    public function lastProcessedAge(string $queue = 'default'): ?int
    {
        $last = Cache::get(self::LAST_PROCESSED_KEY . '.' . $queue);
        return $last ? (time() - $last) : null;
    }

    private function hasPendingJobs(string $queue): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('jobs') &&
                   DB::table('jobs')->where('queue', $queue)->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
