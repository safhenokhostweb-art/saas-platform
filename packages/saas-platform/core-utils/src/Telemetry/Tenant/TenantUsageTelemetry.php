<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Tenant;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class TenantUsageTelemetry
{
    private const USAGE_KEY_PREFIX = 'telemetry.tenant.usage.';
    private const USAGE_WINDOW     = 86400; // 24 hours

    public function __construct(private TelemetryManager $telemetry) {}

    public function recordModuleUsage(int $tenantId, string $module, string $action): void
    {
        $key = self::USAGE_KEY_PREFIX . $tenantId . '.modules';

        $usage = Cache::get($key, []);
        $usage[$module][$action] = ($usage[$module][$action] ?? 0) + 1;

        Cache::put($key, $usage, self::USAGE_WINDOW);

        $this->telemetry->debug('tenant', 'tenant.module.usage', [
            'tenant_id' => $tenantId,
            'module'    => $module,
            'action'    => $action,
        ]);
    }

    public function recordFeatureAccess(int $tenantId, string $feature): void
    {
        $key = self::USAGE_KEY_PREFIX . $tenantId . '.features';

        $usage = Cache::get($key, []);
        $usage[$feature] = ($usage[$feature] ?? 0) + 1;

        Cache::put($key, $usage, self::USAGE_WINDOW);
    }

    public function getUsageForTenant(int $tenantId): array
    {
        return [
            'tenant_id'      => $tenantId,
            'module_usage'   => Cache::get(self::USAGE_KEY_PREFIX . $tenantId . '.modules', []),
            'feature_access' => Cache::get(self::USAGE_KEY_PREFIX . $tenantId . '.features', []),
            'window_hours'   => 24,
        ];
    }

    public function clearForTenant(int $tenantId): void
    {
        Cache::forget(self::USAGE_KEY_PREFIX . $tenantId . '.modules');
        Cache::forget(self::USAGE_KEY_PREFIX . $tenantId . '.features');
    }
}
