<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics;

use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\CircuitHealthTracker;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\IncidentResolutionTracker;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RuntimeThrottleManager;
use SaaSPlatform\CoreUtils\Telemetry\Metrics\OperationalMetricsAggregator;
use Illuminate\Support\Facades\Cache;

class GovernanceStatusAggregator
{
    public function __construct(
        private GovernanceContext              $context,
        private CircuitHealthTracker           $circuitTracker,
        private IncidentResolutionTracker      $incidentTracker,
        private RuntimeThrottleManager         $throttleManager,
        private RuntimeGovernanceMetrics       $metrics,
        private OperationalMetricsAggregator   $operationalMetrics
    ) {}

    public function aggregate(): array
    {
        return [
            'aggregated_at'   => now()->toIso8601String(),
            'platform_state'  => $this->context->getState(),
            'health'          => $this->healthSummary(),
            'governance'      => $this->governanceSummary(),
            'operations'      => $this->operationsSummary(),
            'alerts'          => $this->activeAlerts(),
        ];
    }

    public function healthSummary(): array
    {
        $circuitScore  = $this->circuitTracker->healthScore();
        $throttle      = $this->throttleManager->snapshot();
        $hasIncidents  = $this->incidentTracker->hasCriticalActive();
        $activeCount   = $this->incidentTracker->activeCount();

        $overallScore = $this->computeOverallScore($circuitScore, $throttle, $hasIncidents);

        return [
            'overall_score'    => $overallScore,
            'circuit_score'    => $circuitScore,
            'active_incidents' => $activeCount,
            'has_critical'     => $hasIncidents,
            'throttle_active'  => $throttle['global']['active'] ?? false,
            'deployment_frozen' => (bool) Cache::get('governance.deployment.frozen'),
        ];
    }

    private function governanceSummary(): array
    {
        $transitions = $this->metrics->stateTransitions(24);
        $recovery    = $this->metrics->recoveryActions(24);
        $incidents   = $this->metrics->incidentMetrics(24);

        return [
            'state_transitions_24h' => $transitions['count'],
            'recovery_actions_24h'  => $recovery['total'] ?? 0,
            'recovery_success_rate' => $recovery['success_rate'] ?? null,
            'incidents_24h'         => $incidents['total'] ?? 0,
        ];
    }

    private function operationsSummary(): array
    {
        try {
            $ops = $this->operationalMetrics->aggregate(24);
            return [
                'health_score' => $ops['health_score']['score'] ?? null,
                'grade'        => $ops['health_score']['grade'] ?? null,
            ];
        } catch (\Throwable) {
            return ['health_score' => null, 'grade' => null];
        }
    }

    private function activeAlerts(): array
    {
        $alerts = [];

        if ($this->context->getState() === GovernanceContext::STATE_EMERGENCY) {
            $alerts[] = ['level' => 'emergency', 'message' => 'Platform in EMERGENCY state — operator intervention required'];
        }

        if ($this->incidentTracker->hasCriticalActive()) {
            $alerts[] = ['level' => 'critical', 'message' => 'Critical active incidents require operator attention'];
        }

        if ($this->circuitTracker->hasOpenCircuits()) {
            $open = $this->circuitTracker->openCircuits();
            $alerts[] = ['level' => 'warning', 'message' => count($open) . ' circuit(s) open: ' . implode(', ', $open)];
        }

        if (Cache::get('governance.deployment.frozen')) {
            $alerts[] = ['level' => 'info', 'message' => 'Deployment is frozen by governance'];
        }

        return $alerts;
    }

    private function computeOverallScore(int $circuitScore, array $throttle, bool $hasIncidents): int
    {
        $score = $circuitScore;

        if ($hasIncidents) {
            $score -= 30;
        }

        if (!empty($throttle['global']['active'])) {
            $score -= 20;
        }

        return max(0, min(100, $score));
    }
}
