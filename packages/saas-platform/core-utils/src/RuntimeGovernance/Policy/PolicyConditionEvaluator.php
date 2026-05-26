<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Policy;

class PolicyConditionEvaluator
{
    public function evaluate(array $condition, array $signals): bool
    {
        $metric    = $condition['metric'] ?? '';
        $operator  = $condition['operator'] ?? '=';
        $threshold = $condition['threshold'];

        $value = $this->resolveMetric($metric, $signals);

        if ($value === null) {
            return false;
        }

        return match ($operator) {
            '>'     => is_numeric($value) && $value > $threshold,
            '>='    => is_numeric($value) && $value >= $threshold,
            '<'     => is_numeric($value) && $value < $threshold,
            '<='    => is_numeric($value) && $value <= $threshold,
            '='     => $value == $threshold,
            '!='    => $value != $threshold,
            'in'    => is_array($threshold) && in_array($value, $threshold),
            'not_in' => is_array($threshold) && !in_array($value, $threshold),
            'contains' => is_string($value) && str_contains($value, (string) $threshold),
            default => false,
        };
    }

    public function evaluateAll(array $conditions, string $logicalOperator, array $signals): bool
    {
        if (empty($conditions)) {
            return false;
        }

        $results = array_map(fn($c) => $this->evaluate($c, $signals), $conditions);

        return strtoupper($logicalOperator) === 'OR'
            ? in_array(true, $results, true)
            : !in_array(false, $results, true);
    }

    private function resolveMetric(string $metric, array $signals): mixed
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
}
