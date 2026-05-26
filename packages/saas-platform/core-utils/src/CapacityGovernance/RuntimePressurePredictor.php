<?php

namespace SaaSPlatform\CoreUtils\CapacityGovernance;

use SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService;
use Illuminate\Support\Facades\Cache;

class RuntimePressurePredictor
{
    private const PREDICTION_WINDOW = 300; // 5 min cache

    public function __construct(
        private CapacityThresholdManager $thresholds,
        private CapacityForecastEngine   $forecast,
        private RuntimeHealthService     $healthService
    ) {}

    public function predict(): array
    {
        return Cache::remember('capacity.pressure.prediction', self::PREDICTION_WINDOW, function () {
            $health        = $this->healthService->check();
            $memUsagePct   = $this->memoryUsagePct();
            $queueForecast = $this->forecast->forecastQueueSaturation(2);

            $pressureFactors = [
                'memory'   => $this->thresholds->level('memory_usage', $memUsagePct),
                'queue'    => $queueForecast['saturation_risk'],
                'health'   => $this->healthLevelFromScore($health['score'] ?? 100),
            ];

            $overallLevel = $this->aggregatePressureLevel($pressureFactors);

            return [
                'pressure_level'   => $overallLevel,
                'pressure_factors' => $pressureFactors,
                'memory_usage_pct' => $memUsagePct,
                'queue_forecast'   => $queueForecast,
                'confidence'       => 'estimated',
                'predicted_at'     => now()->toIso8601String(),
            ];
        });
    }

    public function isSaturating(): bool
    {
        $prediction = $this->predict();
        return in_array($prediction['pressure_level'], ['high', 'critical'], true);
    }

    public function pressureScore(): int
    {
        $prediction = $this->predict();
        return match ($prediction['pressure_level']) {
            'critical' => 90,
            'high'     => 70,
            'elevated' => 50,
            'normal'   => 20,
            default    => 0,
        };
    }

    private function memoryUsagePct(): float
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return 0.0;
        }
        $limitBytes = $this->parseMemoryLimit($limit);
        return $limitBytes > 0
            ? round(memory_get_peak_usage(true) / $limitBytes * 100, 1)
            : 0.0;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $unit  = strtoupper(substr($limit, -1));
        $value = (int) $limit;
        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }

    private function healthLevelFromScore(int $score): string
    {
        return match (true) {
            $score < 40 => 'critical',
            $score < 60 => 'high',
            $score < 80 => 'elevated',
            default     => 'normal',
        };
    }

    private function aggregatePressureLevel(array $factors): string
    {
        $scores = ['normal' => 0, 'warning' => 1, 'elevated' => 1, 'high' => 2, 'critical' => 3];
        $max    = 0;
        foreach ($factors as $level) {
            $max = max($max, $scores[$level] ?? 0);
        }
        return match ($max) {
            3       => 'critical',
            2       => 'high',
            1       => 'elevated',
            default => 'normal',
        };
    }
}
