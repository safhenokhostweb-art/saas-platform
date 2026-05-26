<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance;

use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\CircuitBreakerManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\IncidentResolutionTracker;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics\RuntimeStabilityIndexCalculator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Policy\PolicyConditionEvaluator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Policy\GovernanceRuleCompiler;
use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\RecoveryStrategyManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RuntimeThrottleManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation\TenantDegradationManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GovernanceRuntimeValidationService
{
    public function __construct(
        private GovernanceContext                $context,
        private GovernancePolicyRegistry         $registry,
        private RuntimeStateCoordinator          $stateCoordinator,
        private CircuitBreakerManager            $circuitManager,
        private IncidentResolutionTracker        $incidentTracker,
        private RuntimeStabilityIndexCalculator  $stabilityCalc,
        private PolicyConditionEvaluator         $conditionEvaluator,
        private GovernanceRuleCompiler           $ruleCompiler,
        private RecoveryStrategyManager          $recoveryManager,
        private RuntimeThrottleManager           $throttleManager,
        private TenantDegradationManager         $degradationManager
    ) {}

    public function validate(): array
    {
        $checks  = [];
        $passed  = 0;
        $failed  = 0;
        $warnings = 0;

        $dimensions = [
            'state_machine'    => $this->validateStateMachine(),
            'policy_registry'  => $this->validatePolicyRegistry(),
            'circuit_breakers' => $this->validateCircuitBreakers(),
            'recovery_engine'  => $this->validateRecoveryEngine(),
            'telemetry_bridge' => $this->validateTelemetryBridge(),
            'cache_backend'    => $this->validateCacheBackend(),
            'db_tables'        => $this->validateDbTables(),
            'tenant_isolation' => $this->validateTenantIsolation(),
            'policy_evaluator' => $this->validatePolicyEvaluator(),
            'stability_index'  => $this->validateStabilityIndex(),
        ];

        foreach ($dimensions as $name => $result) {
            $checks[$name] = $result;
            if ($result['status'] === 'pass') {
                $passed++;
            } elseif ($result['status'] === 'warn') {
                $warnings++;
            } else {
                $failed++;
            }
        }

        return [
            'valid'        => $failed === 0,
            'passed'       => $passed,
            'warnings'     => $warnings,
            'failed'       => $failed,
            'total'        => count($checks),
            'checks'       => $checks,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    private function validateStateMachine(): array
    {
        try {
            $state = $this->context->getState();
            $valid = in_array($state, [
                GovernanceContext::STATE_HEALTHY,
                GovernanceContext::STATE_DEGRADED,
                GovernanceContext::STATE_CRITICAL,
                GovernanceContext::STATE_EMERGENCY,
            ], true);

            return $valid
                ? ['status' => 'pass', 'detail' => 'Current state: ' . $state]
                : ['status' => 'fail', 'detail' => 'Invalid state: ' . $state];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validatePolicyRegistry(): array
    {
        try {
            $policies = $this->registry->all();
            $enabled  = $this->registry->enabled();

            if (empty($policies)) {
                return ['status' => 'warn', 'detail' => 'No policies registered — run bootstrap()'];
            }

            return ['status' => 'pass', 'detail' => count($policies) . ' policies, ' . count($enabled) . ' enabled'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateCircuitBreakers(): array
    {
        try {
            $circuit = $this->circuitManager->circuit('cache_backend');
            $state   = $circuit->state();

            return ['status' => 'pass', 'detail' => 'Circuit manager operational; test circuit state: ' . $state];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateRecoveryEngine(): array
    {
        try {
            $idempotent = ['queue_restart', 'cache_rebuild', 'health_check_retry'];
            foreach ($idempotent as $strategy) {
                if (!$this->recoveryManager->isIdempotentSafe($strategy)) {
                    return ['status' => 'fail', 'detail' => $strategy . ' not marked idempotent'];
                }
            }

            return ['status' => 'pass', 'detail' => 'Idempotency checks passed for ' . count($idempotent) . ' strategies'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateTelemetryBridge(): array
    {
        try {
            $bound = app()->bound(\SaaSPlatform\CoreUtils\Telemetry\TelemetryManager::class);
            return $bound
                ? ['status' => 'pass', 'detail' => 'TelemetryManager bound in container']
                : ['status' => 'fail', 'detail' => 'TelemetryManager not bound'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateCacheBackend(): array
    {
        try {
            $key = 'governance.validation.ping.' . time();
            Cache::put($key, true, 10);
            $ok = Cache::get($key) === true;
            Cache::forget($key);

            return $ok
                ? ['status' => 'pass', 'detail' => 'Cache read/write operational']
                : ['status' => 'fail', 'detail' => 'Cache write-read mismatch'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateDbTables(): array
    {
        $tables   = ['telemetry_events', 'governance_incidents', 'recovery_logs'];
        $missing  = [];

        foreach ($tables as $table) {
            try {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    $missing[] = $table;
                }
            } catch (\Throwable) {
                $missing[] = $table . '(error)';
            }
        }

        if (empty($missing)) {
            return ['status' => 'pass', 'detail' => 'All governance tables present'];
        }

        return ['status' => 'warn', 'detail' => 'Missing tables: ' . implode(', ', $missing) . ' — run migrations'];
    }

    private function validateTenantIsolation(): array
    {
        try {
            $level = $this->context->tenantDegradationLevel(0);
            $validRange = $level >= 0 && $level <= 5;

            return $validRange
                ? ['status' => 'pass', 'detail' => 'Tenant degradation level returns valid range']
                : ['status' => 'fail', 'detail' => 'Tenant degradation level out of range: ' . $level];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validatePolicyEvaluator(): array
    {
        try {
            $result = $this->conditionEvaluator->evaluate([
                'metric'    => 'test.value',
                'operator'  => '>',
                'threshold' => 5,
            ], ['test' => ['value' => 10]]);

            return $result
                ? ['status' => 'pass', 'detail' => 'PolicyConditionEvaluator working correctly']
                : ['status' => 'fail', 'detail' => 'PolicyConditionEvaluator returned false for 10 > 5'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateStabilityIndex(): array
    {
        try {
            $result = $this->stabilityCalc->calculate();
            $index  = $result['index'] ?? -1;

            if ($index < 0 || $index > 100) {
                return ['status' => 'fail', 'detail' => 'Stability index out of range: ' . $index];
            }

            $status = $index >= 50 ? 'pass' : 'warn';
            return ['status' => $status, 'detail' => 'Stability index: ' . $index . ' (' . ($result['grade'] ?? '?') . ')'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }
}
