<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Incident;

use SaaSPlatform\CoreUtils\RuntimeGovernance\SelfHealing\RecoveryStrategyManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation\TenantProtectionCoordinator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\CircuitBreakerManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use Illuminate\Support\Facades\Cache;

class AutomatedMitigationExecutor
{
    private const SEVERITY_STRATEGIES = [
        IncidentSeverityClassifier::SEV_WARNING => [
            'health_check_retry',
            'telemetry',
        ],
        IncidentSeverityClassifier::SEV_DEGRADED => [
            'queue_restart',
            'cache_rebuild',
            'health_check_retry',
        ],
        IncidentSeverityClassifier::SEV_CRITICAL => [
            'deployment_freeze',
            'circuit_open',
            'queue_restart',
            'alert',
        ],
        IncidentSeverityClassifier::SEV_PLATFORM_EMERGENCY => [
            'deployment_freeze',
            'tenant_isolation',
            'circuit_open',
            'alert',
        ],
    ];

    public function __construct(
        private RecoveryStrategyManager     $recoveryManager,
        private TenantProtectionCoordinator $tenantProtection,
        private CircuitBreakerManager       $circuitManager,
        private TelemetryManager            $telemetry
    ) {}

    public function mitigate(string $incidentId, string $severity, array $context): array
    {
        $strategies = self::SEVERITY_STRATEGIES[$severity] ?? ['health_check_retry'];
        $results    = [];

        foreach ($strategies as $strategy) {
            $result = $this->executeStrategy($strategy, $incidentId, $context);
            $results[$strategy] = $result;

            if ($result['status'] === 'success') {
                break;
            }
        }

        return [
            'incident_id' => $incidentId,
            'severity'    => $severity,
            'strategies'  => $results,
            'mitigated'   => in_array('success', array_column($results, 'status'), true),
        ];
    }

    public function canAutoMitigate(string $severity): bool
    {
        return isset(self::SEVERITY_STRATEGIES[$severity])
            && $severity !== IncidentSeverityClassifier::SEV_INFO;
    }

    private function executeStrategy(string $strategy, string $incidentId, array $context): array
    {
        try {
            return match ($strategy) {
                'deployment_freeze' => $this->freezeDeployment($incidentId),
                'circuit_open'      => $this->openCircuit($context),
                'tenant_isolation'  => $this->isolateTenant($context),
                'queue_restart'     => $this->recoveryManager->execute('queue_restart', $context + ['incident_id' => $incidentId]),
                'cache_rebuild'     => $this->recoveryManager->execute('cache_rebuild', $context + ['incident_id' => $incidentId]),
                'health_check_retry' => $this->recoveryManager->execute('health_check_retry', $context + ['incident_id' => $incidentId]),
                'alert'             => $this->recoveryManager->execute('alert', $context + ['incident_id' => $incidentId]),
                'telemetry'         => $this->emitTelemetry($incidentId, $context),
                default             => ['status' => 'skipped', 'reason' => 'unknown_strategy'],
            };
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function freezeDeployment(string $incidentId): array
    {
        Cache::put('governance.deployment.frozen', [
            'frozen_at'   => now()->toIso8601String(),
            'reason'      => 'incident:' . $incidentId,
            'auto_frozen' => true,
        ], 3600);

        return ['status' => 'success', 'action' => 'deployment_frozen'];
    }

    private function openCircuit(array $context): array
    {
        $component = $context['component'] ?? 'unknown';

        if ($component !== 'unknown') {
            $this->circuitManager->forceOpen($component, 'incident_mitigation');
            return ['status' => 'success', 'circuit' => $component];
        }

        return ['status' => 'skipped', 'reason' => 'no_component_in_context'];
    }

    private function isolateTenant(array $context): array
    {
        $tenantId = $context['tenant_id'] ?? null;

        if (!$tenantId) {
            return ['status' => 'skipped', 'reason' => 'no_tenant_in_context'];
        }

        $this->tenantProtection->protect((int) $tenantId);
        return ['status' => 'success', 'tenant_id' => $tenantId];
    }

    private function emitTelemetry(string $incidentId, array $context): array
    {
        $this->telemetry->warning('governance', 'governance.incident.mitigation_telemetry', [
            'incident_id' => $incidentId,
            'context'     => $context,
        ]);

        return ['status' => 'success', 'action' => 'telemetry_emitted'];
    }
}
