<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Incident;

use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;
use SaaSPlatform\CoreUtils\RuntimeGovernance\RuntimeStateCoordinator;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use Illuminate\Support\Str;

class IncidentResponseCoordinator
{
    public function __construct(
        private IncidentSeverityClassifier  $classifier,
        private RuntimeIncidentTimeline     $timeline,
        private AutomatedMitigationExecutor $executor,
        private IncidentResolutionTracker   $tracker,
        private RuntimeStateCoordinator     $stateCoordinator,
        private TelemetryManager            $telemetry
    ) {}

    public function respond(string $incidentType, array $context = []): array
    {
        $incidentId = (string) Str::uuid();
        $severity   = $this->classifier->classify($incidentType, $context);

        $this->tracker->open($incidentId, $incidentType, $severity, $context);
        $this->timeline->open($incidentId, $incidentType, $severity, $context);

        $this->telemetry->warning('governance', 'governance.incident.opened', [
            'incident_id' => $incidentId,
            'type'        => $incidentType,
            'severity'    => $severity,
        ]);

        $targetState = $this->classifier->platformState($severity);
        $this->transitionPlatformState($incidentId, $targetState, $incidentType);

        if (!$this->classifier->isAutoResolvable($severity)) {
            return $this->escalate($incidentId, $severity, $incidentType, $context);
        }

        return $this->autoMitigate($incidentId, $severity, $incidentType, $context);
    }

    public function resolve(string $incidentId, string $resolution, bool $autoResolved = false): void
    {
        $this->tracker->resolve($incidentId, $resolution, $autoResolved);
        $this->timeline->close($incidentId, $resolution);

        $this->telemetry->info('governance', 'governance.incident.resolved', [
            'incident_id'   => $incidentId,
            'resolution'    => $resolution,
            'auto_resolved' => $autoResolved,
        ]);
    }

    public function respondToAnomalies(array $anomalies): array
    {
        if (empty($anomalies)) {
            return [];
        }

        $responses = [];
        foreach ($anomalies as $anomaly) {
            $type = $anomaly['type'] ?? 'UNKNOWN';
            $responses[] = $this->respond($type, $anomaly);
        }

        return $responses;
    }

    public function activeIncidentCount(): int
    {
        return $this->tracker->activeCount();
    }

    public function hasCriticalActiveIncidents(): bool
    {
        return $this->tracker->hasCriticalActive();
    }

    private function autoMitigate(string $incidentId, string $severity, string $type, array $context): array
    {
        $this->tracker->transition($incidentId, IncidentResolutionTracker::STATUS_MITIGATING);
        $this->timeline->record($incidentId, 'auto_mitigation_started', ['severity' => $severity]);

        $mitigation = $this->executor->mitigate($incidentId, $severity, $context);

        foreach ($mitigation['strategies'] as $strategy => $result) {
            $this->timeline->mitigationAttempted($incidentId, $strategy, $result['status'], $result);
        }

        if ($mitigation['mitigated']) {
            $this->resolve($incidentId, 'auto_mitigated:' . implode(',', array_keys($mitigation['strategies'])), true);
        } else {
            $this->escalate($incidentId, $severity, $type, $context);
        }

        return [
            'incident_id' => $incidentId,
            'type'        => $type,
            'severity'    => $severity,
            'auto_handled' => $mitigation['mitigated'],
            'mitigation'  => $mitigation,
        ];
    }

    private function escalate(string $incidentId, string $severity, string $type, array $context): array
    {
        $reason = 'severity_requires_operator:' . $severity;
        $this->tracker->escalate($incidentId, $reason);
        $this->timeline->record($incidentId, 'escalated_to_operator', [
            'reason'   => $reason,
            'severity' => $severity,
        ]);

        $this->telemetry->critical('governance', 'governance.incident.escalated', [
            'incident_id' => $incidentId,
            'type'        => $type,
            'severity'    => $severity,
            'context'     => $context,
        ]);

        return [
            'incident_id'    => $incidentId,
            'type'           => $type,
            'severity'       => $severity,
            'auto_handled'   => false,
            'escalated'      => true,
            'escalation_reason' => $reason,
        ];
    }

    private function transitionPlatformState(string $incidentId, string $targetState, string $reason): void
    {
        try {
            $current = $this->stateCoordinator->currentState();

            if ($current === $targetState) {
                return;
            }

            $currentScore = $this->stateScore($current);
            $targetScore  = $this->stateScore($targetState);

            if ($targetScore > $currentScore) {
                $this->stateCoordinator->transitionTo($targetState, $reason, 'incident:' . $incidentId);
                $this->timeline->stateChanged($incidentId, $current, $targetState, $reason);
            }
        } catch (\Throwable) {
            // State transition is best-effort
        }
    }

    private function stateScore(string $state): int
    {
        return match ($state) {
            GovernanceContext::STATE_HEALTHY   => 0,
            GovernanceContext::STATE_DEGRADED  => 1,
            GovernanceContext::STATE_CRITICAL  => 2,
            GovernanceContext::STATE_EMERGENCY => 3,
            default                            => 0,
        };
    }
}
