<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Queue;

use Illuminate\Support\Facades\DB;

class FailedJobDiagnosticsService
{
    public function summary(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return ['available' => false];
            }

            $total = DB::table('failed_jobs')->count();

            $byQueue = DB::table('failed_jobs')
                ->selectRaw('queue, count(*) as count')
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->toArray();

            $byException = DB::table('failed_jobs')
                ->selectRaw('SUBSTRING_INDEX(exception, "\n", 1) as exception_class, count(*) as count')
                ->groupBy('exception_class')
                ->orderByDesc('count')
                ->limit(5)
                ->pluck('count', 'exception_class')
                ->toArray();

            $recent = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(5)
                ->get()
                ->map(fn($r) => [
                    'id'         => $r->id,
                    'queue'      => $r->queue,
                    'failed_at'  => $r->failed_at,
                    // payload and exception intentionally excluded — may contain sensitive data
                ])
                ->toArray();

            return [
                'available'    => true,
                'total'        => $total,
                'by_queue'     => $byQueue,
                'by_exception' => $byException,
                'recent'       => $recent,
            ];
        } catch (\Throwable) {
            return ['available' => false, 'error' => 'diagnostics_unavailable'];
        }
    }

    public function diagnoseById(int $id): array
    {
        try {
            $job = DB::table('failed_jobs')->find($id);
            if (!$job) {
                return ['found' => false];
            }

            // Extract exception class from stack trace (first line only)
            $exceptionLines = explode("\n", $job->exception ?? '');
            $exceptionClass = $exceptionLines[0] ?? 'unknown';

            return [
                'found'           => true,
                'id'              => $job->id,
                'queue'           => $job->queue,
                'failed_at'       => $job->failed_at,
                'exception_class' => $exceptionClass,
                // full payload/exception not returned — may contain sensitive data
            ];
        } catch (\Throwable) {
            return ['found' => false];
        }
    }
}
