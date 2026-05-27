<?php

namespace SaaSPlatform\CoreUtils\Realtime;

use Illuminate\Support\Facades\Cache;

class ConnectionHealthMonitor
{
    private const HEALTH_PREFIX = 'conn:health:';

    public function recordPing(string $tenantId, int|string $userId, string $connectionType = 'polling'): void
    {
        $key = $this->healthKey($tenantId, $userId);
        Cache::put($key, [
            'user_id'         => $userId,
            'tenant_id'       => $tenantId,
            'connection_type' => $connectionType,
            'last_ping'       => now()->toIso8601String(),
            'ping_count'      => ((Cache::get($key)['ping_count'] ?? 0) + 1),
            'status'          => 'healthy',
        ], 300);
    }

    public function getStatus(string $tenantId, int|string $userId): string
    {
        $data = Cache::get($this->healthKey($tenantId, $userId));
        if (! $data) {
            return 'disconnected';
        }
        $lastPing = \Carbon\Carbon::parse($data['last_ping']);
        if ($lastPing->diffInSeconds(now()) > 120) {
            return 'stale';
        }
        return $data['status'] ?? 'healthy';
    }

    public function getTenantHealth(string $tenantId): array
    {
        return [
            'tenant_id'   => $tenantId,
            'checked_at'  => now()->toIso8601String(),
            'transport'   => $this->detectTransport(),
            'degraded'    => false,
            'fallback_mode' => ! $this->isWebSocketAvailable(),
        ];
    }

    public function isWebSocketAvailable(): bool
    {
        return config('broadcasting.default') !== 'null' &&
               config('broadcasting.default') !== null &&
               ! empty(config('broadcasting.connections.' . config('broadcasting.default') . '.key'));
    }

    public function detectTransport(): string
    {
        if ($this->isWebSocketAvailable()) {
            return 'websocket';
        }
        return 'polling';
    }

    public function getTransportCapabilities(): array
    {
        return [
            'websocket' => $this->isWebSocketAvailable(),
            'polling'   => true,
            'sse'       => false,
            'recommended' => $this->detectTransport(),
        ];
    }

    private function healthKey(string $tenantId, int|string $userId): string
    {
        return self::HEALTH_PREFIX . "tenant:{$tenantId}:user:{$userId}";
    }
}
