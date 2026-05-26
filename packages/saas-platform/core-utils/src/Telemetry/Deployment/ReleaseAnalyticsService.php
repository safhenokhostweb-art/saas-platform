<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Deployment;

use Illuminate\Support\Facades\DB;

class ReleaseAnalyticsService
{
    public function history(int $limit = 10): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return [];
            }

            $uuids = DB::table('deployment_logs')
                ->select('deployment_uuid')
                ->distinct()
                ->orderByDesc(DB::raw('MIN(logged_at)'))
                ->limit($limit)
                ->pluck('deployment_uuid')
                ->toArray();

            return array_map(fn($uuid) => $this->summarizeDeployment($uuid), $uuids);
        } catch (\Throwable) {
            return [];
        }
    }

    public function summarizeDeployment(string $deploymentUuid): array
    {
        try {
            $logs = DB::table('deployment_logs')
                ->where('deployment_uuid', $deploymentUuid)
                ->orderBy('logged_at')
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();

            if (empty($logs)) {
                return ['deployment_uuid' => $deploymentUuid, 'status' => 'no_logs'];
            }

            $start      = $logs[0]['logged_at'] ?? null;
            $end        = $logs[count($logs) - 1]['logged_at'] ?? null;
            $statuses   = array_column($logs, 'status');
            $finalStatus = in_array('failed', $statuses) ? 'failed' : 'completed';

            return [
                'deployment_uuid' => $deploymentUuid,
                'status'          => $finalStatus,
                'started_at'      => $start,
                'ended_at'        => $end,
                'phase_count'     => count($logs),
                'phases'          => array_map(fn($l) => [
                    'phase'  => $l['phase'],
                    'status' => $l['status'],
                ], $logs),
            ];
        } catch (\Throwable) {
            return ['deployment_uuid' => $deploymentUuid, 'status' => 'error'];
        }
    }

    public function successRate(int $days = 30): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return ['available' => false];
            }

            $since = now()->subDays($days)->toDateTimeString();

            $total   = DB::table('deployment_logs')
                ->where('logged_at', '>=', $since)
                ->distinct('deployment_uuid')
                ->count('deployment_uuid');

            $failed = DB::table('deployment_logs')
                ->where('logged_at', '>=', $since)
                ->where('status', 'failed')
                ->distinct('deployment_uuid')
                ->count('deployment_uuid');

            $successRate = $total > 0 ? round((($total - $failed) / $total) * 100, 1) : 100;

            return [
                'available'    => true,
                'days'         => $days,
                'total'        => $total,
                'failed'       => $failed,
                'success_rate' => $successRate,
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }
}
