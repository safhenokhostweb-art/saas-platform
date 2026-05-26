<?php

namespace SaaSPlatform\CoreUtils\StorageGovernance;

use Illuminate\Support\Facades\Cache;

class RuntimeStorageQuotaService
{
    private const QUOTA_PREFIX = 'storage.quota.tenant.';
    private const USAGE_PREFIX = 'storage.usage.tenant.';
    private const QUOTA_TTL   = 86400;

    private const PLAN_QUOTAS_MB = [
        'trial'        => 100,
        'starter'      => 1024,
        'professional' => 10240,
        'enterprise'   => 102400,
    ];

    public function setQuota(int $tenantId, int $quotaMb): void
    {
        Cache::put(self::QUOTA_PREFIX . $tenantId, $quotaMb, self::QUOTA_TTL);
    }

    public function setQuotaForPlan(int $tenantId, string $plan): void
    {
        $quota = self::PLAN_QUOTAS_MB[$plan] ?? self::PLAN_QUOTAS_MB['starter'];
        $this->setQuota($tenantId, $quota);
    }

    public function quota(int $tenantId): int
    {
        return (int) Cache::get(self::QUOTA_PREFIX . $tenantId, self::PLAN_QUOTAS_MB['starter']);
    }

    public function recordUsage(int $tenantId, float $sizeMb): void
    {
        $key     = self::USAGE_PREFIX . $tenantId;
        $current = (float) Cache::get($key, 0.0);
        Cache::put($key, $current + $sizeMb, self::QUOTA_TTL);
    }

    public function usageMb(int $tenantId): float
    {
        return (float) Cache::get(self::USAGE_PREFIX . $tenantId, 0.0);
    }

    public function utilizationPct(int $tenantId): float
    {
        $quota = $this->quota($tenantId);
        return $quota > 0 ? min(100.0, round($this->usageMb($tenantId) / $quota * 100, 1)) : 0.0;
    }

    public function isExceeded(int $tenantId): bool
    {
        return $this->usageMb($tenantId) >= $this->quota($tenantId);
    }

    public function remainingMb(int $tenantId): float
    {
        return max(0.0, $this->quota($tenantId) - $this->usageMb($tenantId));
    }

    public function stats(int $tenantId): array
    {
        return [
            'tenant_id'      => $tenantId,
            'quota_mb'       => $this->quota($tenantId),
            'usage_mb'       => round($this->usageMb($tenantId), 2),
            'remaining_mb'   => round($this->remainingMb($tenantId), 2),
            'utilization_pct' => $this->utilizationPct($tenantId),
            'exceeded'       => $this->isExceeded($tenantId),
        ];
    }
}
