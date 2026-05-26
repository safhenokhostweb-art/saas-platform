<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor;

use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantConsumptionTracker;
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantQuotaManager;

class TenantPressureAnalyzer
{
    private const HIGH_UTILIZATION_PCT  = 70.0;
    private const NOISY_UTILIZATION_PCT = 90.0;

    public function __construct(
        private TenantConsumptionTracker $tracker,
        private TenantQuotaManager       $quotaManager
    ) {}

    public function pressureScore(int $tenantId): float
    {
        $summary = $this->tracker->consumptionSummary($tenantId);
        if (empty($summary)) {
            return 0.0;
        }

        $utilizations = array_column($summary, 'utilization');
        return count($utilizations) > 0 ? max($utilizations) : 0.0;
    }

    public function isHighPressure(int $tenantId): bool
    {
        return $this->pressureScore($tenantId) >= self::HIGH_UTILIZATION_PCT;
    }

    public function isNoisy(int $tenantId): bool
    {
        return $this->pressureScore($tenantId) >= self::NOISY_UTILIZATION_PCT;
    }

    public function pressureLevel(int $tenantId): string
    {
        $score = $this->pressureScore($tenantId);
        return match (true) {
            $score >= self::NOISY_UTILIZATION_PCT => 'noisy',
            $score >= self::HIGH_UTILIZATION_PCT  => 'high',
            $score >= 40.0                         => 'moderate',
            default                                => 'normal',
        };
    }

    public function analyzePlatformPressure(array $tenantIds): array
    {
        $byLevel  = ['noisy' => [], 'high' => [], 'moderate' => [], 'normal' => []];
        $scores   = [];

        foreach ($tenantIds as $id) {
            $level      = $this->pressureLevel($id);
            $scores[$id] = $this->pressureScore($id);
            $byLevel[$level][] = $id;
        }

        arsort($scores);

        return [
            'by_level'     => $byLevel,
            'top_consumers' => array_slice(array_keys($scores), 0, 10, true),
            'noisy_count'  => count($byLevel['noisy']),
            'high_count'   => count($byLevel['high']),
            'platform_pressure' => $this->platformPressureLevel($byLevel, count($tenantIds)),
        ];
    }

    private function platformPressureLevel(array $byLevel, int $total): string
    {
        if ($total === 0) {
            return 'normal';
        }
        $noisyPct = count($byLevel['noisy']) / $total * 100;
        return match (true) {
            $noisyPct >= 20 => 'critical',
            $noisyPct >= 10 => 'high',
            $noisyPct >= 5  => 'elevated',
            default         => 'normal',
        };
    }
}
