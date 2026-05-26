<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Policy;

use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\RecoveryStrategyManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation\TenantDegradationManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\RuntimeStateCoordinator;

class RuntimePolicyExecutor
{
    public function __construct(
        private RecoveryStrategyManager  $strategyManager,
        private TenantDegradationManager $degradationManager,
        private RuntimeStateCoordinator  $stateCoordinator,
        private TelemetryManager         $telemetry
    ) {}

    public function execute(array $compiledPolicy, array $signals = []): array
    {
        $executionId = (string) Str::uuid();
        $results     = [];

        foreach ($compiledPolicy['actions'] as $action) {
            $results[] = $this->applyAction($action, $executionId, $compiledPolicy['name'], $signals);
        }

        $this->telemetry->info('governance', 'governance.policy.executed', [
            'policy'       => $compiledPolicy['name'],
            'version'      => $compiledPolicy['version'],
            'execution_id' => $executionId,
            'actions'      => array_column($compiledPolicy['actions'], 'type'),
        ]);

        return [
            'execution_id' => $executionId,
            'policy'       => $compiledPolicy['name'],
            'results'      => $results,
        ];
    }

    private function applyAction(array $action, string $executionId, string $policyName, array $signals): array
    {
        $type     = $action['type'];
        $tenantId = $signals['tenant_id'] ?? null;

        try {
            $outcome = match ($type) {
                'state_transition' => $this->handleStateTransition($action, $policyName),
                'degradation'      => $this->handleDegradation($action, $tenantId, $policyName),
                'recovery'         => $this->handleRecovery($action, $signals),
                'deployment_freeze' => $this->handleDeploymentFreeze($policyName),
                'queue_throttle'   => $this->handleQueueThrottle($action),
                'cache_clear'      => $this->handleCacheClear($action),
                'telemetry'        => $this->handleTelemetry($action, $policyName),
                default            => ['status' => 'unknown_action_type', 'type' => $type],
            };

            return array_merge(['type' => $type, 'status' => 'applied'], $outcome);
        } catch (\Throwable $e) {
            return ['type' => $type, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function handleStateTransition(array $action, string $policyName): array
    {
        $state   = $action['state'] ?? 'degraded';
        $success = $this->stateCoordinator->transitionTo($state, 'policy:' . $policyName, 'executor');

        return ['transitioned' => $success, 'state' => $state];
    }

    private function handleDegradation(array $action, ?int $tenantId, string $policyName): array
    {
        $level = (int) ($action['level'] ?? 1);

        if ($tenantId !== null) {
            $this->degradationManager->degrade($tenantId, $level, 'policy:' . $policyName);
            return ['tenant_id' => $tenantId, 'level' => $level];
        }

        return ['status' => 'skipped', 'reason' => 'no_tenant_context'];
    }

    private function handleRecovery(array $action, array $signals): array
    {
        $strategy = $action['strategy'] ?? 'retry';
        return $this->strategyManager->execute($strategy, $signals);
    }

    private function handleDeploymentFreeze(string $policyName): array
    {
        \Illuminate\Support\Facades\Cache::put('governance.deployment.frozen', [
            'policy'    => $policyName,
            'frozen_at' => now()->toIso8601String(),
        ], 3600);

        return ['frozen' => true];
    }

    private function handleQueueThrottle(array $action): array
    {
        $factor = (float) ($action['factor'] ?? 0.5);
        \Illuminate\Support\Facades\Cache::put('governance.queue.throttle_factor', $factor, 600);
        return ['factor' => $factor];
    }

    private function handleCacheClear(array $action): array
    {
        $scope = $action['scope'] ?? 'safe';
        return ['cleared' => true, 'scope' => $scope];
    }

    private function handleTelemetry(array $action, string $policyName): array
    {
        $event = $action['event'] ?? 'governance.policy.action';
        $this->telemetry->info('governance', $event, ['policy' => $policyName]);
        return ['emitted' => true, 'event' => $event];
    }
}
