<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Telemetry;

use Illuminate\Support\Facades\Cache;

class MobileDeliveryAnalytics
{
    private const PREFIX = 'mobile:analytics:delivery:';
    private const TTL    = 3600;

    public function recordDelivery(string $tenantId, string $type, string $targetId, bool $success, float $latencyMs = 0): void
    {
        $key    = self::PREFIX . "{$type}:{$tenantId}";
        $stats  = Cache::get($key, ['sent' => 0, 'delivered' => 0, 'failed' => 0, 'latencies' => []]);
        $stats['sent']++;
        $success ? $stats['delivered']++ : $stats['failed']++;
        if ($latencyMs > 0) {
            $stats['latencies'][] = $latencyMs;
            if (count($stats['latencies']) > 200) {
                $stats['latencies'] = array_slice($stats['latencies'], -200);
            }
        }
        Cache::put($key, $stats, self::TTL);
    }

    public function getDeliveryRate(string $tenantId, string $type = 'all', int $hours = 24): float
    {
        $stats = $this->getDeliveryStats($tenantId);
        $data  = $type === 'all'
            ? array_merge($stats['messages'], $stats['notifications'])
            : ($stats[$type] ?? ['sent' => 0, 'delivered' => 0]);
        $sent  = $data['sent'] ?? 0;
        return $sent > 0 ? round(($data['delivered'] ?? 0) / $sent, 4) : 0.0;
    }

    public function getAverageLatency(string $tenantId, string $type = 'all'): float
    {
        $key     = self::PREFIX . "{$type}:{$tenantId}";
        $stats   = Cache::get($key, []);
        $latencies = $stats['latencies'] ?? [];
        return empty($latencies) ? 0.0 : round(array_sum($latencies) / count($latencies), 2);
    }

    public function getDeliveryStats(string $tenantId): array
    {
        $defaults = ['sent' => 0, 'delivered' => 0, 'failed' => 0];
        return [
            'messages'      => Cache::get(self::PREFIX . "messages:{$tenantId}", $defaults),
            'notifications' => Cache::get(self::PREFIX . "notifications:{$tenantId}", $defaults),
            'push'          => Cache::get(self::PREFIX . "push:{$tenantId}", $defaults),
        ];
    }

    public function getDeliveryTrend(string $tenantId, int $hours = 24): array
    {
        return Cache::get(self::PREFIX . "trend:{$tenantId}", []);
    }

    public function getFailureReasons(string $tenantId, int $hours = 24): array
    {
        return Cache::get(self::PREFIX . "failures:{$tenantId}", []);
    }

    public function getDailyReport(string $tenantId): array
    {
        return [
            'tenant_id'   => $tenantId,
            'date'        => now()->format('Y-m-d'),
            'stats'       => $this->getDeliveryStats($tenantId),
            'avg_latency' => $this->getAverageLatency($tenantId),
            'delivery_rate' => $this->getDeliveryRate($tenantId),
        ];
    }
}
