<?php

namespace App\Services\Infrastructure;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads real hardware figures from the TARGET server over its own remote
 * connection (2026-11-19). ServerCapacityService used to read free RAM from
 * the panel host's local metrics collector and fall back to invented
 * 16 GB / 250 GB / 8-core values, so the capacity guard checked the wrong
 * machine or no machine at all.
 *
 * Returns null when the server cannot be probed (no shell, connection
 * failure, unparsable output); callers must treat that as "unknown", never
 * as a default.
 */
class ServerResourceProbe
{
    public const SITES_PATH = '/var/www';

    public function __construct(private readonly RemoteConnectionService $connections)
    {
    }

    /**
     * @return array{ram_total_mb: int, ram_available_mb: int, cpu_cores: float, disk_total_gb: float, disk_free_gb: float}|null
     */
    public function probe(CustomerRemoteServer $server): ?array
    {
        try {
            $driver = $this->connections->buildDriver($server);
            $driver->connect();
            try {
                $result = $driver->execute(
                    'cat /proc/meminfo 2>/dev/null; echo __NPROC__; nproc 2>/dev/null; echo __DF__; '
                    . '(df -Pk ' . self::SITES_PATH . ' 2>/dev/null || df -Pk / 2>/dev/null) | tail -1'
                );
            } finally {
                $driver->disconnect();
            }
        } catch (Throwable $e) {
            Log::info("ServerResourceProbe: server #{$server->id} could not be probed: " . $e->getMessage());

            return null;
        }

        return self::parse((string) ($result->stdout ?? ''));
    }

    /**
     * @return array{ram_total_mb: int, ram_available_mb: int, cpu_cores: float, disk_total_gb: float, disk_free_gb: float}|null
     */
    public static function parse(string $output): ?array
    {
        if (! preg_match('/^MemTotal:\s+(\d+)\s*kB/mi', $output, $total)) {
            return null;
        }
        $available = preg_match('/^MemAvailable:\s+(\d+)\s*kB/mi', $output, $m) ? (int) $m[1] : null;

        [, $afterNproc] = array_pad(explode('__NPROC__', $output, 2), 2, '');
        [$nprocPart, $dfPart] = array_pad(explode('__DF__', $afterNproc, 2), 2, '');
        $cores = (int) trim($nprocPart);

        // POSIX df -Pk: Filesystem 1024-blocks Used Available Capacity Mounted
        $df = preg_split('/\s+/', trim($dfPart));
        $dfTotalKb = isset($df[1]) && ctype_digit($df[1]) ? (int) $df[1] : null;
        $dfFreeKb = isset($df[3]) && ctype_digit($df[3]) ? (int) $df[3] : null;

        if ($available === null || $cores < 1 || $dfTotalKb === null || $dfFreeKb === null) {
            return null;
        }

        return [
            'ram_total_mb'     => intdiv((int) $total[1], 1024),
            'ram_available_mb' => intdiv($available, 1024),
            'cpu_cores'        => (float) $cores,
            'disk_total_gb'    => round($dfTotalKb / (1024 ** 2), 2),
            'disk_free_gb'     => round($dfFreeKb / (1024 ** 2), 2),
        ];
    }
}
