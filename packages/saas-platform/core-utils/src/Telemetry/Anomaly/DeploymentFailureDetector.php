<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Anomaly;

use Illuminate\Support\Facades\DB;

class DeploymentFailureDetector
{
    private const RECENT_WINDOW_HOURS    = 24;
    private const FAILURE_THRESHOLD      = 2;
    private const STALE_LOCK_MINUTES     = 120;

    public function detect(): array
    {
        $anomalies = [];
        $anomalies = array_merge($anomalies, $this->checkRecentFailures());
        $anomalies = array_merge($anomalies, $this->checkStaleLock());
        return $anomalies;
    }

    private function checkRecentFailures(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return [];
            }

            $failures = DB::table('deployment_logs')
                ->where('status', 'failed')
                ->where('logged_at', '>=', now()->subHours(self::RECENT_WINDOW_HOURS))
                ->count();

            if ($failures >= self::FAILURE_THRESHOLD) {
                return [RuntimeAnomalyEngine::buildAnomaly(
                    'DEPLOYMENT_FAILURES_RECENT',
                    'critical',
                    "Deployment failure count ({$failures}) in last " . self::RECENT_WINDOW_HOURS . "h exceeds threshold",
                    ['failure_count' => $failures, 'window_hours' => self::RECENT_WINDOW_HOURS]
                )];
            }
        } catch (\Throwable) {
            // Non-critical
        }

        return [];
    }

    private function checkStaleLock(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('release_locks')) {
                return [];
            }

            $staleLock = DB::table('release_locks')
                ->where('status', 'active')
                ->where('acquired_at', '<', now()->subMinutes(self::STALE_LOCK_MINUTES))
                ->first();

            if ($staleLock) {
                return [RuntimeAnomalyEngine::buildAnomaly(
                    'DEPLOYMENT_STALE_LOCK',
                    'critical',
                    'Active deployment lock is stale (held > ' . self::STALE_LOCK_MINUTES . ' min)',
                    [
                        'deployment_uuid' => $staleLock->deployment_uuid,
                        'acquired_at'     => $staleLock->acquired_at,
                        'stale_threshold' => self::STALE_LOCK_MINUTES,
                    ]
                )];
            }
        } catch (\Throwable) {
            // Non-critical
        }

        return [];
    }
}
