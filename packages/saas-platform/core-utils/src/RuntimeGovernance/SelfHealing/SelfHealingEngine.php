<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine;
use SaaSPlatform\CoreUtils\RuntimeGovernance\RuntimeStateCoordinator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;

class SelfHealingEngine
{
    private const ANOMALY_TO_FAILURE = [
        'QUEUE_FAILED_JOBS_HIGH'        => 'queue.failed_count_high',
        'QUEUE_BACKLOG_HIGH'            => 'queue.stall_detected',
        'TENANT_ISOLATION_VIOLATIONS'   => 'security.boundary_violation',
        'DEPLOYMENT_FAILURES_RECENT'    => 'deployment.failure',
        'DEPLOYMENT_STALE_LOCK'         => 'deployment.stale_lock',
        'SECURITY_AUTH_FAILURE_BURST'   => 'security.auth_burst',
        'RUNTIME_MEMORY_HIGH'           => 'runtime.memory_pressure',
    ];

    public function __construct(
        private RuntimeRecoveryCoordinator  $recoveryCoordinator,
        private FailureClassificationEngine $classifier,
        private RuntimeAnomalyEngine        $anomalyEngine,
        private RuntimeStateCoordinator     $stateCoordinator,
        private TelemetryManager            $telemetry
    ) {}

    public function healFromAnomalies(): array
    {
        $anomalies = $this->anomalyEngine->scan();
        $results   = [];

        foreach ($anomalies as $anomaly) {
            $code        = $anomaly['code'] ?? '';
            $failureType = self::ANOMALY_TO_FAILURE[$code] ?? 'unknown.anomaly';

            $result    = $this->recoveryCoordinator->coordinateRecovery($failureType, [
                'anomaly_code' => $code,
                'severity'     => $anomaly['severity'] ?? 'warning',
                'context'      => $anomaly['context'] ?? [],
            ]);

            $results[] = $result;
        }

        // If no anomalies and platform is degraded, attempt state recovery
        if (empty($anomalies) && !$this->stateCoordinator->isHealthy()) {
            $results[] = $this->recoveryCoordinator->attemptPlatformRecovery();
        }

        return [
            'anomalies_detected' => count($anomalies),
            'recoveries'         => $results,
            'platform_state'     => $this->stateCoordinator->currentState(),
            'healed_at'          => now()->toIso8601String(),
        ];
    }

    public function healSpecific(string $failureType, array $context = []): array
    {
        return $this->recoveryCoordinator->coordinateRecovery($failureType, $context);
    }

    public function canHeal(string $failureType, array $context = []): bool
    {
        $class = $this->classifier->classify($failureType, $context);
        return $this->classifier->isAutoRecoverable($class);
    }

    public function recoverabilityReport(): array
    {
        $failureTypes = array_values(self::ANOMALY_TO_FAILURE);

        return array_map(function ($type) {
            $class = $this->classifier->classify($type);
            return [
                'failure_type'   => $type,
                'class'          => $class,
                'auto_recoverable' => $this->classifier->isAutoRecoverable($class),
                'score'          => $this->classifier->recoverabilityScore($class),
                'strategies'     => $this->classifier->suggestedStrategies($class),
            ];
        }, $failureTypes);
    }
}
