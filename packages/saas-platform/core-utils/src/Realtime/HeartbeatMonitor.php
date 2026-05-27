<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;

class HeartbeatMonitor
{
    private const HEARTBEAT_INTERVAL = 30; // seconds
    private const STALE_THRESHOLD    = 90; // seconds

    public function ping(string $connectionId, string $tenantId = ''): void
    {
        Cache::put("realtime:heartbeat:{$connectionId}", [
            'tenant_id'  => $tenantId,
            'last_ping'  => time(),
            'at'         => now()->toISOString(),
        ], now()->addSeconds(self::STALE_THRESHOLD * 2));
    }

    public function isAlive(string $connectionId): bool
    {
        $data = Cache::get("realtime:heartbeat:{$connectionId}");
        if (! $data) return false;
        return (time() - ($data['last_ping'] ?? 0)) < self::STALE_THRESHOLD;
    }

    public function getStaleConnections(): array
    {
        // In production, this would scan a connection registry
        // Here we return empty as Redis SCAN is infeasible without a connection set
        return [];
    }

    public function cleanup(): int
    {
        // Would iterate known connections and evict stale ones
        return 0;
    }

    public function intervalSeconds(): int
    {
        return self::HEARTBEAT_INTERVAL;
    }
}
