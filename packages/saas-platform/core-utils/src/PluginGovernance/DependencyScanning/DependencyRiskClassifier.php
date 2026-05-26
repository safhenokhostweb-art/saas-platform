<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning;

final class DependencyRiskClassifier
{
    public const SAFE    = 'SAFE';
    public const WARNING = 'WARNING';
    public const UNSAFE  = 'UNSAFE';
    public const BLOCKED = 'BLOCKED';

    private const SEVERITY_ORDER = [
        self::SAFE    => 0,
        self::WARNING => 1,
        self::UNSAFE  => 2,
        self::BLOCKED => 3,
    ];

    public function classify(array $findings): string
    {
        if (empty($findings)) {
            return self::SAFE;
        }

        $max = self::SAFE;

        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? self::SAFE;

            if ((self::SEVERITY_ORDER[$severity] ?? 0) > (self::SEVERITY_ORDER[$max] ?? 0)) {
                $max = $severity;
            }
        }

        return $max;
    }

    public function isExecutionAllowed(string $classification): bool
    {
        return in_array($classification, [self::SAFE, self::WARNING], true);
    }

    public function isSandboxable(string $classification): bool
    {
        return $classification !== self::BLOCKED;
    }

    public function requiresReview(string $classification): bool
    {
        return in_array($classification, [self::WARNING, self::UNSAFE], true);
    }

    public function toScore(string $classification): int
    {
        return match($classification) {
            self::SAFE    => 100,
            self::WARNING => 60,
            self::UNSAFE  => 20,
            self::BLOCKED => 0,
            default       => 0,
        };
    }
}
