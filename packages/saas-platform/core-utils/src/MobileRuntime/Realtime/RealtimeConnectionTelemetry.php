<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RealtimeConnectionTelemetry
{
    private const PREFIX = 'mobile:telemetry:rt:';
    private const TTL    = 3600;

    public function recordConnection(string $tenantId, int|string $userId, string $deviceId, array $meta = []): void
    {
        Cache::increment(self::PREFIX . "active:{$tenantId}");
        Cache::increment(self::PREFIX . "total:{$tenantId}");
        $this->appendEvent($tenantId, 'connect', [
            'user_id'   => $userId,
            'device_id' => $deviceId,
            'at'        => now()->toIso8601String(),
            'meta'      => $meta,
        ]);
    }

    public function recordDisconnection(string $tenantId, int|string $userId, string $deviceId, string $reason = 'normal'): void
    {
        $active = (int) Cache::get(self::PREFIX . "active:{$tenantId}", 0);
        Cache::put(self::PREFIX . "active:{$tenantId}", max(0, $active - 1), self::TTL);
        $this->appendEvent($tenantId, 'disconnect', [
            'user_id'   => $userId,
            'device_id' => $deviceId,
            'reason'    => $reason,
            'at'        => now()->toIso8601String(),
        ]);
    }

    public function recordError(string $tenantId, string $error, array $context = []): void
    {
        Cache::increment(self::PREFIX . "errors:{$tenantId}");
        Log::warning('mobile.rt.error', array_merge(['tenant_id' => $tenantId, 'error' => $error], $context));
    }

    public function getConnectionStats(string $tenantId): array
    {
        return [
            'active'       => (int) Cache::get(self::PREFIX . "active:{$tenantId}", 0),
            'total_today'  => (int) Cache::get(self::PREFIX . "total:{$tenantId}", 0),
            'error_count'  => (int) Cache::get(self::PREFIX . "errors:{$tenantId}", 0),
            'error_rate'   => $this->getErrorRate($tenantId),
        ];
    }

    public function getErrorRate(string $tenantId, int $minutes = 5): float
    {
        $errors = (int) Cache::get(self::PREFIX . "errors:{$tenantId}", 0);
        $total  = (int) Cache::get(self::PREFIX . "total:{$tenantId}", 1);
        return $total > 0 ? round($errors / $total, 4) : 0.0;
    }

    public function getActiveConnectionCount(string $tenantId): int
    {
        return (int) Cache::get(self::PREFIX . "active:{$tenantId}", 0);
    }

    public function flush(string $tenantId): void
    {
        foreach (['active', 'total', 'errors', 'events'] as $key) {
            Cache::forget(self::PREFIX . "{$key}:{$tenantId}");
        }
    }

    private function appendEvent(string $tenantId, string $type, array $data): void
    {
        $events   = Cache::get(self::PREFIX . "events:{$tenantId}", []);
        $events[] = array_merge(['type' => $type], $data);
        if (count($events) > 500) {
            $events = array_slice($events, -500);
        }
        Cache::put(self::PREFIX . "events:{$tenantId}", $events, self::TTL);
    }
}
