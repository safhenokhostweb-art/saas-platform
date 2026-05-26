<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CascadeProtection;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\CircuitBreakerManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\ModuleCircuitRegistry;

class FailureContainmentCoordinator
{
    public function __construct(
        private RuntimeBlastRadiusAnalyzer $blastAnalyzer,
        private CircuitBreakerManager      $circuitManager,
        private ModuleCircuitRegistry      $moduleCircuits,
        private TelemetryManager           $telemetry
    ) {}

    public function contain(string $failingComponent): array
    {
        $analysis = $this->blastAnalyzer->analyze($failingComponent);
        $actions  = [];

        // Open circuit for the failing component if it's a module
        if ($this->moduleCircuits->moduleIsDisabled($failingComponent) === false) {
            $this->moduleCircuits->openForModule($failingComponent);
            $actions[] = ['action' => 'circuit_opened', 'component' => $failingComponent];
        }

        // For critical blast radius, open circuits for leaf dependents too
        if ($analysis['risk_level'] === 'critical') {
            foreach ($analysis['containment_order'] as $component) {
                $this->circuitManager->forceOpen($component);
                $actions[] = ['action' => 'downstream_isolated', 'component' => $component];
            }
        }

        $this->telemetry->emit(
            'governance',
            $analysis['risk_level'] === 'critical'
                ? TelemetryManager::SEVERITY_CRITICAL
                : TelemetryManager::SEVERITY_WARNING,
            'governance.cascade.contained',
            [
                'failing_component'  => $failingComponent,
                'risk_level'         => $analysis['risk_level'],
                'blast_radius_score' => $analysis['blast_radius_score'],
                'actions'            => $actions,
            ]
        );

        return [
            'contained'       => true,
            'failing'         => $failingComponent,
            'blast_analysis'  => $analysis,
            'actions_taken'   => $actions,
        ];
    }

    public function release(string $component): array
    {
        $this->moduleCircuits->resetForModule($component);
        $this->circuitManager->reset($component);

        $this->telemetry->info('governance', 'governance.cascade.released', [
            'component' => $component,
        ]);

        return ['released' => true, 'component' => $component];
    }
}
