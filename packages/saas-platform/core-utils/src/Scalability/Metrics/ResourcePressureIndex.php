<?php

namespace SaaSPlatform\CoreUtils\Scalability\Metrics;

use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimePressurePredictor;
use SaaSPlatform\CoreUtils\StorageGovernance\StoragePressureAnalyzer;
use SaaSPlatform\CoreUtils\CacheGovernance\TenantCachePressureMonitor;

class ResourcePressureIndex
{
    public function __construct(
        private RuntimePressurePredictor   $pressurePredictor,
        private StoragePressureAnalyzer    $storagePressure,
        private TenantCachePressureMonitor $cachePressure,
        private QueueScalabilityMetrics    $queueMetrics,
        private TenantIsolationMetrics     $isolationMetrics
    ) {}

    public function calculate(array $tenantIds = []): array
    {
        $runtimeScore  = 100 - $this->pressurePredictor->pressureScore();
        $queueScore    = 100 - $this->queueMetrics->saturationIndex();
        $storageLevel  = $this->storagePressure->platformStoragePressure($tenantIds);
        $cacheLevel    = $this->cachePressure->platformCachePressure($tenantIds);
        $isolationData = $this->isolationMetrics->collect($tenantIds);

        $storageScore  = $this->levelToScore($storageLevel);
        $cacheScore    = $this->levelToScore($cacheLevel);
        $fairnessScore = $isolationData['fairness_score'] ?? 100;

        $overallIndex = (int) round(
            ($runtimeScore * 0.25) +
            ($queueScore   * 0.25) +
            ($storageScore * 0.15) +
            ($cacheScore   * 0.15) +
            ($fairnessScore * 0.20)
        );

        return [
            'index'         => $overallIndex,
            'grade'         => $this->grade($overallIndex),
            'components'    => [
                'runtime'  => $runtimeScore,
                'queue'    => $queueScore,
                'storage'  => $storageScore,
                'cache'    => $cacheScore,
                'fairness' => $fairnessScore,
            ],
            'pressure_levels' => [
                'runtime'  => $this->pressurePredictor->predict()['pressure_level'],
                'queue'    => $this->queueMetrics->collect()['saturation_risk'],
                'storage'  => $storageLevel,
                'cache'    => $cacheLevel,
            ],
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    private function levelToScore(string $level): int
    {
        return match ($level) {
            'normal'   => 100,
            'elevated' => 75,
            'warning'  => 55,
            'high'     => 35,
            'critical' => 10,
            default    => 70,
        };
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
