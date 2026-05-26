<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Incident;

use Illuminate\Support\Facades\DB;

class RuntimeTimelineBuilder
{
    public function buildForTrace(string $traceId): array
    {
        $events = $this->loadTelemetryEvents(['trace_id' => $traceId]);
        $logs   = $this->loadDeploymentLogs($traceId);

        $timeline = array_merge($events, $logs);
        usort($timeline, fn($a, $b) => strcmp($a['at'], $b['at']));

        return [
            'trace_id'    => $traceId,
            'event_count' => count($timeline),
            'timeline'    => $timeline,
            'first_event' => $timeline[0]['at'] ?? null,
            'last_event'  => $timeline[count($timeline) - 1]['at'] ?? null,
        ];
    }

    public function buildForTenant(int $tenantId, string $from, string $to): array
    {
        // Strictly tenant-scoped — no cross-tenant data
        $events = $this->loadTelemetryEvents([
            'tenant_id' => $tenantId,
            'from'      => $from,
            'to'        => $to,
        ]);

        usort($events, fn($a, $b) => strcmp($a['at'], $b['at']));

        return [
            'tenant_id'   => $tenantId,
            'from'        => $from,
            'to'          => $to,
            'event_count' => count($events),
            'timeline'    => $events,
        ];
    }

    public function buildForDeployment(string $deploymentUuid): array
    {
        $deploymentEvents = [];

        try {
            if (DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                $deploymentEvents = DB::table('deployment_logs')
                    ->where('deployment_uuid', $deploymentUuid)
                    ->orderBy('logged_at')
                    ->get()
                    ->map(fn($r) => [
                        'type'    => 'deployment',
                        'phase'   => $r->phase,
                        'status'  => $r->status,
                        'message' => $r->message,
                        'at'      => $r->logged_at,
                    ])
                    ->toArray();
            }
        } catch (\Throwable) {
            // Non-critical
        }

        $telemetryEvents = $this->loadTelemetryEvents(['module' => 'deployment']);

        $timeline = array_merge($deploymentEvents, $telemetryEvents);
        usort($timeline, fn($a, $b) => strcmp($a['at'], $b['at']));

        return [
            'deployment_uuid' => $deploymentUuid,
            'event_count'     => count($timeline),
            'timeline'        => $timeline,
        ];
    }

    private function loadTelemetryEvents(array $filters): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                return [];
            }

            $q = DB::table('telemetry_events');
            if (!empty($filters['trace_id']))  { $q->where('trace_id', $filters['trace_id']); }
            if (!empty($filters['tenant_id'])) { $q->where('tenant_id', $filters['tenant_id']); }
            if (!empty($filters['module']))    { $q->where('module', $filters['module']); }
            if (!empty($filters['from']))      { $q->where('timestamp', '>=', $filters['from']); }
            if (!empty($filters['to']))        { $q->where('timestamp', '<=', $filters['to']); }

            return $q->orderBy('timestamp')
                ->limit(500)
                ->get()
                ->map(fn($r) => [
                    'type'     => 'telemetry',
                    'event'    => $r->event,
                    'module'   => $r->module,
                    'severity' => $r->severity,
                    'at'       => $r->timestamp,
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadDeploymentLogs(string $traceId): array
    {
        // Deployment logs don't have trace_id; return empty for trace-based queries
        return [];
    }
}
