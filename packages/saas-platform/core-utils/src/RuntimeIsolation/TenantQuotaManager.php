<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation;

use Illuminate\Support\Facades\Cache;

class TenantQuotaManager
{
    private const QUOTA_PREFIX = 'isolation.quota.tenant.';
    private const QUOTA_TTL    = 3600;

    public function __construct(private TenantCapacityProfile $profile) {}

    public function setQuota(int $tenantId, string $plan, array $overrides = []): void
    {
        $base  = $this->profile->forPlan($plan);
        $quota = array_merge($base, $overrides, ['plan' => $plan, 'tenant_id' => $tenantId]);
        Cache::put(self::QUOTA_PREFIX . $tenantId, $quota, self::QUOTA_TTL);
    }

    public function getQuota(int $tenantId): array
    {
        return Cache::get(self::QUOTA_PREFIX . $tenantId, $this->profile->forPlan('starter'));
    }

    public function getLimit(int $tenantId, string $resource): int
    {
        return $this->getQuota($tenantId)[$resource] ?? 0;
    }

    public function getPlan(int $tenantId): string
    {
        return $this->getQuota($tenantId)['plan'] ?? 'starter';
    }

    public function getPriority(int $tenantId): int
    {
        $plan = $this->getPlan($tenantId);
        return $this->profile->priority($plan);
    }

    public function upgradeQuota(int $tenantId, string $newPlan, array $overrides = []): void
    {
        $this->setQuota($tenantId, $newPlan, $overrides);
    }

    public function applyEmergencyQuota(int $tenantId): void
    {
        $current = $this->getQuota($tenantId);
        $emergency = array_map(
            fn($v) => is_int($v) ? (int) ceil($v * 0.1) : $v,
            $current
        );
        $emergency['_emergency'] = true;
        Cache::put(self::QUOTA_PREFIX . $tenantId, $emergency, 1800);
    }

    public function isUnderEmergencyQuota(int $tenantId): bool
    {
        return (bool) ($this->getQuota($tenantId)['_emergency'] ?? false);
    }

    public function releaseEmergencyQuota(int $tenantId): void
    {
        $plan = $this->getPlan($tenantId);
        $this->setQuota($tenantId, $plan);
    }
}
