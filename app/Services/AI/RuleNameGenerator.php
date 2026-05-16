<?php

namespace App\Services\AI;

class RuleNameGenerator
{
    public function generate(string $prompt): string
    {
        $name = strtolower($prompt);

        // remove common words
        $name = preg_replace('/\b(create|an|a|the|engineering|rule|for|in)\b/', '', $name);

        // replace non letters with _
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);

        // remove duplicate _
        $name = preg_replace('/_+/', '_', $name);

        // trim _
        $name = trim($name, '_');

        // limit length
        $name = substr($name, 0, 60);

        if (empty($name)) {
            $name = 'ai_generated_rule';
        }

        return $name;
    }
}
