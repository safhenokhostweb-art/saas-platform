<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Metrics;

use Illuminate\Support\Facades\DB;

class RuntimeMetricsRepository
{
    public function current(): array
    {
        return [
            'memory_mb'      => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'php_version'    => PHP_VERSION,
            'hostname'       => gethostname() ?: 'unknown',
            'pid'            => getmypid(),
            'sapi'           => PHP_SAPI,
            'collected_at'   => now()->toIso8601String(),
        ];
    }

    public function eventCountBySeverity(int $hours = 24): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                return [];
            }

            return DB::table('telemetry_events')
                ->where('created_at', '>=', now()->subHours($hours))
                ->selectRaw('severity, count(*) as count')
                ->groupBy('severity')
                ->orderByDesc('count')
                ->pluck('count', 'severity')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function eventCountByModule(int $hours = 24): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                return [];
            }

            return DB::table('telemetry_events')
                ->where('created_at', '>=', now()->subHours($hours))
                ->selectRaw('module, count(*) as count')
                ->groupBy('module')
                ->orderByDesc('count')
                ->pluck('count', 'module')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function recentErrors(int $limit = 20): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                return [];
            }

            return DB::table('telemetry_events')
                ->whereIn('severity', ['error', 'critical'])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn($r) => [
                    'event_uuid' => $r->event_uuid,
                    'module'     => $r->module,
                    'event'      => $r->event,
                    'severity'   => $r->severity,
                    'tenant_id'  => $r->tenant_id,
                    'timestamp'  => $r->timestamp,
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
