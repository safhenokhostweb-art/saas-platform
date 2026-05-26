<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class GovernanceExecutionPipeline
{
    private const EXECUTION_KEY_PREFIX = 'governance.execution.';

    public function __construct(
        private RuntimeStateCoordinator $stateCoordinator,
        private TelemetryManager        $telemetry
    ) {}

    public function execute(array $decisions): array
    {
        $executionId = (string) Str::uuid();
        $results     = [];

        foreach ($decisions as $decision) {
            $policyResults = $this->executeDecision($executionId, $decision);
            $results[]     = [
                'execution_id' => $executionId,
                'policy'       => $decision['policy'],
                'results'      => $policyResults,
            ];
        }

        $this->storeExecution($executionId, $decisions, $results);

        $this->telemetry->info('governance', 'governance.pipeline.executed', [
            'execution_id'   => $executionId,
            'decision_count' => count($decisions),
            'action_count'   => array_sum(array_map(fn($d) => count($d['actions'] ?? []), $decisions)),
        ]);

        return ['execution_id' => $executionId, 'results' => $results];
    }

    public function rollback(string $executionId): array
    {
        $execution = Cache::get(self::EXECUTION_KEY_PREFIX . $executionId);

        if (!$execution) {
            return ['success' => false, 'reason' => 'execution_not_found'];
        }

        $this->telemetry->warning('governance', 'governance.pipeline.rollback', [
            'execution_id' => $executionId,
        ]);

        Cache::forget(self::EXECUTION_KEY_PREFIX . $executionId);

        return ['success' => true, 'execution_id' => $executionId];
    }

    public function getExecution(string $executionId): ?array
    {
        return Cache::get(self::EXECUTION_KEY_PREFIX . $executionId);
    }

    private function executeDecision(string $executionId, array $decision): array
    {
        $results = [];

        foreach ($decision['actions'] as $action) {
            $results[] = $this->applyAction($action, $executionId, $decision['policy']);
        }

        return $results;
    }

    private function applyAction(array $action, string $executionId, string $policyName): array
    {
        $type = $action['type'] ?? 'unknown';

        try {
            $outcome = match ($type) {
                'state_transition' => $this->handleStateTransition($action, $policyName),
                'telemetry'        => $this->handleTelemetryAction($action),
                'deployment_freeze' => $this->handleDeploymentFreeze($policyName),
                'cache_clear'      => $this->handleCacheClear($action),
                'degradation'      => ['deferred' => true, 'type' => $type, 'action' => $action],
                'queue_throttle'   => ['deferred' => true, 'type' => $type, 'action' => $action],
                'recovery'         => ['deferred' => true, 'type' => $type, 'action' => $action],
                default            => ['skipped' => true, 'type' => $type],
            };

            return array_merge(['type' => $type, 'status' => 'applied'], $outcome);
        } catch (\Throwable $e) {
            return ['type' => $type, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function handleStateTransition(array $action, string $policyName): array
    {
        $state   = $action['state'] ?? GovernanceContext::STATE_DEGRADED;
        $success = $this->stateCoordinator->transitionTo($state, 'policy:' . $policyName, 'pipeline');

        return ['transitioned' => $success, 'target_state' => $state];
    }

    private function handleTelemetryAction(array $action): array
    {
        $this->telemetry->info('governance', $action['event'] ?? 'governance.action', []);
        return ['emitted' => true];
    }

    private function handleDeploymentFreeze(string $policyName): array
    {
        // Signal deployment freeze via cache — checked by DeploymentOrchestrator
        Cache::put('governance.deployment.frozen', [
            'reason'    => 'policy:' . $policyName,
            'frozen_at' => now()->toIso8601String(),
        ], 3600);

        $this->telemetry->warning('governance', 'governance.deployment.frozen', [
            'policy' => $policyName,
        ]);

        return ['deployment_frozen' => true];
    }

    private function handleCacheClear(array $action): array
    {
        $scope = $action['scope'] ?? 'runtime';

        // Targeted cache clear — do not flush all cache
        if ($scope === 'runtime') {
            Cache::forget('governance.runtime.state');
        }

        return ['cache_cleared' => true, 'scope' => $scope];
    }

    private function storeExecution(string $executionId, array $decisions, array $results): void
    {
        Cache::put(self::EXECUTION_KEY_PREFIX . $executionId, [
            'execution_id' => $executionId,
            'decisions'    => $decisions,
            'results'      => $results,
            'executed_at'  => now()->toIso8601String(),
        ], 3600);
    }
}
