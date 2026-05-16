<?php

namespace App\Services\AI;

class RuleDetector
{
    public function isRule(string $text): bool
    {
        return str_contains($text, 'RULE')
            || str_contains($text, 'Engineering Rule')
            || str_contains($text, 'Policy');
    }
}
