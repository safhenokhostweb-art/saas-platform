<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance;

class ExtensionRuntimePressureAnalyzer
{
    public const PRESSURE_NORMAL   = 'normal';
    public const PRESSURE_ELEVATED = 'elevated';
    public const PRESSURE_HIGH     = 'high';
    public const PRESSURE_CRITICAL = 'critical';

    private const ELEVATED_PCT  = 50.0;
    private const HIGH_PCT      = 75.0;
    private const CRITICAL_PCT  = 90.0;

    public function __construct(
        private PluginResourceBudget $budget
    ) {}

    public function analyze(string $moduleId): array
    {
        $utilization = $this->budget->utilizationPct($moduleId);
        $level       = $this->classifyPressure($utilization);

        return [
            'module_id'      => $moduleId,
            'utilization_pct'=> $utilization,
            'pressure_level' => $level,
            'budget'         => $this->budget->snapshot($moduleId),
        ];
    }

    public function pressureLevel(string $moduleId): string
    {
        return $this->classifyPressure($this->budget->utilizationPct($moduleId));
    }

    public function isUnderPressure(string $moduleId): bool
    {
        return in_array($this->pressureLevel($moduleId), [
            self::PRESSURE_HIGH, self::PRESSURE_CRITICAL,
        ], true);
    }

    private function classifyPressure(float $pct): string
    {
        if ($pct >= self::CRITICAL_PCT) {
            return self::PRESSURE_CRITICAL;
        }
        if ($pct >= self::HIGH_PCT) {
            return self::PRESSURE_HIGH;
        }
        if ($pct >= self::ELEVATED_PCT) {
            return self::PRESSURE_ELEVATED;
        }
        return self::PRESSURE_NORMAL;
    }
}
