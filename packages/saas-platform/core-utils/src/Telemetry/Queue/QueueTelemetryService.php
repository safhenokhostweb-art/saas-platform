<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Queue;

use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class QueueTelemetryService
{
    public function __construct(private TelemetryManager $telemetry) {}

    public function recordJobDispatched(string $jobClass, array $traceContext = []): void
    {
        $this->telemetry->info('queue', 'job.dispatched', array_merge([
            'job_class' => $jobClass,
        ], $traceContext));
    }

    public function recordJobProcessed(string $jobClass, float $durationMs, array $traceContext = []): void
    {
        $this->telemetry->info('queue', 'job.processed', array_merge([
            'job_class'   => $jobClass,
            'duration_ms' => $durationMs,
        ], $traceContext));
    }

    public function recordJobFailed(string $jobClass, string $exception, array $traceContext = []): void
    {
        $this->telemetry->error('queue', 'job.failed', array_merge([
            'job_class' => $jobClass,
            'exception' => $exception,
        ], $traceContext));
    }

    public function snapshot(): array
    {
        $data = [
            'pending'     => 0,
            'failed'      => 0,
            'queues'      => [],
        ];

        try {
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                $data['pending'] = (int) DB::table('jobs')->count();
                $data['queues']  = DB::table('jobs')
                    ->selectRaw('queue, count(*) as count')
                    ->groupBy('queue')
                    ->pluck('count', 'queue')
                    ->toArray();
            }

            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                $data['failed'] = (int) DB::table('failed_jobs')->count();
            }
        } catch (\Throwable) {
            // Non-critical
        }

        return $data;
    }
}
