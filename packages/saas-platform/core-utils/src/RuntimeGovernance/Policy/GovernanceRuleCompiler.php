<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Policy;

class GovernanceRuleCompiler
{
    private const REQUIRED_FIELDS = ['name', 'conditions', 'actions'];

    public function compile(array $rawPolicy): array
    {
        $this->validate($rawPolicy);

        return [
            'name'               => $rawPolicy['name'],
            'version'            => $rawPolicy['version'] ?? '1.0',
            'enabled'            => $rawPolicy['enabled'] ?? true,
            'trigger'            => $rawPolicy['trigger'] ?? 'periodic',
            'condition_operator' => strtoupper($rawPolicy['condition_operator'] ?? 'AND'),
            'conditions'         => $this->compileConditions($rawPolicy['conditions']),
            'actions'            => $this->compileActions($rawPolicy['actions']),
            'cooldown_seconds'   => max(60, (int) ($rawPolicy['cooldown_seconds'] ?? 300)),
            'compiled_at'        => now()->toIso8601String(),
        ];
    }

    public function compileMany(array $policies): array
    {
        return array_map(fn($p) => $this->compile($p), $policies);
    }

    public function describe(array $compiledPolicy): string
    {
        $conditions = array_map(fn($c) =>
            "{$c['metric']} {$c['operator']} {$c['threshold']}",
            $compiledPolicy['conditions']
        );

        $operator = $compiledPolicy['condition_operator'];
        $actions  = array_column($compiledPolicy['actions'], 'type');

        return "Policy '{$compiledPolicy['name']}': IF " .
               implode(" {$operator} ", $conditions) .
               " THEN " . implode(', ', $actions);
    }

    private function validate(array $policy): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $policy)) {
                throw new \InvalidArgumentException("Policy missing required field: {$field}");
            }
        }

        if (!is_array($policy['conditions']) || empty($policy['conditions'])) {
            throw new \InvalidArgumentException("Policy '{$policy['name']}' must have at least one condition");
        }

        if (!is_array($policy['actions']) || empty($policy['actions'])) {
            throw new \InvalidArgumentException("Policy '{$policy['name']}' must have at least one action");
        }
    }

    private function compileConditions(array $conditions): array
    {
        return array_map(fn($c) => [
            'metric'    => (string) ($c['metric'] ?? ''),
            'operator'  => (string) ($c['operator'] ?? '='),
            'threshold' => $c['threshold'] ?? null,
        ], $conditions);
    }

    private function compileActions(array $actions): array
    {
        return array_map(fn($a) => array_merge([
            'type' => 'telemetry',
        ], $a), $actions);
    }
}
