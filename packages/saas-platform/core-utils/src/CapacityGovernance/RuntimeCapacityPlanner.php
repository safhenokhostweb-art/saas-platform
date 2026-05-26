<?php

namespace SaaSPlatform\CoreUtils\CapacityGovernance;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\Metrics\OperationalMetricsAggregator;

class RuntimeCapacityPlanner
{
    public function __construct(
        private CapacityForecastEngine      $forecast,
        private RuntimePressurePredictor    $predictor,
        private RuntimeScalingAdvisor       $advisor,
        private CapacityThresholdManager    $thresholds,
        private OperationalMetricsAggregator $metrics,
        private TelemetryManager            $telemetry
    ) {}

    public function plan(int $forecastHours = 24): array
    {
        $pressure      = $this->predictor->predict();
        $queueForecast = $this->forecast->forecastQueueSaturation($forecastHours);
        $storageForecast = $this->forecast->forecastStorageGrowth(30);
        $advisor       = $this->advisor->recommend();
        $readiness     = $this->advisor->scalabilityReadiness();

        if ($pressure['pressure_level'] === 'critical') {
            $this->telemetry->error('capacity', 'capacity.planner.critical_pressure', [
                'forecast_hours' => $forecastHours,
                'pressure'       => $pressure,
            ]);
        }

        return [
            'planned_at'     => now()->toIso8601String(),
            'forecast_hours' => $forecastHours,
            'pressure'       => $pressure,
            'queue'          => $queueForecast,
            'storage'        => $storageForecast,
            'advisor'        => $advisor,
            'readiness'      => $readiness,
            'thresholds'     => $this->thresholds->all(),
        ];
    }

    public function expansionPlan(int $additionalTenants): array
    {
        $impact = $this->forecast->forecastTenantExpansionImpact($additionalTenants);
        $readiness = $this->advisor->scalabilityReadiness();

        return [
            'additional_tenants'  => $additionalTenants,
            'impact'              => $impact,
            'current_readiness'   => $readiness,
            'safe_to_expand'      => $readiness['readiness_score'] >= 70 && $impact['density_level'] !== 'critical',
            'expansion_risk'      => $impact['density_level'],
        ];
    }

    public function capacityReport(): array
    {
        try {
            $ops = $this->metrics->aggregate(24);
        } catch (\Throwable) {
            $ops = ['health_score' => ['score' => null, 'grade' => null]];
        }

        return [
            'report_at'        => now()->toIso8601String(),
            'operational'      => $ops,
            'capacity_plan'    => $this->plan(4),
            'scaling_advice'   => $this->advisor->recommend(),
        ];
    }
}
