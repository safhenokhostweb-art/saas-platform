<?php

namespace App\Services\DevOps;

use App\Services\Infrastructure\Monitoring\HealthCheckService;
use Illuminate\Support\Facades\Cache;

/**
 * Panel-host CPU / memory / disk readings for the DevOps dashboard.
 *
 * Reads through HealthCheckService (one implementation of the OS probes) and
 * caches the result briefly, so polling dashboards never fan out into
 * repeated /proc and nproc reads. Never throws: an unreadable probe comes
 * back as null fields with status "unknown".
 */
class ServerMetricsCollector
{
    public const CACHE_KEY = 'devops:server-metrics';

    public function __construct(private readonly HealthCheckService $health) {}

    public function collect(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $ttl = max(1, (int) config('resource_governance.disk.metrics_cache_seconds', 30));

        return Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->read());
    }

    private function read(): array
    {
        $disk   = $this->safe(fn () => $this->health->checkDisk());
        $memory = $this->safe(fn () => $this->health->checkMemory());
        $cpu    = $this->safe(fn () => $this->health->checkCpu());

        $diskTotal = $disk['total_bytes'] ?? null;
        $diskFree  = $disk['free_bytes'] ?? null;
        $memTotal  = $memory['total_bytes'] ?? null;
        $memAvail  = $memory['available_bytes'] ?? null;

        return [
            'disk' => [
                'status'       => $disk['status'] ?? 'unknown',
                'total_bytes'  => $diskTotal,
                'free_bytes'   => $diskFree,
                'used_bytes'   => $diskTotal !== null && $diskFree !== null ? max(0, $diskTotal - $diskFree) : null,
                'used_percent' => $disk['used_percent'] ?? null,
            ],
            'memory' => [
                'status'          => $memory['status'] ?? 'unknown',
                'total_bytes'     => $memTotal,
                'available_bytes' => $memAvail,
                'used_bytes'      => $memTotal !== null && $memAvail !== null ? max(0, $memTotal - $memAvail) : null,
                'used_percent'    => $memory['used_percent'] ?? null,
            ],
            'cpu' => [
                'status'   => $cpu['status'] ?? 'unknown',
                'cores'    => $cpu['cores'] ?? null,
                'load_1m'  => $cpu['load_1m'] ?? null,
                'load_5m'  => $cpu['load_5m'] ?? null,
                'load_15m' => $cpu['load_15m'] ?? null,
                'percent'  => $cpu['percent'] ?? null,
            ],
            'collected_at' => now()->toIso8601String(),
        ];
    }

    private function safe(callable $probe): array
    {
        try {
            $result = $probe();

            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
