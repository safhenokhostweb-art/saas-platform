<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance;

use Illuminate\Support\Facades\Cache;

class PluginFairnessCoordinator
{
    private const TRACKED_PREFIX = 'plugin.fairness.tracked';
    private const TRACKED_TTL   = 3600;

    public function __construct(
        private ExtensionRuntimePressureAnalyzer $pressureAnalyzer
    ) {}

    public function registerModule(string $moduleId): void
    {
        $tracked = $this->trackedModules();
        $tracked[$moduleId] = true;
        Cache::put(self::TRACKED_PREFIX, $tracked, self::TRACKED_TTL);
    }

    public function deregisterModule(string $moduleId): void
    {
        $tracked = $this->trackedModules();
        unset($tracked[$moduleId]);
        Cache::put(self::TRACKED_PREFIX, $tracked, self::TRACKED_TTL);
    }

    public function trackedModules(): array
    {
        return Cache::get(self::TRACKED_PREFIX, []);
    }

    public function platformFairnessSummary(): array
    {
        $modules     = array_keys($this->trackedModules());
        $pressures   = [];
        $noisyCount  = 0;

        foreach ($modules as $moduleId) {
            $level = $this->pressureAnalyzer->pressureLevel($moduleId);
            $pressures[$moduleId] = $level;

            if ($this->pressureAnalyzer->isUnderPressure($moduleId)) {
                $noisyCount++;
            }
        }

        $total      = count($modules);
        $noisyPct   = $total > 0 ? ($noisyCount / $total) * 100 : 0;
        $fairness   = max(0.0, 100.0 - ($noisyPct * 1.5));

        return [
            'total_modules'   => $total,
            'noisy_modules'   => $noisyCount,
            'noisy_pct'       => round($noisyPct, 1),
            'fairness_score'  => round($fairness, 1),
            'pressures'       => $pressures,
        ];
    }
}
