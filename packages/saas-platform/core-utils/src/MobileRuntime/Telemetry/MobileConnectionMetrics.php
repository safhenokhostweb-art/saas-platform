<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Telemetry;

use Illuminate\Support\Facades\Cache;

class MobileConnectionMetrics
{
    private const PREFIX = 'mobile:metrics:conn:';
    private const TTL    = 3600;

    public function recordConnect(string $tenantId, int|string $userId, string $deviceId, string $transport): void
    {
        Cache::increment(self::PREFIX . "active:{$tenantId}");
        Cache::increment(self::PREFIX . "total:{$tenantId}");
        $this->incrementTransport($tenantId, $transport);
        Cache::put(self::PREFIX . "connect:{$tenantId}:{$userId}:{$deviceId}", now()->toIso8601String(), self::TTL);
    }

    public function recordDisconnect(string $tenantId, int|string $userId, string $deviceId, float $duration): void
    {
        $active = (int) Cache::get(self::PREFIX . "active:{$tenantId}", 0);
        Cache::put(self::PREFIX . "active:{$tenantId}", max(0, $active - 1), self::TTL);
        Cache::forget(self::PREFIX . "connect:{$tenantId}:{$userId}:{$deviceId}");

        $durations   = Cache::get(self::PREFIX . "durations:{$tenantId}", []);
        $durations[]  = $duration;
        if (count($durations) > 200) $durations = array_slice($durations, -200);
        Cache::put(self::PREFIX . "durations:{$tenantId}", $durations, self::TTL);
    }

    public function recordReconnect(string $tenantId, int|string $userId, int $attemptNumber): void
    {
        Cache::increment(self::PREFIX . "reconnects:{$tenantId}");
    }

    public function recordLatency(string $tenantId, string $deviceId, float $latencyMs): void
    {
        $latencies   = Cache::get(self::PREFIX . "latencies:{$tenantId}", []);
        $latencies[] = $latencyMs;
        if (count($latencies) > 500) $latencies = array_slice($latencies, -500);
        Cache::put(self::PREFIX . "latencies:{$tenantId}", $latencies, self::TTL);
    }

    public function getAverageLatency(string $tenantId): float
    {
        $latencies = Cache::get(self::PREFIX . "latencies:{$tenantId}", []);
        return empty($latencies) ? 0.0 : round(array_sum($latencies) / count($latencies), 2);
    }

    public function getConnectionStats(string $tenantId): array
    {
        $durations = Cache::get(self::PREFIX . "durations:{$tenantId}", []);
        $avgDuration = empty($durations) ? 0.0 : round(array_sum($durations) / count($durations), 2);

        return [
            'active'          => (int) Cache::get(self::PREFIX . "active:{$tenantId}", 0),
            'total_today'     => (int) Cache::get(self::PREFIX . "total:{$tenantId}", 0),
            'reconnect_count' => (int) Cache::get(self::PREFIX . "reconnects:{$tenantId}", 0),
            'avg_duration_s'  => $avgDuration,
            'avg_latency_ms'  => $this->getAverageLatency($tenantId),
        ];
    }

    public function getTransportBreakdown(string $tenantId): array
    {
        $ws      = (int) Cache::get(self::PREFIX . "transport:websocket:{$tenantId}", 0);
        $polling = (int) Cache::get(self::PREFIX . "transport:polling:{$tenantId}", 0);
        $total   = $ws + $polling;
        if ($total === 0) return ['websocket_pct' => 0, 'polling_pct' => 0];
        return [
            'websocket_pct' => round($ws / $total * 100, 1),
            'polling_pct'   => round($polling / $total * 100, 1),
        ];
    }

    public function getPeakConnections(string $tenantId, int $hours = 24): array
    {
        return Cache::get(self::PREFIX . "peaks:{$tenantId}", []);
    }

    private function incrementTransport(string $tenantId, string $transport): void
    {
        Cache::increment(self::PREFIX . "transport:{$transport}:{$tenantId}");
    }
}
