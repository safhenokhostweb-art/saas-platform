<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Incident;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RuntimeIncidentTimeline
{
    private const TIMELINE_TTL    = 86400; // 24 hours
    private const MAX_EVENTS      = 200;
    private const CACHE_PREFIX    = 'governance.incident.timeline.';

    public function open(string $incidentId, string $type, string $severity, array $context = []): void
    {
        $this->appendEvent($incidentId, 'incident_opened', [
            'type'     => $type,
            'severity' => $severity,
            'context'  => $context,
        ]);
    }

    public function record(string $incidentId, string $event, array $data = []): void
    {
        $this->appendEvent($incidentId, $event, $data);
    }

    public function mitigationAttempted(string $incidentId, string $strategy, string $status, array $result = []): void
    {
        $this->appendEvent($incidentId, 'mitigation_attempted', [
            'strategy' => $strategy,
            'status'   => $status,
            'result'   => $result,
        ]);
    }

    public function stateChanged(string $incidentId, string $from, string $to, string $reason): void
    {
        $this->appendEvent($incidentId, 'state_changed', [
            'from'   => $from,
            'to'     => $to,
            'reason' => $reason,
        ]);
    }

    public function close(string $incidentId, string $resolution, array $summary = []): void
    {
        $this->appendEvent($incidentId, 'incident_closed', [
            'resolution' => $resolution,
            'summary'    => $summary,
        ]);
    }

    public function get(string $incidentId): array
    {
        return Cache::get(self::CACHE_PREFIX . $incidentId, []);
    }

    public function duration(string $incidentId): ?int
    {
        $events = $this->get($incidentId);
        if (empty($events)) {
            return null;
        }

        $opened = collect($events)->firstWhere('event', 'incident_opened');
        $closed = collect($events)->lastWhere('event', 'incident_closed');

        if (!$opened) {
            return null;
        }

        $start = strtotime($opened['timestamp']);
        $end   = $closed ? strtotime($closed['timestamp']) : time();

        return $end - $start;
    }

    public function mitigationAttempts(string $incidentId): array
    {
        return collect($this->get($incidentId))
            ->filter(fn($e) => $e['event'] === 'mitigation_attempted')
            ->values()
            ->toArray();
    }

    private function appendEvent(string $incidentId, string $event, array $data): void
    {
        $key    = self::CACHE_PREFIX . $incidentId;
        $events = Cache::get($key, []);

        $events[] = [
            'id'        => (string) Str::uuid(),
            'event'     => $event,
            'data'      => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }

        Cache::put($key, $events, self::TIMELINE_TTL);
    }
}
