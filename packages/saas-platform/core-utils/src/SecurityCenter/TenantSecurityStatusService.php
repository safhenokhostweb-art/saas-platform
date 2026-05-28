<?php

namespace SaaSPlatform\CoreUtils\SecurityCenter;

use Illuminate\Support\Facades\Cache;

class TenantSecurityStatusService
{
    private const STATUS_CACHE_TTL = 300;
    private const STATUS_KEY       = 'security:tenant_status:';

    public function getStatus(string $tenantId = 'global'): array
    {
        return Cache::remember(self::STATUS_KEY . $tenantId, self::STATUS_CACHE_TTL, function () use ($tenantId) {
            return $this->buildStatus($tenantId);
        });
    }

    public function getAllTenantsStatus(): array
    {
        $tenantIds = $this->getRegisteredTenantIds();
        $statuses  = [];
        foreach ($tenantIds as $tenantId) {
            $statuses[$tenantId] = $this->getStatus($tenantId);
        }
        return $statuses;
    }

    public function getVulnerableModules(string $tenantId): array
    {
        $status = $this->getStatus($tenantId);
        return $status['vulnerable_modules'] ?? [];
    }

    public function getExpiredModules(string $tenantId): array
    {
        $status = $this->getStatus($tenantId);
        return $status['expired_modules'] ?? [];
    }

    public function getPatchRiskLevel(string $tenantId): string
    {
        $pending  = Cache::get("security:patches:pending:{$tenantId}", []);
        $critical = array_filter($pending, fn ($p) => $p['severity'] === 'critical');
        $high     = array_filter($pending, fn ($p) => $p['severity'] === 'high');

        if (count($critical) > 0) return 'critical';
        if (count($high) > 2)    return 'high';
        if (count($high) > 0)    return 'medium';
        return 'low';
    }

    public function getCompatibilityStatus(string $tenantId): array
    {
        return Cache::get("security:compat:{$tenantId}", [
            'compatible'       => true,
            'incompatible_modules' => [],
            'checked_at'       => now()->toIso8601String(),
        ]);
    }

    public function setAutoUpdate(string $tenantId, bool $enabled): void
    {
        $status = $this->getStatus($tenantId);
        $status['auto_update_enabled'] = $enabled;
        Cache::put(self::STATUS_KEY . $tenantId, $status, self::STATUS_CACHE_TTL);
    }

    public function recordPatchOperation(string $tenantId, string $patchId, string $operation): void
    {
        $status = $this->getStatus($tenantId);
        $status['last_patch_at']    = now()->toIso8601String();
        $status['last_patch_id']    = $patchId;
        $status['last_operation']   = $operation;
        $status['rollback_available'] = ($operation === 'installed');
        Cache::put(self::STATUS_KEY . $tenantId, $status, self::STATUS_CACHE_TTL);
    }

    private function buildStatus(string $tenantId): array
    {
        return [
            'tenant_id'           => $tenantId,
            'auto_update_enabled' => (bool) config('security-center.auto_patch', false),
            'runtime_health'      => 'healthy',
            'last_patch_at'       => Cache::get("security:last_patch_at:{$tenantId}"),
            'last_operation'      => Cache::get("security:last_operation:{$tenantId}", 'none'),
            'rollback_available'  => Cache::get("security:rollback_available:{$tenantId}", false),
            'vulnerable_modules'  => [],
            'expired_modules'     => [],
            'security_posture'    => 'good',
            'patch_window'        => config('security-center.patch_window', '02:00-04:00'),
            'timezone'            => 'UTC',
            'updated_at'          => now()->toIso8601String(),
        ];
    }

    private function getRegisteredTenantIds(): array
    {
        return Cache::get('security:registered_tenant_ids', ['global']);
    }
}
