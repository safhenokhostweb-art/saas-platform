<?php

namespace SaaSPlatform\CoreUtils\CapacityGovernance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CapacityForecastEngine
{
    private const FORECAST_CACHE_TTL = 300;

    public function __construct(private CapacityThresholdManager $thresholds) {}

    public function forecastQueueSaturation(int $hoursAhead = 4): array
    {
        $key = 'capacity.forecast.queue.' . $hoursAhead;
        return Cache::remember($key, self::FORECAST_CACHE_TTL, function () use ($hoursAhead) {
            $current = $this->currentQueueDepth();
            $growthRate = $this->queueGrowthRate();
            $projected = $current + ($growthRate * $hoursAhead);

            $criticalThreshold = $this->thresholds->get('queue_depth_critical');

            return [
                'current_depth'      => $current,
                'growth_rate_per_hour' => round($growthRate, 1),
                'projected_in_hours' => $hoursAhead,
                'projected_depth'    => round($projected),
                'saturation_risk'    => $this->thresholds->level('queue_depth', $projected),
                'hours_to_critical'  => $growthRate > 0
                    ? round(max(0, ($criticalThreshold - $current) / $growthRate), 1)
                    : null,
            ];
        });
    }

    public function forecastStorageGrowth(int $daysAhead = 30): array
    {
        $key = 'capacity.forecast.storage.' . $daysAhead;
        return Cache::remember($key, self::FORECAST_CACHE_TTL, function () use ($daysAhead) {
            $dailyGrowthMb = $this->estimateDailyStorageGrowthMb();
            $projected     = $dailyGrowthMb * $daysAhead;

            return [
                'daily_growth_mb'  => round($dailyGrowthMb, 2),
                'days_ahead'       => $daysAhead,
                'projected_growth_mb' => round($projected, 2),
                'projected_growth_gb' => round($projected / 1024, 2),
            ];
        });
    }

    public function forecastTenantExpansionImpact(int $additionalTenants): array
    {
        $current    = $this->estimateCurrentTenantCount();
        $projected  = $current + $additionalTenants;
        $densityLevel = $this->thresholds->level('tenant_density', $projected);

        return [
            'current_tenants'   => $current,
            'additional'        => $additionalTenants,
            'projected_total'   => $projected,
            'density_level'     => $densityLevel,
            'queue_pressure_est' => round($additionalTenants * 50),
            'memory_increase_est_pct' => round($additionalTenants / max(1, $current) * 100 * 0.8, 1),
        ];
    }

    private function currentQueueDepth(): int
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                return 0;
            }
            return DB::table('jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function queueGrowthRate(): float
    {
        // Estimate from recent failed_jobs trend vs current depth
        // In production this would use time-series data; here we estimate
        $depth = $this->currentQueueDepth();
        return $depth > 100 ? $depth * 0.05 : 0.0;
    }

    private function estimateDailyStorageGrowthMb(): float
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                return 50.0; // default estimate
            }
            $rowsToday = DB::table('telemetry_events')
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
            return $rowsToday * 0.002; // ~2KB per event estimate
        } catch (\Throwable) {
            return 50.0;
        }
    }

    private function estimateCurrentTenantCount(): int
    {
        try {
            foreach (['sites', 'tenants', 'tenant_sites'] as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    return DB::table($table)->count();
                }
            }
        } catch (\Throwable) {}
        return 0;
    }
}
