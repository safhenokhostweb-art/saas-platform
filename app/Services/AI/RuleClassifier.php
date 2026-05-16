<?php

namespace App\Services\AI;

class RuleClassifier
{
    public function classify(string $ruleContent): string
    {
        $content = strtolower($ruleContent);

        if (str_contains($content, 'tenant')) {
            return 'saas';
        }

        if (str_contains($content, 'database') || str_contains($content, 'migration')) {
            return 'database';
        }

        if (str_contains($content, 'laravel') || str_contains($content, 'controller')) {
            return 'architecture';
        }

        if (str_contains($content, 'security') || str_contains($content, 'auth')) {
            return 'security';
        }

        if (str_contains($content, 'ai') || str_contains($content, 'model')) {
            return 'ai';
        }

        return 'general';
    }
}
