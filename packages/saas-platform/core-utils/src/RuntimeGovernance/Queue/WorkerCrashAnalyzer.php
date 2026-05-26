<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Queue;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WorkerCrashAnalyzer
{
    private const CRASH_KEY_PREFIX  = 'governance.worker.crash.';
    private const CRASH_THRESHOLD   = 3;
    private const CRASH_WINDOW      = 600; // 10 min

    public function recordCrash(string $workerId, string $reason = 'unknown'): void
    {
        $key   = self::CRASH_KEY_PREFIX . $workerId;
        $count = (int) Cache::increment($key);
        Cache::put($key . '.last_reason', $reason, self::CRASH_WINDOW);
        Cache::put($key . '.ttl', 1, self::CRASH_WINDOW);
    }

    public function crashCount(string $workerId): int
    {
        return (int) Cache::get(self::CRASH_KEY_PREFIX . $workerId, 0);
    }

    public function isCrashLooping(string $workerId): bool
    {
        return $this->crashCount($workerId) >= self::CRASH_THRESHOLD;
    }

    public function poisonJobSignature(array $failedJob): ?string
    {
        // Detect poison jobs by repeated failures of the same job class
        try {
            if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return null;
            }

            $payload = json_decode($failedJob['payload'] ?? '{}', true);
            $jobClass = $payload['displayName'] ?? null;

            if (!$jobClass) {
                return null;
            }

            $count = DB::table('failed_jobs')
                ->whereRaw("payload LIKE ?", ['%' . $jobClass . '%'])
                ->count();

            return $count >= 3 ? $jobClass : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function crashReport(): array
    {
        return [
            'threshold'       => self::CRASH_THRESHOLD,
            'window_seconds'  => self::CRASH_WINDOW,
        ];
    }
}
