<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Metrics;

use SaaSPlatform\CoreUtils\PluginGovernance\Trust\ModuleTrustRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\DependencyRiskClassifier;

final class PluginRiskAssessment
{
    public const RISK_MINIMAL  = 'minimal';
    public const RISK_LOW      = 'low';
    public const RISK_MODERATE = 'moderate';
    public const RISK_HIGH     = 'high';
    public const RISK_CRITICAL = 'critical';

    public static function assess(
        string $moduleId,
        string $trustLevel,
        string $dependencyClassification,
        int    $violationCount,
        bool   $quarantined
    ): array {
        if ($quarantined) {
            return self::result($moduleId, self::RISK_CRITICAL, 'Module is quarantined', 0);
        }

        if ($dependencyClassification === DependencyRiskClassifier::BLOCKED) {
            return self::result($moduleId, self::RISK_CRITICAL, 'Blocked dependency patterns detected', 0);
        }

        if ($trustLevel === ModuleTrustRegistry::LEVEL_UNTRUSTED) {
            return self::result($moduleId, self::RISK_HIGH, 'Untrusted module', 20);
        }

        $score = 100;

        if ($dependencyClassification === DependencyRiskClassifier::UNSAFE)  $score -= 40;
        if ($dependencyClassification === DependencyRiskClassifier::WARNING)  $score -= 15;
        if ($trustLevel === ModuleTrustRegistry::LEVEL_SANDBOXED)             $score -= 20;
        $score -= min(30, $violationCount * 5);

        $risk = match(true) {
            $score >= 85 => self::RISK_MINIMAL,
            $score >= 70 => self::RISK_LOW,
            $score >= 50 => self::RISK_MODERATE,
            $score >= 25 => self::RISK_HIGH,
            default      => self::RISK_CRITICAL,
        };

        return self::result($moduleId, $risk, '', max(0, $score));
    }

    private static function result(string $moduleId, string $risk, string $reason, int $score): array
    {
        return [
            'module_id'   => $moduleId,
            'risk_level'  => $risk,
            'reason'      => $reason,
            'risk_score'  => $score,
            'assessed_at' => now()->toIso8601String(),
        ];
    }
}
