<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Metrics;

use SaaSPlatform\CoreUtils\PluginGovernance\Containment\SandboxViolationTelemetry;
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\ExtensionQuarantineService;

final class SandboxIsolationMetrics
{
    public static function capture(
        string                   $moduleId,
        SandboxViolationTelemetry $violationTelemetry,
        ExtensionQuarantineService $quarantine
    ): array {
        $violations   = $violationTelemetry->getViolations($moduleId);
        $quarantined  = $quarantine->isQuarantined($moduleId);
        $quarantineRecord = $quarantine->getQuarantineRecord($moduleId);

        $violationTypes = [];
        foreach ($violations as $v) {
            $type = $v['type'] ?? 'unknown';
            $violationTypes[$type] = ($violationTypes[$type] ?? 0) + 1;
        }

        return [
            'module_id'        => $moduleId,
            'quarantined'      => $quarantined,
            'quarantine_reason'=> $quarantineRecord['reason'] ?? null,
            'violation_count'  => count($violations),
            'violation_types'  => $violationTypes,
            'isolation_score'  => self::computeIsolationScore(count($violations), $quarantined),
            'captured_at'      => now()->toIso8601String(),
        ];
    }

    public static function computeIsolationScore(int $violations, bool $quarantined): int
    {
        if ($quarantined) return 0;
        if ($violations === 0) return 100;
        if ($violations <= 2)  return 70;
        if ($violations <= 5)  return 40;
        return 10;
    }
}
