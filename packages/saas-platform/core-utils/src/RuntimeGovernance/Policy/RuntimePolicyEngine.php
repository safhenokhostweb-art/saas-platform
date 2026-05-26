<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Policy;

use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernancePolicyRegistry;
use SaaSPlatform\CoreUtils\RuntimeGovernance\RuntimeDecisionEngine;

class RuntimePolicyEngine
{
    public function __construct(
        private GovernancePolicyRegistry $registry,
        private PolicyConditionEvaluator $evaluator,
        private GovernanceRuleCompiler   $compiler,
        private RuntimePolicyExecutor    $executor,
        private RuntimeDecisionEngine    $decisionEngine
    ) {}

    public function evaluate(array $signals, string $trigger = 'periodic'): array
    {
        $policies     = $this->registry->matching($trigger);
        $matchedCount = 0;
        $results      = [];

        foreach ($policies as $policy) {
            if ($this->decisionEngine->isOnCooldown($policy['name'])) {
                continue;
            }

            $met = $this->evaluator->evaluateAll(
                $policy['conditions'] ?? [],
                $policy['condition_operator'] ?? 'AND',
                $signals
            );

            if ($met) {
                $compiled  = $this->compiler->compile($policy);
                $result    = $this->executor->execute($compiled, $signals);
                $results[] = $result;
                $matchedCount++;

                $this->decisionEngine->markExecuted(
                    $policy['name'],
                    $policy['cooldown_seconds'] ?? 300
                );
            }
        }

        return [
            'trigger'       => $trigger,
            'evaluated'     => count($policies),
            'matched'       => $matchedCount,
            'executions'    => $results,
        ];
    }

    public function register(array $policy): void
    {
        $compiled = $this->compiler->compile($policy);
        $this->registry->register($compiled['name'], $compiled);
    }

    public function listPolicies(): array
    {
        return array_map(fn($p) => [
            'name'             => $p['name'],
            'version'          => $p['version'] ?? '1.0',
            'enabled'          => $p['enabled'] ?? true,
            'trigger'          => $p['trigger'] ?? 'periodic',
            'condition_count'  => count($p['conditions'] ?? []),
            'action_count'     => count($p['actions'] ?? []),
            'cooldown_seconds' => $p['cooldown_seconds'] ?? 300,
            'on_cooldown'      => $this->decisionEngine->isOnCooldown($p['name']),
            'description'      => $this->compiler->describe($p),
        ], $this->registry->all());
    }
}
