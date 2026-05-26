<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Metrics;

use Illuminate\Support\Facades\DB;

class DeploymentMetricsRepository
{
    public function summary(int $days = 30): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return ['available' => false];
            }

            $since = now()->subDays($days)->toDateTimeString();

            $total = DB::table('deployment_logs')
                ->where('logged_at', '>=', $since)
                ->distinct('deployment_uuid')
                ->count('deployment_uuid');

            $failed = DB::table('deployment_logs')
                ->where('logged_at', '>=', $since)
                ->where('status', 'failed')
                ->distinct('deployment_uuid')
                ->count('deployment_uuid');

            $avgDuration = DB::table('deployment_logs')
                ->where('logged_at', '>=', $since)
                ->whereNotNull('duration_ms')
                ->avg('duration_ms');

            return [
                'available'       => true,
                'days'            => $days,
                'total'           => $total,
                'failed'          => $failed,
                'success_rate'    => $total > 0 ? round((($total - $failed) / $total) * 100, 1) : 100,
                'avg_duration_ms' => $avgDuration ? round($avgDuration, 0) : null,
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }

    public function phaseFailureBreakdown(int $days = 30): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return [];
            }

            return DB::table('deployment_logs')
                ->where('status', 'failed')
                ->where('logged_at', '>=', now()->subDays($days)->toDateTimeString())
                ->selectRaw('phase, count(*) as failure_count')
                ->groupBy('phase')
                ->orderByDesc('failure_count')
                ->pluck('failure_count', 'phase')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function recentDeployments(int $limit = 10): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('release_locks')) {
                return [];
            }

            return DB::table('release_locks')
                ->orderByDesc('acquired_at')
                ->limit($limit)
                ->get()
                ->map(fn($r) => [
                    'deployment_uuid' => $r->deployment_uuid,
                    'environment'     => $r->environment,
                    'status'          => $r->status,
                    'phase'           => $r->phase,
                    'acquired_at'     => $r->acquired_at,
                    'released_at'     => $r->released_at,
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
