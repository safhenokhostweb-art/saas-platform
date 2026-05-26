<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Metrics;

use Illuminate\Support\Facades\DB;

class QueueMetricsRepository
{
    public function summary(): array
    {
        $data = [
            'pending'       => 0,
            'failed'        => 0,
            'queue_depths'  => [],
            'failed_last24h' => 0,
            'available'     => true,
        ];

        try {
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                $data['pending'] = DB::table('jobs')->count();
                $data['queue_depths'] = DB::table('jobs')
                    ->selectRaw('queue, count(*) as depth')
                    ->groupBy('queue')
                    ->pluck('depth', 'queue')
                    ->toArray();
            }

            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                $data['failed'] = DB::table('failed_jobs')->count();
                $data['failed_last24h'] = DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subDay()->toDateTimeString())
                    ->count();
            }
        } catch (\Throwable) {
            $data['available'] = false;
        }

        return $data;
    }

    public function failedJobTrend(int $days = 7): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return [];
            }

            return DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDays($days)->toDateTimeString())
                ->selectRaw('DATE(failed_at) as date, count(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
