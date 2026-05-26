<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Incident;

class IncidentReconstructionService
{
    public function __construct(
        private RuntimeTimelineBuilder $timeline,
        private TraceReplayService     $replay
    ) {}

    public function reconstruct(string $traceId): array
    {
        $replayData    = $this->replay->replay($traceId);
        $timelineData  = $this->timeline->buildForTrace($traceId);
        $anomalyWindow = $this->replay->anomalyWindow($traceId);

        $rootCause = $this->inferRootCause($replayData, $anomalyWindow);

        return [
            'trace_id'      => $traceId,
            'reconstructed' => $replayData['found'],
            'summary'       => [
                'event_count'   => $replayData['event_count'] ?? 0,
                'modules'       => $replayData['modules'] ?? [],
                'first_event'   => $replayData['first_at'] ?? null,
                'last_event'    => $replayData['last_at'] ?? null,
                'anomaly_count' => $anomalyWindow['anomaly_count'] ?? 0,
                'tenant_id'     => $replayData['tenant_id'] ?? null,
            ],
            'root_cause'    => $rootCause,
            'timeline'      => $timelineData['timeline'] ?? [],
            'next_steps'    => $this->buildNextSteps($rootCause),
            'reconstructed_at' => now()->toIso8601String(),
        ];
    }

    public function reconstructForDeployment(string $deploymentUuid): array
    {
        $timelineData = $this->timeline->buildForDeployment($deploymentUuid);

        $failures = array_filter(
            $timelineData['timeline'] ?? [],
            fn($e) => ($e['status'] ?? '') === 'failed'
        );

        return [
            'deployment_uuid' => $deploymentUuid,
            'timeline'        => $timelineData['timeline'],
            'failed_phases'   => array_values($failures),
            'failure_count'   => count($failures),
            'reconstructed_at' => now()->toIso8601String(),
        ];
    }

    private function inferRootCause(array $replay, array $anomaly): array
    {
        if (!$replay['found']) {
            return ['type' => 'not_found', 'confidence' => 'none'];
        }

        $severities = $replay['severities'] ?? [];

        if (($severities['critical'] ?? 0) > 0) {
            return [
                'type'       => 'critical_event',
                'confidence' => 'high',
                'evidence'   => 'Critical severity events found in trace',
            ];
        }

        if (($severities['error'] ?? 0) > 0) {
            return [
                'type'       => 'error_event',
                'confidence' => 'medium',
                'evidence'   => ($severities['error'] ?? 0) . ' error event(s) in trace',
            ];
        }

        return ['type' => 'no_anomaly_detected', 'confidence' => 'low'];
    }

    private function buildNextSteps(array $rootCause): array
    {
        return match ($rootCause['type']) {
            'critical_event' => [
                'Review critical events in telemetry_events for this trace_id',
                'Run: php artisan platform:health',
                'Check deployment status if incident coincides with a deploy',
            ],
            'error_event' => [
                'Review error events for this trace_id',
                'Check queue for failed jobs in the same time window',
            ],
            default => [
                'No anomaly detected for this trace — may be a client-side issue',
                'Review application logs for the request timestamp',
            ],
        };
    }
}
