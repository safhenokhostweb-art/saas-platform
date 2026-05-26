<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\CascadeProtection;

class RuntimeBlastRadiusAnalyzer
{
    public function __construct(private DependencyImpactGraph $graph) {}

    public function analyze(string $failingComponent): array
    {
        $impacted     = $this->graph->blastRadius($failingComponent);
        $score        = count($impacted);
        $riskLevel    = $this->computeRiskLevel($score, $failingComponent);

        return [
            'failing_component'  => $failingComponent,
            'impacted_components' => $impacted,
            'blast_radius_score' => $score,
            'risk_level'         => $riskLevel,
            'is_critical'        => $this->graph->isCriticalComponent($failingComponent),
            'containment_order'  => $this->containmentOrder($impacted),
        ];
    }

    public function analyzeMultiple(array $failingComponents): array
    {
        $allImpacted = [];

        foreach ($failingComponents as $component) {
            $allImpacted = array_merge($allImpacted, $this->graph->blastRadius($component));
        }

        $allImpacted = array_unique($allImpacted);
        $allImpacted = array_diff($allImpacted, $failingComponents);

        return [
            'failing_components' => $failingComponents,
            'total_impacted'     => count($allImpacted),
            'impacted'           => array_values($allImpacted),
            'risk_level'         => $this->computeRiskLevel(count($allImpacted), implode(',', $failingComponents)),
        ];
    }

    private function computeRiskLevel(int $blastScore, string $component): string
    {
        if ($blastScore >= 5 || $this->graph->isCriticalComponent($component)) {
            return 'critical';
        }

        if ($blastScore >= 2) {
            return 'high';
        }

        if ($blastScore >= 1) {
            return 'medium';
        }

        return 'low';
    }

    private function containmentOrder(array $impacted): array
    {
        // Leaf nodes (no downstream deps) should be contained first
        return usort($impacted, fn($a, $b) =>
            count($this->graph->downstreamOf($a)) <=> count($this->graph->downstreamOf($b))
        ) ? $impacted : $impacted;
    }
}
