<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Tenant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TenantIncidentTracker
{
    private const INCIDENT_CACHE_PREFIX = 'telemetry.tenant.incidents.';
    private const INCIDENT_WINDOW       = 3600;

    public function recordError(int $tenantId, string $error): void
    {
        $key = self::INCIDENT_CACHE_PREFIX . $tenantId;

        $incidents = Cache::get($key, []);
        $incidents[] = [
            'type'       => 'error',
            'message'    => substr($error, 0, 256),
            'recorded_at' => now()->toIso8601String(),
        ];

        // Keep last 50 incidents in cache per tenant
        if (count($incidents) > 50) {
            $incidents = array_slice($incidents, -50);
        }

        Cache::put($key, $incidents, self::INCIDENT_WINDOW);
    }

    public function recordMaintenanceEvent(int $tenantId, string $type, string $scope): void
    {
        $key = self::INCIDENT_CACHE_PREFIX . $tenantId;

        $incidents   = Cache::get($key, []);
        $incidents[] = [
            'type'        => 'maintenance.' . $type,
            'scope'       => $scope,
            'recorded_at' => now()->toIso8601String(),
        ];

        Cache::put($key, $incidents, self::INCIDENT_WINDOW);
    }

    public function getForTenant(int $tenantId): array
    {
        $key       = self::INCIDENT_CACHE_PREFIX . $tenantId;
        $incidents = Cache::get($key, []);

        return [
            'count'     => count($incidents),
            'incidents' => array_slice(array_reverse($incidents), 0, 10),
        ];
    }

    public function clearForTenant(int $tenantId): void
    {
        Cache::forget(self::INCIDENT_CACHE_PREFIX . $tenantId);
    }

    public function maintenanceHistory(int $tenantId): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('tenant_maintenance')) {
                return [];
            }

            return DB::table('tenant_maintenance')
                ->where('site_id', $tenantId)
                ->orderByDesc('started_at')
                ->limit(10)
                ->get()
                ->map(fn($r) => [
                    'scope'      => $r->scope,
                    'message'    => $r->message,
                    'started_at' => $r->started_at,
                    'ended_at'   => $r->ended_at,
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
