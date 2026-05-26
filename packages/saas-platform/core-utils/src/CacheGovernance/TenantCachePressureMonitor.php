<?php

namespace SaaSPlatform\CoreUtils\CacheGovernance;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use Illuminate\Support\Facades\Cache;

class TenantCachePressureMonitor
{
    private const PRESSURE_PREFIX = 'cache.pressure.tenant.';
    private const PRESSURE_TTL   = 300;

    public function __construct(
        private RuntimeCachePartitioner $partitioner,
        private TelemetryManager        $telemetry
    ) {}

    public function scan(array $tenantIds): array
    {
        $pressured = [];

        foreach ($tenantIds as $id) {
            $stats = $this->partitioner->partitionStats($id);

            if ($stats['at_quota']) {
                $this->recordPressure($id, 'at_quota', $stats['utilization_pct']);
                $pressured[$id] = ['level' => 'critical', 'utilization' => $stats['utilization_pct']];

                $this->telemetry->error('cache_governance', 'cache.tenant.quota_exceeded', [
                    'tenant_id'   => $id,
                    'utilization' => $stats['utilization_pct'],
                ]);
            } elseif ($stats['near_quota']) {
                $this->recordPressure($id, 'near_quota', $stats['utilization_pct']);
                $pressured[$id] = ['level' => 'warning', 'utilization' => $stats['utilization_pct']];

                $this->telemetry->warning('cache_governance', 'cache.tenant.near_quota', [
                    'tenant_id'   => $id,
                    'utilization' => $stats['utilization_pct'],
                ]);
            }
        }

        return $pressured;
    }

    public function pressureLevel(int $tenantId): string
    {
        $record = Cache::get(self::PRESSURE_PREFIX . $tenantId);
        return $record['level'] ?? 'normal';
    }

    public function platformCachePressure(array $tenantIds): string
    {
        if (empty($tenantIds)) {
            return 'normal';
        }

        $pressured = count(array_filter($tenantIds, fn($id) => $this->pressureLevel($id) !== 'normal'));
        $pct       = $pressured / count($tenantIds) * 100;

        return match (true) {
            $pct >= 30 => 'critical',
            $pct >= 15 => 'high',
            $pct >= 5  => 'elevated',
            default    => 'normal',
        };
    }

    private function recordPressure(int $tenantId, string $level, float $utilization): void
    {
        Cache::put(self::PRESSURE_PREFIX . $tenantId, [
            'level'       => $level,
            'utilization' => $utilization,
            'recorded_at' => now()->toIso8601String(),
        ], self::PRESSURE_TTL);
    }
}
