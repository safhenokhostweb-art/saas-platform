<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics;

use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\CircuitHealthTracker;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\IncidentResolutionTracker;

class RuntimeStabilityIndexCalculator
{
    private const WEIGHTS = [
        'platform_state'    => 30,
        'circuit_health'    => 25,
        'incident_pressure' => 25,
        'recovery_velocity' => 20,
    ];

    public function __construct(
        private GovernanceContext         $context,
        private CircuitHealthTracker      $circuitTracker,
        private IncidentResolutionTracker $incidentTracker,
        private RuntimeGovernanceMetrics  $metrics
    ) {}

    public function calculate(): array
    {
        $dimensions = [
            'platform_state'    => $this->scorePlatformState(),
            'circuit_health'    => $this->scoreCircuitHealth(),
            'incident_pressure' => $this->scoreIncidentPressure(),
            'recovery_velocity' => $this->scoreRecoveryVelocity(),
        ];

        $total = 0;
        $breakdown = [];
        foreach ($dimensions as $key => $raw) {
            $weight      = self::WEIGHTS[$key];
            $contribution = (int) round($raw * $weight / 100);
            $total       += $contribution;
            $breakdown[$key] = [
                'raw_score'    => $raw,
                'weight'       => $weight,
                'contribution' => $contribution,
            ];
        }

        return [
            'index'     => $total,
            'grade'     => $this->grade($total),
            'trend'     => $this->trend($total),
            'breakdown' => $breakdown,
            'computed_at' => now()->toIso8601String(),
        ];
    }

    public function grade(int $index): string
    {
        return match (true) {
            $index >= 90 => 'A+',
            $index >= 80 => 'A',
            $index >= 70 => 'B',
            $index >= 60 => 'C',
            $index >= 50 => 'D',
            default      => 'F',
        };
    }

    public function isStable(): bool
    {
        return $this->calculate()['index'] >= 70;
    }

    private function scorePlatformState(): int
    {
        return match ($this->context->getState()) {
            GovernanceContext::STATE_HEALTHY   => 100,
            GovernanceContext::STATE_DEGRADED  => 60,
            GovernanceContext::STATE_CRITICAL  => 20,
            GovernanceContext::STATE_EMERGENCY => 0,
            default                            => 50,
        };
    }

    private function scoreCircuitHealth(): int
    {
        return $this->circuitTracker->healthScore();
    }

    private function scoreIncidentPressure(): int
    {
        $active = $this->incidentTracker->activeCount();
        $hasCritical = $this->incidentTracker->hasCriticalActive();

        if ($hasCritical) {
            return max(0, 30 - ($active * 10));
        }

        return max(0, 100 - ($active * 15));
    }

    private function scoreRecoveryVelocity(): int
    {
        $recovery = $this->metrics->recoveryActions(6);

        if (!($recovery['available'] ?? false)) {
            return 70; // neutral score when no data
        }

        $rate = $recovery['success_rate'] ?? null;
        if ($rate === null) {
            return 80;
        }

        return (int) $rate;
    }

    private function trend(int $current): string
    {
        $transitions = $this->metrics->stateTransitions(1);
        $count       = $transitions['count'];

        if ($count === 0) {
            return 'stable';
        }

        $last = end($transitions['transitions']);
        if (!$last) {
            return 'stable';
        }

        $toState = $last['to'] ?? GovernanceContext::STATE_HEALTHY;

        return match ($toState) {
            GovernanceContext::STATE_HEALTHY   => 'improving',
            GovernanceContext::STATE_DEGRADED  => 'declining',
            GovernanceContext::STATE_CRITICAL,
            GovernanceContext::STATE_EMERGENCY => 'critical_decline',
            default                            => 'stable',
        };
    }
}
