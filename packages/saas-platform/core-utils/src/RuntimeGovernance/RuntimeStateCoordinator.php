<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeStateCoordinator
{
    private const HISTORY_KEY  = 'governance.runtime.state.history';
    private const MAX_HISTORY  = 50;

    public function __construct(
        private GovernanceContext $context,
        private TelemetryManager  $telemetry
    ) {}

    public function currentState(): string
    {
        return $this->context->currentState();
    }

    public function transitionTo(string $state, string $reason, string $triggeredBy = 'system'): bool
    {
        $previous = $this->currentState();

        if (!$this->isValidTransition($previous, $state)) {
            return false;
        }

        $this->context->setState($state);
        $this->recordTransition($previous, $state, $reason, $triggeredBy);

        $this->telemetry->emit('governance', $this->severityForState($state), 'governance.state.transition', [
            'from'         => $previous,
            'to'           => $state,
            'reason'       => $reason,
            'triggered_by' => $triggeredBy,
        ]);

        return true;
    }

    public function recover(string $reason = 'system_recovery'): bool
    {
        $current = $this->currentState();

        if ($current === GovernanceContext::STATE_HEALTHY) {
            return true;
        }

        return $this->transitionTo(GovernanceContext::STATE_HEALTHY, $reason, 'recovery_engine');
    }

    public function isHealthy(): bool
    {
        return $this->context->isHealthy();
    }

    public function isCritical(): bool
    {
        return $this->context->isCritical();
    }

    public function history(int $limit = 20): array
    {
        $history = Cache::get(self::HISTORY_KEY, []);
        return array_slice(array_reverse($history), 0, $limit);
    }

    private function isValidTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }

        // Emergency can only be cleared by human intervention or explicit recovery
        if ($from === GovernanceContext::STATE_EMERGENCY && $to !== GovernanceContext::STATE_CRITICAL) {
            return false;
        }

        return in_array($to, GovernanceContext::validStates(), true);
    }

    private function recordTransition(string $from, string $to, string $reason, string $triggeredBy): void
    {
        $history   = Cache::get(self::HISTORY_KEY, []);
        $history[] = [
            'from'         => $from,
            'to'           => $to,
            'reason'       => $reason,
            'triggered_by' => $triggeredBy,
            'at'           => now()->toIso8601String(),
        ];

        if (count($history) > self::MAX_HISTORY) {
            $history = array_slice($history, -self::MAX_HISTORY);
        }

        Cache::forever(self::HISTORY_KEY, $history);
    }

    private function severityForState(string $state): string
    {
        return match ($state) {
            GovernanceContext::STATE_HEALTHY   => TelemetryManager::SEVERITY_INFO,
            GovernanceContext::STATE_DEGRADED  => TelemetryManager::SEVERITY_WARNING,
            GovernanceContext::STATE_CRITICAL  => TelemetryManager::SEVERITY_ERROR,
            GovernanceContext::STATE_EMERGENCY => TelemetryManager::SEVERITY_CRITICAL,
            default                            => TelemetryManager::SEVERITY_INFO,
        };
    }
}
