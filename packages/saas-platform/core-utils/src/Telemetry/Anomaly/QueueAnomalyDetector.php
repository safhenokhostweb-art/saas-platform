<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Anomaly;

use Illuminate\Support\Facades\DB;

class QueueAnomalyDetector
{
    private const FAILED_JOBS_THRESHOLD = 10;
    private const PENDING_JOBS_THRESHOLD = 500;

    public function detect(): array
    {
        $anomalies = [];

        $anomalies = array_merge($anomalies, $this->checkFailedJobs());
        $anomalies = array_merge($anomalies, $this->checkPendingJobBacklog());

        return $anomalies;
    }

    private function checkFailedJobs(): array
    {
        try {
            $count = DB::table('failed_jobs')->count();

            if ($count >= self::FAILED_JOBS_THRESHOLD) {
                return [RuntimeAnomalyEngine::buildAnomaly(
                    'QUEUE_FAILED_JOBS_HIGH',
                    $count >= 50 ? 'critical' : 'warning',
                    "Failed job count ({$count}) exceeds threshold",
                    ['failed_job_count' => $count, 'threshold' => self::FAILED_JOBS_THRESHOLD]
                )];
            }
        } catch (\Throwable) {
            // Table may not exist yet
        }

        return [];
    }

    private function checkPendingJobBacklog(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                return [];
            }

            $count = DB::table('jobs')->count();

            if ($count >= self::PENDING_JOBS_THRESHOLD) {
                return [RuntimeAnomalyEngine::buildAnomaly(
                    'QUEUE_BACKLOG_HIGH',
                    'warning',
                    "Queue backlog ({$count} jobs) exceeds threshold",
                    ['pending_count' => $count, 'threshold' => self::PENDING_JOBS_THRESHOLD]
                )];
            }
        } catch (\Throwable) {
            // Non-critical
        }

        return [];
    }
}
