<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Incident;

use Illuminate\Support\Facades\DB;

class TraceReplayService
{
    public function replay(string $traceId): array
    {
        $events = $this->eventsForTrace($traceId);

        if (empty($events)) {
            return [
                'trace_id' => $traceId,
                'found'    => false,
                'message'  => 'No events found for trace_id',
            ];
        }

        return [
            'trace_id'    => $traceId,
            'found'       => true,
            'event_count' => count($events),
            'first_at'    => $events[0]['timestamp'] ?? null,
            'last_at'     => $events[count($events) - 1]['timestamp'] ?? null,
            'modules'     => array_values(array_unique(array_column($events, 'module'))),
            'severities'  => array_count_values(array_column($events, 'severity')),
            'tenant_id'   => $events[0]['tenant_id'] ?? null,
            'events'      => array_map(fn($e) => [
                'event_uuid' => $e['event_uuid'],
                'module'     => $e['module'],
                'event'      => $e['event'],
                'severity'   => $e['severity'],
                'timestamp'  => $e['timestamp'],
                // data excluded — may contain runtime context with sensitive paths
            ], $events),
        ];
    }

    public function anomalyWindow(string $traceId, int $windowSeconds = 60): array
    {
        $events = $this->eventsForTrace($traceId);

        if (empty($events)) {
            return ['trace_id' => $traceId, 'anomalies' => []];
        }

        $anomalies = array_filter($events, fn($e) => in_array($e['severity'], ['error', 'critical']));

        return [
            'trace_id'      => $traceId,
            'window_seconds' => $windowSeconds,
            'anomalies'     => array_values($anomalies),
            'anomaly_count' => count($anomalies),
        ];
    }

    private function eventsForTrace(string $traceId): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('telemetry_events')) {
                return [];
            }

            return DB::table('telemetry_events')
                ->where('trace_id', $traceId)
                ->orderBy('timestamp')
                ->limit(1000)
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
