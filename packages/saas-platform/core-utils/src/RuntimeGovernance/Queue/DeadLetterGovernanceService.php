<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Queue;

use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class DeadLetterGovernanceService
{
    private const POISON_THRESHOLD = 3;

    public function __construct(private TelemetryManager $telemetry) {}

    public function analyze(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return ['available' => false];
            }

            $total         = DB::table('failed_jobs')->count();
            $poisonJobs    = $this->detectPoisonJobs();
            $oldestFailure = DB::table('failed_jobs')->min('failed_at');

            return [
                'available'    => true,
                'total'        => $total,
                'poison_jobs'  => $poisonJobs,
                'oldest'       => $oldestFailure,
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }

    public function detectPoisonJobs(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return [];
            }

            // Group by exception class (first line) — poison jobs have same exception repeatedly
            return DB::table('failed_jobs')
                ->selectRaw('SUBSTRING_INDEX(exception, "\n", 1) as exception_sig, count(*) as count')
                ->groupBy('exception_sig')
                ->having('count', '>=', self::POISON_THRESHOLD)
                ->orderByDesc('count')
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function quarantineQueue(string $queue, string $reason): void
    {
        \Illuminate\Support\Facades\Cache::put(
            'governance.queue.quarantined.' . $queue,
            ['quarantined_at' => now()->toIso8601String(), 'reason' => $reason],
            3600
        );

        $this->telemetry->warning('governance', 'governance.queue.quarantined', [
            'queue'  => $queue,
            'reason' => $reason,
        ]);
    }

    public function isQuarantined(string $queue): bool
    {
        return (bool) \Illuminate\Support\Facades\Cache::get('governance.queue.quarantined.' . $queue);
    }

    public function releaseQuarantine(string $queue): void
    {
        \Illuminate\Support\Facades\Cache::forget('governance.queue.quarantined.' . $queue);
        $this->telemetry->info('governance', 'governance.queue.quarantine_released', ['queue' => $queue]);
    }
}
