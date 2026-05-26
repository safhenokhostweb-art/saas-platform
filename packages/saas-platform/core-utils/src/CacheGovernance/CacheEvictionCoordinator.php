<?php

namespace SaaSPlatform\CoreUtils\CacheGovernance;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class CacheEvictionCoordinator
{
    private const EVICTION_LOG_KEY = 'cache.eviction.log';
    private const LOG_TTL          = 3600;

    public function __construct(
        private RuntimeCachePartitioner    $partitioner,
        private TenantCacheNamespaceManager $namespaceManager,
        private TelemetryManager           $telemetry
    ) {}

    public function evictTenantCache(int $tenantId, string $reason): void
    {
        $this->partitioner->resetUsage($tenantId);

        $this->logEviction($tenantId, $reason);

        $this->telemetry->info('cache_governance', 'cache.eviction.tenant', [
            'tenant_id' => $tenantId,
            'reason'    => $reason,
        ]);
    }

    public function evictUnderPressure(array $tenantIds): array
    {
        $evicted = [];
        foreach ($tenantIds as $id) {
            if ($this->partitioner->isAtQuota($id)) {
                $this->evictTenantCache($id, 'quota_exceeded');
                $evicted[] = $id;
            }
        }
        return $evicted;
    }

    public function prioritizedEviction(array $tenantsByPriority): array
    {
        // Evict lowest-priority tenants first
        asort($tenantsByPriority);
        $evicted = [];

        foreach ($tenantsByPriority as $tenantId => $priority) {
            if ($this->partitioner->isNearQuota($tenantId, 75.0)) {
                $this->evictTenantCache($tenantId, 'prioritized_eviction');
                $evicted[$tenantId] = $priority;
            }
        }

        return $evicted;
    }

    public function evictionLog(): array
    {
        return Cache::get(self::EVICTION_LOG_KEY, []);
    }

    private function logEviction(int $tenantId, string $reason): void
    {
        $log   = Cache::get(self::EVICTION_LOG_KEY, []);
        $log[] = [
            'tenant_id'  => $tenantId,
            'reason'     => $reason,
            'evicted_at' => now()->toIso8601String(),
        ];

        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }

        Cache::put(self::EVICTION_LOG_KEY, $log, self::LOG_TTL);
    }
}
