<?php

namespace SaaSPlatform\CoreUtils\CacheGovernance;

use Illuminate\Support\Facades\Cache;

class RuntimeCachePartitioner
{
    private const QUOTA_PREFIX  = 'cache.quota.tenant.';
    private const USAGE_PREFIX  = 'cache.usage.tenant.';
    private const DEFAULT_QUOTA_KB = 5120; // 5 MB per tenant by default
    private const TTL = 3600;

    public function __construct(private TenantCacheNamespaceManager $namespaceManager) {}

    public function setQuota(int $tenantId, int $quotaKb): void
    {
        Cache::put(self::QUOTA_PREFIX . $tenantId, $quotaKb, self::TTL);
    }

    public function quota(int $tenantId): int
    {
        return (int) Cache::get(self::QUOTA_PREFIX . $tenantId, self::DEFAULT_QUOTA_KB);
    }

    public function recordUsage(int $tenantId, int $estimatedBytes): void
    {
        $key     = self::USAGE_PREFIX . $tenantId;
        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + (int) ceil($estimatedBytes / 1024), self::TTL);
    }

    public function usageKb(int $tenantId): int
    {
        return (int) Cache::get(self::USAGE_PREFIX . $tenantId, 0);
    }

    public function utilizationPct(int $tenantId): float
    {
        $quota = $this->quota($tenantId);
        return $quota > 0 ? min(100.0, round($this->usageKb($tenantId) / $quota * 100, 1)) : 0.0;
    }

    public function isNearQuota(int $tenantId, float $threshold = 80.0): bool
    {
        return $this->utilizationPct($tenantId) >= $threshold;
    }

    public function isAtQuota(int $tenantId): bool
    {
        return $this->usageKb($tenantId) >= $this->quota($tenantId);
    }

    public function resetUsage(int $tenantId): void
    {
        Cache::forget(self::USAGE_PREFIX . $tenantId);
    }

    public function partitionStats(int $tenantId): array
    {
        return [
            'tenant_id'      => $tenantId,
            'quota_kb'       => $this->quota($tenantId),
            'usage_kb'       => $this->usageKb($tenantId),
            'utilization_pct' => $this->utilizationPct($tenantId),
            'near_quota'     => $this->isNearQuota($tenantId),
            'at_quota'       => $this->isAtQuota($tenantId),
        ];
    }
}
