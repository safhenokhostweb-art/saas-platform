<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Tenant;

use Illuminate\Support\Facades\Cache;

class TenantRuntimeMetrics
{
    private const KEY_PREFIX     = 'telemetry.tenant.metrics.';
    private const WINDOW_SECONDS = 3600;

    public function recordRequest(int $tenantId, float $durationMs, int $statusCode): void
    {
        $key = self::KEY_PREFIX . $tenantId;

        $metrics = Cache::get($key, [
            'request_count'   => 0,
            'error_count'     => 0,
            'total_duration'  => 0.0,
            'status_codes'    => [],
        ]);

        $metrics['request_count']++;
        $metrics['total_duration'] += $durationMs;

        $statusGroup = (string) intdiv($statusCode, 100) . 'xx';
        $metrics['status_codes'][$statusGroup] = ($metrics['status_codes'][$statusGroup] ?? 0) + 1;

        if ($statusCode >= 500) {
            $metrics['error_count']++;
        }

        Cache::put($key, $metrics, self::WINDOW_SECONDS);
    }

    public function getForTenant(int $tenantId): array
    {
        $key     = self::KEY_PREFIX . $tenantId;
        $metrics = Cache::get($key, []);

        if (empty($metrics)) {
            return ['available' => false];
        }

        $count = $metrics['request_count'] ?? 0;

        return [
            'available'           => true,
            'request_count'       => $count,
            'error_count'         => $metrics['error_count'] ?? 0,
            'error_rate'          => $count > 0 ? round(($metrics['error_count'] / $count) * 100, 2) : 0,
            'avg_duration_ms'     => $count > 0 ? round($metrics['total_duration'] / $count, 2) : 0,
            'status_distribution' => $metrics['status_codes'] ?? [],
        ];
    }

    public function clearForTenant(int $tenantId): void
    {
        Cache::forget(self::KEY_PREFIX . $tenantId);
    }
}
