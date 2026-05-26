<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics;

use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\IncidentResolutionTracker;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\CircuitHealthTracker;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RuntimeThrottleManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Queue\DeadLetterGovernanceService;
use Illuminate\Support\Facades\Cache;

class RuntimeGovernanceSnapshot
{
    public function __construct(
        private GovernanceContext         $context,
        private IncidentResolutionTracker $incidentTracker,
        private CircuitHealthTracker      $circuitTracker,
        private RuntimeThrottleManager    $throttleManager,
        private DeadLetterGovernanceService $deadLetterService
    ) {}

    public function capture(): array
    {
        return [
            'captured_at'    => now()->toIso8601String(),
            'platform_state' => $this->context->getState(),
            'deployment'     => $this->deploymentStatus(),
            'circuits'       => $this->circuitStatus(),
            'incidents'      => $this->incidentStatus(),
            'throttle'       => $this->throttleStatus(),
            'queue'          => $this->queueStatus(),
        ];
    }

    private function deploymentStatus(): array
    {
        $frozen = Cache::get('governance.deployment.frozen');
        return [
            'frozen'     => (bool) $frozen,
            'frozen_at'  => $frozen['frozen_at'] ?? null,
            'reason'     => $frozen['reason'] ?? null,
            'auto_frozen' => $frozen['auto_frozen'] ?? false,
        ];
    }

    private function circuitStatus(): array
    {
        $states = $this->circuitTracker->allStates();
        return [
            'health_score' => $this->circuitTracker->healthScore(),
            'open_count'   => count($this->circuitTracker->openCircuits()),
            'has_open'     => $this->circuitTracker->hasOpenCircuits(),
            'states'       => $states,
        ];
    }

    private function incidentStatus(): array
    {
        $active = $this->incidentTracker->activeIncidents();
        return [
            'active_count'    => count($active),
            'has_critical'    => $this->incidentTracker->hasCriticalActive(),
            'active_incidents' => $active,
        ];
    }

    private function throttleStatus(): array
    {
        return $this->throttleManager->snapshot();
    }

    private function queueStatus(): array
    {
        $quarantined = [];
        foreach (['default', 'high', 'low', 'notifications'] as $queue) {
            if ($this->deadLetterService->isQuarantined($queue)) {
                $quarantined[] = $queue;
            }
        }

        return [
            'quarantined_queues' => $quarantined,
            'has_quarantined'    => !empty($quarantined),
        ];
    }
}
