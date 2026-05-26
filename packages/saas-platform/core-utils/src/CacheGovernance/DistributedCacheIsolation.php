<?php

namespace SaaSPlatform\CoreUtils\CacheGovernance;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class DistributedCacheIsolation
{
    public function __construct(
        private TenantCacheNamespaceManager $namespaceManager,
        private RuntimeCachePartitioner     $partitioner,
        private TenantCachePressureMonitor  $monitor,
        private CacheEvictionCoordinator    $evictionCoordinator,
        private TelemetryManager            $telemetry
    ) {}

    public function put(int $tenantId, string $key, mixed $value, int $ttl = 3600): bool
    {
        if ($this->partitioner->isAtQuota($tenantId)) {
            $this->telemetry->warning('cache_governance', 'cache.isolation.write_rejected_quota', [
                'tenant_id' => $tenantId,
                'key'       => $key,
            ]);
            return false;
        }

        $namespaced = $this->namespaceManager->key($tenantId, $key);
        Cache::put($namespaced, $value, $ttl);

        $estimatedBytes = strlen(serialize($value));
        $this->partitioner->recordUsage($tenantId, $estimatedBytes);

        return true;
    }

    public function get(int $tenantId, string $key, mixed $default = null): mixed
    {
        $namespaced = $this->namespaceManager->key($tenantId, $key);
        return Cache::get($namespaced, $default);
    }

    public function forget(int $tenantId, string $key): void
    {
        $namespaced = $this->namespaceManager->key($tenantId, $key);
        Cache::forget($namespaced);
    }

    public function isolationHealth(array $tenantIds): array
    {
        $pressured       = $this->monitor->scan($tenantIds);
        $platformPressure = $this->monitor->platformCachePressure($tenantIds);

        if ($platformPressure === 'critical') {
            $evicted = $this->evictionCoordinator->evictUnderPressure($tenantIds);
            return [
                'platform_pressure' => $platformPressure,
                'pressured_tenants' => $pressured,
                'evicted_tenants'   => $evicted,
            ];
        }

        return [
            'platform_pressure' => $platformPressure,
            'pressured_tenants' => $pressured,
            'evicted_tenants'   => [],
        ];
    }

    public function isCrossTenatAccess(string $cacheKey, int $requestingTenantId): bool
    {
        $ownerTenantId = $this->namespaceManager->extractTenantId($cacheKey);
        return $ownerTenantId !== null && $ownerTenantId !== $requestingTenantId;
    }
}
