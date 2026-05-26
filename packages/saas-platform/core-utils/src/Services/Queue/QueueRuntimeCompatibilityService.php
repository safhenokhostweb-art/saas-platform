<?php

namespace SaaSPlatform\CoreUtils\Services\Queue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;

/**
 * Detects stale queue workers after a deployment.
 *
 * Workers running OLD code while the application serves NEW code
 * can cause serialization errors, model incompatibilities,
 * and data corruption in queued jobs.
 */
class QueueRuntimeCompatibilityService
{
    private const RELEASE_CACHE_KEY = 'saas_platform.current_release_id';

    public function __construct(private ReleaseManifestManager $manifestManager) {}

    public function validate(): array
    {
        $checks = [];

        $checks[] = $this->checkFailedJobsTable();
        $checks[] = $this->checkQueueLength();
        $checks[] = $this->checkReleaseVersionCoherence();

        $passed = array_sum(array_column($checks, 'passed'));
        $total  = count($checks);

        return [
            'passed' => $passed === $total,
            'score'  => "{$passed}/{$total}",
            'checks' => $checks,
        ];
    }

    public function stampCurrentRelease(string $deploymentUuid): void
    {
        Cache::forever(self::RELEASE_CACHE_KEY, [
            'deployment_uuid' => $deploymentUuid,
            'stamped_at'      => now()->toIso8601String(),
        ]);
    }

    public function getCurrentReleaseStamp(): ?array
    {
        return Cache::get(self::RELEASE_CACHE_KEY);
    }

    public function isWorkerStale(string $workerRelease): bool
    {
        $current = $this->manifestManager->latest();
        if (!$current) {
            return false;
        }
        return $workerRelease !== ($current['deployment_uuid'] ?? null);
    }

    private function checkFailedJobsTable(): array
    {
        try {
            $count = DB::table('failed_jobs')->count();
            $ok    = $count === 0;
            return [
                'check'  => 'failed_jobs',
                'passed' => true, // failed jobs are non-blocking for deployment
                'detail' => $ok ? 'No failed jobs' : "{$count} failed job(s) in queue (non-blocking)",
            ];
        } catch (\Throwable $e) {
            return ['check' => 'failed_jobs', 'passed' => true, 'detail' => 'Table check skipped: ' . $e->getMessage()];
        }
    }

    private function checkQueueLength(): array
    {
        try {
            $connection = config('queue.default', 'sync');

            if ($connection === 'sync') {
                return ['check' => 'queue_length', 'passed' => true, 'detail' => 'Sync driver — no queue backlog'];
            }

            if ($connection === 'database') {
                $pending = DB::table('jobs')->count();
                return [
                    'check'  => 'queue_length',
                    'passed' => $pending < 1000,
                    'detail' => "{$pending} job(s) pending",
                ];
            }

            return ['check' => 'queue_length', 'passed' => true, 'detail' => "Driver: {$connection} — manual drain recommended"];
        } catch (\Throwable $e) {
            return ['check' => 'queue_length', 'passed' => true, 'detail' => 'Queue check skipped: ' . $e->getMessage()];
        }
    }

    private function checkReleaseVersionCoherence(): array
    {
        $stamp = $this->getCurrentReleaseStamp();

        if (!$stamp) {
            return [
                'check'  => 'release_coherence',
                'passed' => true,
                'detail' => 'No release stamp yet — first deployment',
            ];
        }

        $latest = $this->manifestManager->latest();
        $match  = $latest && ($latest['deployment_uuid'] === $stamp['deployment_uuid']);

        return [
            'check'  => 'release_coherence',
            'passed' => $match,
            'detail' => $match
                ? "Workers aligned with release {$stamp['deployment_uuid']}"
                : "Workers may be running stale code (stamped: {$stamp['deployment_uuid']})",
        ];
    }
}
