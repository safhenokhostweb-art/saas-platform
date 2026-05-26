<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance;

class SandboxLoadBalancer
{
    public function __construct(
        private PluginFairnessCoordinator        $fairness,
        private ExtensionRuntimePressureAnalyzer $pressureAnalyzer
    ) {}

    public function shouldThrottle(string $moduleId): bool
    {
        return $this->pressureAnalyzer->isUnderPressure($moduleId);
    }

    public function throttleFactor(string $moduleId): float
    {
        $level = $this->pressureAnalyzer->pressureLevel($moduleId);

        return match($level) {
            ExtensionRuntimePressureAnalyzer::PRESSURE_CRITICAL => 0.1,
            ExtensionRuntimePressureAnalyzer::PRESSURE_HIGH     => 0.4,
            ExtensionRuntimePressureAnalyzer::PRESSURE_ELEVATED => 0.7,
            default                                              => 1.0,
        };
    }

    public function leastLoadedQueue(array $moduleIds): ?string
    {
        if (empty($moduleIds)) {
            return null;
        }

        $minUtilization = PHP_FLOAT_MAX;
        $best           = null;

        foreach ($moduleIds as $moduleId) {
            $analysis = $this->pressureAnalyzer->analyze($moduleId);
            $util     = $analysis['utilization_pct'];

            if ($util < $minUtilization) {
                $minUtilization = $util;
                $best           = $moduleId;
            }
        }

        return $best;
    }

    public function platformStatus(): array
    {
        $summary = $this->fairness->platformFairnessSummary();

        return array_merge($summary, [
            'load_balance_status' => $summary['fairness_score'] >= 70 ? 'balanced' : 'imbalanced',
        ]);
    }
}
