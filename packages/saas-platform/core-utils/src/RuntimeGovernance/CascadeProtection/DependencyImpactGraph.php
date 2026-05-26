<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CascadeProtection;

class DependencyImpactGraph
{
    // Adjacency list: component → downstream dependents
    private const DEPENDENCY_MAP = [
        'database'        => ['queue', 'cache', 'tickets', 'forms', 'notifications', 'telemetry'],
        'cache'           => ['queue', 'session', 'telemetry'],
        'queue'           => ['notifications', 'reports', 'exports'],
        'notifications'   => [],
        'tickets'         => ['notifications'],
        'forms'           => ['notifications'],
        'reports'         => [],
        'exports'         => [],
        'telemetry'       => [],
        'session'         => [],
    ];

    public function downstreamOf(string $component): array
    {
        return self::DEPENDENCY_MAP[$component] ?? [];
    }

    public function blastRadius(string $component): array
    {
        $visited = [];
        $this->traverse($component, $visited);
        unset($visited[$component]);

        return array_keys($visited);
    }

    public function criticalPath(string $failing, string $target): bool
    {
        return in_array($target, $this->blastRadius($failing));
    }

    public function isCriticalComponent(string $component): bool
    {
        // Components whose failure affects ≥ 3 others are critical
        return count($this->blastRadius($component)) >= 3;
    }

    public function failureScore(string $component): int
    {
        return count($this->blastRadius($component));
    }

    private function traverse(string $component, array &$visited): void
    {
        if (isset($visited[$component])) {
            return;
        }

        $visited[$component] = true;

        foreach (self::DEPENDENCY_MAP[$component] ?? [] as $dep) {
            $this->traverse($dep, $visited);
        }
    }
}
