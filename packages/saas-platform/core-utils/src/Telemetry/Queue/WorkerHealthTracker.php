<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Queue;

use Illuminate\Support\Facades\Cache;

class WorkerHealthTracker
{
    private const WORKER_HEARTBEAT_PREFIX = 'telemetry.worker.heartbeat.';
    private const HEARTBEAT_TTL_SECONDS   = 120;
    private const STALE_THRESHOLD_SECONDS = 180;

    public function recordHeartbeat(string $workerId): void
    {
        Cache::put(
            self::WORKER_HEARTBEAT_PREFIX . $workerId,
            ['worker_id' => $workerId, 'at' => now()->toIso8601String(), 'pid' => getmypid()],
            self::HEARTBEAT_TTL_SECONDS
        );
    }

    public function getWorkerStatus(string $workerId): array
    {
        $data = Cache::get(self::WORKER_HEARTBEAT_PREFIX . $workerId);

        if (!$data) {
            return ['worker_id' => $workerId, 'status' => 'unknown', 'last_heartbeat' => null];
        }

        $lastSeen = strtotime($data['at']);
        $stale    = (time() - $lastSeen) > self::STALE_THRESHOLD_SECONDS;

        return [
            'worker_id'      => $workerId,
            'status'         => $stale ? 'stale' : 'healthy',
            'last_heartbeat' => $data['at'],
            'pid'            => $data['pid'] ?? null,
        ];
    }

    public function currentReleaseStamp(): ?string
    {
        return Cache::get('saas_platform.current_release_id');
    }

    public function isWorkerOnCurrentRelease(string $workerId): bool
    {
        $current = $this->currentReleaseStamp();
        if (!$current) {
            return true;
        }

        $workerRelease = Cache::get(self::WORKER_HEARTBEAT_PREFIX . $workerId . '.release');
        return $workerRelease === $current;
    }
}
