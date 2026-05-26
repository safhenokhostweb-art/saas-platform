<?php

namespace SaaSPlatform\CoreUtils\StorageGovernance;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class StoragePressureAnalyzer
{
    private const WARNING_PCT  = 75.0;
    private const CRITICAL_PCT = 90.0;

    public function __construct(
        private RuntimeStorageQuotaService $quotaService,
        private TelemetryManager           $telemetry
    ) {}

    public function pressureLevel(int $tenantId): string
    {
        $utilization = $this->quotaService->utilizationPct($tenantId);
        return match (true) {
            $utilization >= self::CRITICAL_PCT => 'critical',
            $utilization >= self::WARNING_PCT  => 'warning',
            default                            => 'normal',
        };
    }

    public function scanAll(array $tenantIds): array
    {
        $pressured = [];

        foreach ($tenantIds as $id) {
            $level = $this->pressureLevel($id);
            if ($level !== 'normal') {
                $stats       = $this->quotaService->stats($id);
                $pressured[$id] = array_merge($stats, ['pressure_level' => $level]);

                if ($level === 'critical') {
                    $this->telemetry->error('storage', 'storage.pressure.critical', [
                        'tenant_id'   => $id,
                        'utilization' => $stats['utilization_pct'],
                    ]);
                }
            }
        }

        return $pressured;
    }

    public function platformStoragePressure(array $tenantIds): string
    {
        if (empty($tenantIds)) {
            return 'normal';
        }

        $critical = 0;
        $warning  = 0;

        foreach ($tenantIds as $id) {
            $level = $this->pressureLevel($id);
            if ($level === 'critical') {
                $critical++;
            } elseif ($level === 'warning') {
                $warning++;
            }
        }

        $total   = count($tenantIds);
        $critPct = $critical / $total * 100;
        $warnPct = $warning / $total * 100;

        return match (true) {
            $critPct >= 20 => 'critical',
            $critPct >= 10 => 'high',
            $warnPct >= 30 => 'elevated',
            default        => 'normal',
        };
    }

    public function topConsumers(array $tenantIds, int $limit = 10): array
    {
        $usage = [];
        foreach ($tenantIds as $id) {
            $usage[$id] = $this->quotaService->utilizationPct($id);
        }
        arsort($usage);
        return array_slice($usage, 0, $limit, true);
    }
}
