<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation;

use Illuminate\Support\Facades\Cache;

class TenantRuntimeBudget
{
    private const BUDGET_PREFIX    = 'isolation.budget.tenant.';
    private const WINDOW_SECONDS   = 3600;

    public function charge(int $tenantId, string $resource, float $amount): void
    {
        $key     = $this->bucketKey($tenantId, $resource);
        $current = (float) Cache::get($key, 0.0);
        Cache::put($key, $current + $amount, self::WINDOW_SECONDS);
    }

    public function consumed(int $tenantId, string $resource): float
    {
        return (float) Cache::get($this->bucketKey($tenantId, $resource), 0.0);
    }

    public function utilizationPct(int $tenantId, string $resource, int $ceiling): float
    {
        if ($ceiling <= 0) {
            return 0.0;
        }
        return min(100.0, round($this->consumed($tenantId, $resource) / $ceiling * 100, 2));
    }

    public function isExhausted(int $tenantId, string $resource, int $ceiling): bool
    {
        return $this->consumed($tenantId, $resource) >= $ceiling;
    }

    public function isNearCeiling(int $tenantId, string $resource, int $ceiling, float $pct = 80.0): bool
    {
        return $this->utilizationPct($tenantId, $resource, $ceiling) >= $pct;
    }

    public function reset(int $tenantId, string $resource): void
    {
        Cache::forget($this->bucketKey($tenantId, $resource));
    }

    public function snapshot(int $tenantId): array
    {
        $resources = ['max_requests_per_minute', 'max_queue_jobs_per_hour', 'max_runtime_seconds_per_hour'];
        $snap = [];
        foreach ($resources as $resource) {
            $snap[$resource] = $this->consumed($tenantId, $resource);
        }
        return $snap;
    }

    private function bucketKey(int $tenantId, string $resource): string
    {
        // Sliding hourly window key
        $window = (int) floor(time() / self::WINDOW_SECONDS);
        return self::BUDGET_PREFIX . $tenantId . '.' . $resource . '.' . $window;
    }
}
