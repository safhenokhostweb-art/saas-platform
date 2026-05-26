<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing;

use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\RuntimeStateCoordinator;

class RuntimeRecoveryCoordinator
{
    public function __construct(
        private FailureClassificationEngine $classifier,
        private RecoveryStrategyManager     $strategyManager,
        private RecoveryExecutionLog        $executionLog,
        private RuntimeStateCoordinator     $stateCoordinator,
        private TelemetryManager            $telemetry
    ) {}

    public function coordinateRecovery(string $failureType, array $context = []): array
    {
        $recoverySessionId = (string) Str::uuid();
        $class             = $this->classifier->classify($failureType, $context);
        $recoverable       = $this->classifier->isAutoRecoverable($class);

        $this->telemetry->info('governance', 'governance.recovery.initiated', [
            'recovery_session_id' => $recoverySessionId,
            'failure_type'        => $failureType,
            'class'               => $class,
            'recoverable'         => $recoverable,
        ]);

        if (!$recoverable) {
            $this->strategyManager->execute('alert', array_merge($context, [
                'failure_type' => $failureType,
                'class'        => $class,
                'reason'       => 'not_auto_recoverable',
            ]));

            return [
                'recovery_session_id' => $recoverySessionId,
                'status'              => 'deferred_to_operator',
                'failure_class'       => $class,
                'recoverability'      => $this->classifier->recoverabilityScore($class),
            ];
        }

        $strategies = $this->classifier->suggestedStrategies($class);
        $results    = [];

        foreach ($strategies as $strategy) {
            $result = $this->strategyManager->execute($strategy, array_merge($context, [
                'failure_type'        => $failureType,
                'recovery_session_id' => $recoverySessionId,
            ]));

            $results[] = $result;

            if (($result['status'] ?? '') === 'success') {
                break; // First successful strategy is sufficient
            }
        }

        $overallSuccess = collect($results)->contains('status', 'success');

        if ($overallSuccess) {
            $this->telemetry->info('governance', 'governance.recovery.completed', [
                'recovery_session_id' => $recoverySessionId,
                'failure_type'        => $failureType,
                'strategies_tried'    => count($results),
            ]);
        } else {
            $this->telemetry->error('governance', 'governance.recovery.exhausted', [
                'recovery_session_id' => $recoverySessionId,
                'failure_type'        => $failureType,
            ]);
        }

        return [
            'recovery_session_id' => $recoverySessionId,
            'status'              => $overallSuccess ? 'recovered' : 'unrecovered',
            'failure_type'        => $failureType,
            'failure_class'       => $class,
            'strategies_applied'  => $results,
        ];
    }

    public function attemptPlatformRecovery(): array
    {
        $results = [];

        // Try to restore platform to healthy state
        if ($this->stateCoordinator->isCritical()) {
            $results[] = $this->coordinateRecovery('platform.critical_state', [
                'current_state' => $this->stateCoordinator->currentState(),
            ]);
        }

        // Check if we can recover to healthy
        $allSucceeded = !collect($results)->contains('status', 'unrecovered');
        if ($allSucceeded && !empty($results)) {
            $this->stateCoordinator->recover('coordinated_recovery_completed');
        }

        return [
            'recovery_attempted' => !empty($results),
            'results'            => $results,
            'current_state'      => $this->stateCoordinator->currentState(),
        ];
    }
}
