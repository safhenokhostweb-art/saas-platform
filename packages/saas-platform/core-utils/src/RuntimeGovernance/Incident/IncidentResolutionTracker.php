<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Incident;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IncidentResolutionTracker
{
    private const ACTIVE_INCIDENTS_KEY = 'governance.incidents.active';
    private const INCIDENT_PREFIX      = 'governance.incident.record.';
    private const RECORD_TTL           = 86400;

    public const STATUS_OPEN        = 'open';
    public const STATUS_MITIGATING  = 'mitigating';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_ESCALATED   = 'escalated';

    public function open(string $incidentId, string $type, string $severity, array $context = []): void
    {
        $record = [
            'incident_id' => $incidentId,
            'type'        => $type,
            'severity'    => $severity,
            'status'      => self::STATUS_OPEN,
            'context'     => $context,
            'opened_at'   => now()->toIso8601String(),
            'resolved_at' => null,
            'resolution'  => null,
        ];

        Cache::put(self::INCIDENT_PREFIX . $incidentId, $record, self::RECORD_TTL);

        $active = Cache::get(self::ACTIVE_INCIDENTS_KEY, []);
        $active[$incidentId] = [
            'type'      => $type,
            'severity'  => $severity,
            'opened_at' => $record['opened_at'],
        ];
        Cache::put(self::ACTIVE_INCIDENTS_KEY, $active, self::RECORD_TTL);

        $this->persistToDb($record);
    }

    public function transition(string $incidentId, string $status): void
    {
        $record = Cache::get(self::INCIDENT_PREFIX . $incidentId);
        if (!$record) {
            return;
        }

        $record['status'] = $status;
        Cache::put(self::INCIDENT_PREFIX . $incidentId, $record, self::RECORD_TTL);
    }

    public function resolve(string $incidentId, string $resolution, bool $autoResolved): void
    {
        $record = Cache::get(self::INCIDENT_PREFIX . $incidentId);
        if (!$record) {
            return;
        }

        $record['status']       = self::STATUS_RESOLVED;
        $record['resolved_at']  = now()->toIso8601String();
        $record['resolution']   = $resolution;
        $record['auto_resolved'] = $autoResolved;

        Cache::put(self::INCIDENT_PREFIX . $incidentId, $record, self::RECORD_TTL);

        $active = Cache::get(self::ACTIVE_INCIDENTS_KEY, []);
        unset($active[$incidentId]);
        Cache::put(self::ACTIVE_INCIDENTS_KEY, $active, self::RECORD_TTL);

        $this->persistToDb($record);
    }

    public function escalate(string $incidentId, string $reason): void
    {
        $this->transition($incidentId, self::STATUS_ESCALATED);

        $record = Cache::get(self::INCIDENT_PREFIX . $incidentId, []);
        $record['escalation_reason'] = $reason;
        $record['escalated_at']      = now()->toIso8601String();
        Cache::put(self::INCIDENT_PREFIX . $incidentId, $record, self::RECORD_TTL);
    }

    public function get(string $incidentId): ?array
    {
        return Cache::get(self::INCIDENT_PREFIX . $incidentId);
    }

    public function activeIncidents(): array
    {
        return Cache::get(self::ACTIVE_INCIDENTS_KEY, []);
    }

    public function isActive(string $incidentId): bool
    {
        $active = $this->activeIncidents();
        return isset($active[$incidentId]);
    }

    public function activeCount(): int
    {
        return count($this->activeIncidents());
    }

    public function hasCriticalActive(): bool
    {
        $active = $this->activeIncidents();
        foreach ($active as $incident) {
            if (in_array($incident['severity'], [
                IncidentSeverityClassifier::SEV_CRITICAL,
                IncidentSeverityClassifier::SEV_PLATFORM_EMERGENCY,
            ], true)) {
                return true;
            }
        }
        return false;
    }

    private function persistToDb(array $record): void
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('governance_incidents')) {
                return;
            }

            DB::table('governance_incidents')->updateOrInsert(
                ['incident_id' => $record['incident_id']],
                [
                    'type'        => $record['type'],
                    'severity'    => $record['severity'],
                    'status'      => $record['status'],
                    'context'     => json_encode($record['context'] ?? []),
                    'resolution'  => $record['resolution'] ?? null,
                    'opened_at'   => $record['opened_at'],
                    'resolved_at' => $record['resolved_at'] ?? null,
                    'updated_at'  => now(),
                ]
            );
        } catch (\Throwable) {
            // DB write is best-effort; cache is authoritative
        }
    }
}
