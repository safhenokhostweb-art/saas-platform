<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Metrics;

use SaaSPlatform\CoreUtils\PluginGovernance\CapabilityResolutionEngine;
use SaaSPlatform\CoreUtils\PluginGovernance\ModuleCapabilityRegistry;

final class CapabilityUsageMetrics
{
    public static function forModule(
        string                    $moduleId,
        CapabilityResolutionEngine $resolver,
        ModuleCapabilityRegistry  $registry
    ): array {
        $all      = $registry->all();
        $granted  = $resolver->effectiveCapabilities($moduleId);
        $denied   = $resolver->deniedCapabilities($moduleId);
        $total    = count($all);

        $highRiskGranted = array_filter($granted, function (string $cap) use ($registry) {
            $def = $registry->get($cap);
            return $def && $def->isHighRisk();
        });

        return [
            'module_id'          => $moduleId,
            'total_capabilities' => $total,
            'granted_count'      => count($granted),
            'denied_count'       => count($denied),
            'granted'            => $granted,
            'denied'             => $denied,
            'high_risk_granted'  => array_values($highRiskGranted),
            'grant_ratio'        => $total > 0 ? round(count($granted) / $total * 100, 1) : 0,
            'captured_at'        => now()->toIso8601String(),
        ];
    }

    public static function platformSummary(
        ModuleCapabilityRegistry $registry
    ): array {
        $byRisk = [];

        foreach ($registry->all() as $name => $definition) {
            $risk          = $definition->riskLevel();
            $byRisk[$risk] = ($byRisk[$risk] ?? 0) + 1;
        }

        return [
            'total_registered' => count($registry->all()),
            'by_risk_level'    => $byRisk,
            'require_grant'    => count($registry->requireingExplicitGrant()),
        ];
    }
}
