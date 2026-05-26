<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CascadeProtection;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService;

class CascadingFailureDetector
{
    private const FAILURE_WINDOW_KEY = 'governance.cascade.failures';
    private const CASCADE_THRESHOLD  = 2; // n components failing = cascade
    private const WINDOW_SECONDS     = 120;

    public function __construct(
        private DependencyImpactGraph       $graph,
        private RuntimeBlastRadiusAnalyzer  $blastAnalyzer,
        private FailureContainmentCoordinator $containment,
        private RuntimeHealthService        $healthService,
        private TelemetryManager            $telemetry
    ) {}

    public function detect(): array
    {
        $failingComponents = $this->identifyFailingComponents();

        if (count($failingComponents) < self::CASCADE_THRESHOLD) {
            return ['cascade_detected' => false, 'failing' => $failingComponents];
        }

        $analysis = $this->blastAnalyzer->analyzeMultiple($failingComponents);
        $isCascade = $this->isCascadePattern($failingComponents);

        if ($isCascade) {
            $this->telemetry->critical('governance', 'governance.cascade.detected', [
                'failing_components' => $failingComponents,
                'blast_radius'       => $analysis['total_impacted'],
                'risk_level'         => $analysis['risk_level'],
            ]);

            $this->containment->contain($failingComponents[0]);
        }

        return [
            'cascade_detected'   => $isCascade,
            'failing_components' => $failingComponents,
            'analysis'           => $analysis,
        ];
    }

    public function recordComponentFailure(string $component): void
    {
        $failures = Cache::get(self::FAILURE_WINDOW_KEY, []);
        $now      = time();

        // Expire old entries outside the window
        $failures = array_filter($failures, fn($t) => ($now - $t) < self::WINDOW_SECONDS);
        $failures[$component] = $now;

        Cache::put(self::FAILURE_WINDOW_KEY, $failures, self::WINDOW_SECONDS);
    }

    private function identifyFailingComponents(): array
    {
        $failing = [];

        try {
            $health = $this->healthService->ready();
            $checks = $health['checks'] ?? [];

            foreach ($checks as $name => $check) {
                if (($check['status'] ?? 'ok') !== 'ok') {
                    $failing[] = $name;
                    $this->recordComponentFailure($name);
                }
            }
        } catch (\Throwable) {}

        // Also check cached failure window
        $cached = Cache::get(self::FAILURE_WINDOW_KEY, []);
        $now    = time();

        foreach ($cached as $component => $time) {
            if (($now - $time) < self::WINDOW_SECONDS && !in_array($component, $failing)) {
                $failing[] = $component;
            }
        }

        return array_unique($failing);
    }

    private function isCascadePattern(array $components): bool
    {
        // Check if any failing component caused others (downstream relationship)
        foreach ($components as $component) {
            $downstream = $this->graph->blastRadius($component);
            if (!empty(array_intersect($downstream, $components))) {
                return true;
            }
        }

        return count($components) >= self::CASCADE_THRESHOLD;
    }
}
