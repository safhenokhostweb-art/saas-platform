<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance;

use Illuminate\Support\Facades\Cache;

class RuntimeDecisionEngine
{
    private const COOLDOWN_PREFIX = 'governance.policy.cooldown.';

    public function evaluate(array $signals, array $policies): array
    {
        $decisions = [];

        foreach ($policies as $policy) {
            if ($this->isOnCooldown($policy['name'])) {
                continue;
            }

            if ($this->conditionsMet($policy, $signals)) {
                $decisions[] = [
                    'policy'     => $policy['name'],
                    'version'    => $policy['version'] ?? '1.0',
                    'actions'    => $policy['actions'],
                    'priority'   => $this->computePriority($policy),
                    'decided_at' => now()->toIso8601String(),
                ];
            }
        }

        return $this->prioritize($decisions);
    }

    public function prioritize(array $decisions): array
    {
        usort($decisions, fn($a, $b) => $b['priority'] <=> $a['priority']);
        return $decisions;
    }

    public function markExecuted(string $policyName, int $cooldownSeconds): void
    {
        Cache::put(self::COOLDOWN_PREFIX . md5($policyName), 1, $cooldownSeconds);
    }

    public function isOnCooldown(string $policyName): bool
    {
        return Cache::has(self::COOLDOWN_PREFIX . md5($policyName));
    }

    private function conditionsMet(array $policy, array $signals): bool
    {
        $conditions = $policy['conditions'] ?? [];
        $operator   = strtoupper($policy['condition_operator'] ?? 'AND');

        if (empty($conditions)) {
            return false;
        }

        $results = array_map(fn($c) => $this->evaluateCondition($c, $signals), $conditions);

        return $operator === 'OR'
            ? in_array(true, $results, true)
            : !in_array(false, $results, true);
    }

    private function evaluateCondition(array $condition, array $signals): bool
    {
        $metric    = $condition['metric'] ?? '';
        $op        = $condition['operator'] ?? '=';
        $threshold = $condition['threshold'];

        $value = $this->resolveSignal($metric, $signals);

        if ($value === null) {
            return false;
        }

        return match ($op) {
            '>'     => $value > $threshold,
            '>='    => $value >= $threshold,
            '<'     => $value < $threshold,
            '<='    => $value <= $threshold,
            '='     => $value == $threshold,
            '!='    => $value != $threshold,
            default => false,
        };
    }

    private function resolveSignal(string $metric, array $signals): mixed
    {
        $parts = explode('.', $metric);
        $value = $signals;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    private function computePriority(array $policy): int
    {
        // Higher priority for security/emergency actions
        foreach ($policy['actions'] ?? [] as $action) {
            $type = $action['type'] ?? '';
            if ($type === 'deployment_freeze')  return 100;
            if ($type === 'state_transition' && ($action['state'] ?? '') === GovernanceContext::STATE_EMERGENCY) return 95;
            if ($type === 'state_transition' && ($action['state'] ?? '') === GovernanceContext::STATE_CRITICAL)  return 90;
            if ($type === 'recovery')           return 80;
            if ($type === 'degradation')        return 70;
        }

        return 50;
    }
}
