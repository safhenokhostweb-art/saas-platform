<?php

namespace SaaSPlatform\CoreUtils\CapacityGovernance;

class RuntimeScalingAdvisor
{
    public function __construct(
        private RuntimePressurePredictor $predictor,
        private CapacityForecastEngine   $forecast,
        private CapacityThresholdManager $thresholds
    ) {}

    public function recommend(): array
    {
        $prediction = $this->predictor->predict();
        $queueForecast = $this->forecast->forecastQueueSaturation(4);

        $recommendations = [];
        $urgency         = 'none';

        if ($prediction['pressure_level'] === 'critical') {
            $urgency           = 'immediate';
            $recommendations[] = $this->workerScalingAdvice($queueForecast);
            $recommendations[] = ['type' => 'memory', 'action' => 'increase_php_memory_limit', 'urgency' => 'immediate'];
        } elseif ($prediction['pressure_level'] === 'high') {
            $urgency           = 'soon';
            $recommendations[] = $this->workerScalingAdvice($queueForecast);
        } elseif ($prediction['pressure_level'] === 'elevated') {
            $urgency           = 'planned';
            $recommendations[] = ['type' => 'monitoring', 'action' => 'increase_polling_frequency', 'urgency' => 'planned'];
        }

        $storageRec = $this->storageScalingAdvice();
        if ($storageRec) {
            $recommendations[] = $storageRec;
        }

        return [
            'urgency'         => $urgency,
            'pressure_level'  => $prediction['pressure_level'],
            'recommendations' => $recommendations,
            'advised_at'      => now()->toIso8601String(),
        ];
    }

    public function scalabilityReadiness(): array
    {
        $prediction = $this->predictor->predict();
        $pressureScore = $this->predictor->pressureScore();

        $score = max(0, 100 - $pressureScore);

        return [
            'readiness_score' => $score,
            'grade'           => $this->grade($score),
            'pressure_level'  => $prediction['pressure_level'],
            'blockers'        => $this->identifyBlockers($prediction),
        ];
    }

    private function workerScalingAdvice(array $queueForecast): array
    {
        $depth   = $queueForecast['current_depth'];
        $workers = (int) ceil($depth / 50);

        return [
            'type'    => 'queue_workers',
            'action'  => 'scale_workers',
            'current_depth' => $depth,
            'recommended_workers' => max(2, $workers),
            'urgency' => $queueForecast['saturation_risk'] === 'critical' ? 'immediate' : 'soon',
        ];
    }

    private function storageScalingAdvice(): ?array
    {
        $storageForecast = $this->forecast->forecastStorageGrowth(30);
        $projectedGb     = $storageForecast['projected_growth_gb'] ?? 0;

        if ($projectedGb > 10) {
            return [
                'type'           => 'storage',
                'action'         => 'provision_additional_storage',
                'projected_gb_30d' => $projectedGb,
                'urgency'        => $projectedGb > 50 ? 'soon' : 'planned',
            ];
        }

        return null;
    }

    private function identifyBlockers(array $prediction): array
    {
        $blockers = [];

        if (($prediction['pressure_factors']['memory'] ?? 'normal') === 'critical') {
            $blockers[] = 'memory_exhaustion_imminent';
        }

        if (($prediction['pressure_factors']['queue'] ?? 'normal') === 'critical') {
            $blockers[] = 'queue_saturation_imminent';
        }

        return $blockers;
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default      => 'F',
        };
    }
}
