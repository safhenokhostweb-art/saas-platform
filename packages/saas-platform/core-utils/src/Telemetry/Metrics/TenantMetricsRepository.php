<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Metrics;

use Illuminate\Support\Facades\DB;

class TenantMetricsRepository
{
    public function allTenantSummary(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                return ['available' => false];
            }

            $byTenant = DB::table('telemetry_events')
                ->whereNotNull('tenant_id')
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('tenant_id, count(*) as event_count, SUM(CASE WHEN severity IN ("error","critical") THEN 1 ELSE 0 END) as error_count')
                ->groupBy('tenant_id')
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();

            return [
                'available'   => true,
                'by_tenant'   => $byTenant,
                'window_hours' => 24,
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }

    public function forTenant(int $tenantId, int $hours = 24): array
    {
        // Read-only; scoped strictly to the specified tenant_id
        try {
            if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                return ['available' => false];
            }

            $events = DB::table('telemetry_events')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subHours($hours))
                ->selectRaw('severity, event, count(*) as count')
                ->groupBy('severity', 'event')
                ->orderByDesc('count')
                ->limit(20)
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();

            $errorCount = DB::table('telemetry_events')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subHours($hours))
                ->whereIn('severity', ['error', 'critical'])
                ->count();

            return [
                'available'    => true,
                'tenant_id'    => $tenantId,
                'window_hours' => $hours,
                'error_count'  => $errorCount,
                'top_events'   => $events,
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }

    public function maintenanceSummary(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('tenant_maintenance')) {
                return ['available' => false];
            }

            $active = DB::table('tenant_maintenance')
                ->whereNull('ended_at')
                ->count();

            $last24h = DB::table('tenant_maintenance')
                ->where('started_at', '>=', now()->subDay()->toDateTimeString())
                ->count();

            return [
                'available'        => true,
                'active_windows'   => $active,
                'windows_last_24h' => $last24h,
            ];
        } catch (\Throwable) {
            return ['available' => false];
        }
    }
}
