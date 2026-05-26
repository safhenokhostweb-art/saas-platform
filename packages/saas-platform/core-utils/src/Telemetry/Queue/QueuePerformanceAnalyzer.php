<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Queue;

use Illuminate\Support\Facades\DB;

class QueuePerformanceAnalyzer
{
    public function analyze(): array
    {
        return [
            'pending_backlog'  => $this->pendingBacklog(),
            'failed_rate'      => $this->failedRate(),
            'queue_depths'     => $this->queueDepths(),
            'health'           => $this->computeHealth(),
        ];
    }

    private function pendingBacklog(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                return ['count' => 0, 'available' => false];
            }

            $count   = DB::table('jobs')->count();
            $oldest  = DB::table('jobs')->min('created_at');

            return [
                'count'      => $count,
                'oldest_job' => $oldest,
                'available'  => true,
            ];
        } catch (\Throwable) {
            return ['count' => 0, 'available' => false];
        }
    }

    private function failedRate(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return ['count' => 0, 'available' => false];
            }

            $last24h = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay()->toDateTimeString())
                ->count();

            return ['last_24h' => $last24h, 'available' => true];
        } catch (\Throwable) {
            return ['count' => 0, 'available' => false];
        }
    }

    private function queueDepths(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                return [];
            }

            return DB::table('jobs')
                ->selectRaw('queue, count(*) as depth')
                ->groupBy('queue')
                ->pluck('depth', 'queue')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function computeHealth(): string
    {
        try {
            $failedCount = DB::getSchemaBuilder()->hasTable('failed_jobs')
                ? DB::table('failed_jobs')->count()
                : 0;

            $pendingCount = DB::getSchemaBuilder()->hasTable('jobs')
                ? DB::table('jobs')->count()
                : 0;

            if ($failedCount >= 50 || $pendingCount >= 1000) {
                return 'critical';
            }

            if ($failedCount >= 10 || $pendingCount >= 500) {
                return 'degraded';
            }

            return 'healthy';
        } catch (\Throwable) {
            return 'unknown';
        }
    }
}
