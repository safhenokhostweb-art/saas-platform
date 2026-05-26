<?php

namespace SaaSPlatform\CoreUtils\CapacityGovernance;

use Illuminate\Support\Facades\Cache;

class CapacityThresholdManager
{
    private const THRESHOLDS_KEY = 'capacity.thresholds';
    private const TTL            = 86400;

    private const DEFAULTS = [
        'queue_depth_warning'   => 500,
        'queue_depth_critical'  => 1000,
        'memory_usage_warning'  => 70.0,
        'memory_usage_critical' => 85.0,
        'tenant_density_warning' => 80,
        'tenant_density_critical' => 150,
        'storage_warning_pct'   => 70.0,
        'storage_critical_pct'  => 90.0,
        'response_time_warning' => 500,   // ms
        'response_time_critical' => 2000, // ms
    ];

    public function get(string $threshold): float|int
    {
        $thresholds = Cache::get(self::THRESHOLDS_KEY, self::DEFAULTS);
        return $thresholds[$threshold] ?? self::DEFAULTS[$threshold] ?? 0;
    }

    public function set(string $threshold, float|int $value): void
    {
        $thresholds = Cache::get(self::THRESHOLDS_KEY, self::DEFAULTS);
        $thresholds[$threshold] = $value;
        Cache::put(self::THRESHOLDS_KEY, $thresholds, self::TTL);
    }

    public function all(): array
    {
        return Cache::get(self::THRESHOLDS_KEY, self::DEFAULTS);
    }

    public function isExceeded(string $metric, float|int $value): bool
    {
        return $value >= $this->get($metric . '_critical');
    }

    public function isWarning(string $metric, float|int $value): bool
    {
        $warning  = $this->get($metric . '_warning');
        $critical = $this->get($metric . '_critical');
        return $value >= $warning && $value < $critical;
    }

    public function level(string $metric, float|int $value): string
    {
        if ($this->isExceeded($metric, $value)) {
            return 'critical';
        }
        if ($this->isWarning($metric, $value)) {
            return 'warning';
        }
        return 'normal';
    }

    public function reset(): void
    {
        Cache::forget(self::THRESHOLDS_KEY);
    }
}
