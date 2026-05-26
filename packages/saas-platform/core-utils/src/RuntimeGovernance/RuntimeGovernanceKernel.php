<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\Anomaly\RuntimeAnomalyEngine;
use SaaSPlatform\CoreUtils\Telemetry\Queue\QueueTelemetryService;
use SaaSPlatform\CoreUtils\Telemetry\Metrics\OperationalMetricsAggregator;
use SaaSPlatform\CoreUtils\Services\Health\RuntimeHealthService;

class RuntimeGovernanceKernel
{
    public function __construct(
        private GovernancePolicyRegistry    $registry,
        private RuntimeDecisionEngine       $decisionEngine,
        private GovernanceExecutionPipeline $pipeline,
        private RuntimeStateCoordinator     $stateCoordinator,
        private GovernanceContext           $context,
        private TelemetryManager            $telemetry,
        private RuntimeAnomalyEngine        $anomalyEngine,
        private QueueTelemetryService       $queueTelemetry,
        private OperationalMetricsAggregator $metricsAggregator,
        private RuntimeHealthService        $healthService
    ) {
        $this->registry->bootstrap();
    }

    public function tick(): array
    {
        $signals   = $this->collectSignals();
        $policies  = $this->registry->enabled();
        $decisions = $this->decisionEngine->evaluate($signals, $policies);

        if (empty($decisions)) {
            return [
                'signals'   => $signals,
                'decisions' => 0,
                'executed'  => null,
            ];
        }

        $execution = $this->pipeline->execute($decisions);

        // Apply cooldowns after execution
        foreach ($decisions as $decision) {
            $policy = $this->registry->get($decision['policy']);
            if ($policy) {
                $this->decisionEngine->markExecuted(
                    $decision['policy'],
                    $policy['cooldown_seconds'] ?? 300
                );
            }
        }

        $this->telemetry->info('governance', 'governance.kernel.tick', [
            'state'          => $this->stateCoordinator->currentState(),
            'decision_count' => count($decisions),
            'execution_id'   => $execution['execution_id'] ?? null,
        ]);

        return [
            'signals'   => $signals,
            'decisions' => count($decisions),
            'executed'  => $execution,
        ];
    }

    public function collectSignals(): array
    {
        $signals = [];

        // Runtime health signals
        try {
            $health = $this->healthService->ready();
            $signals['health'] = [
                'ready'    => $health['status'] === 'ready',
                'checks'   => $health['checks'] ?? [],
            ];
        } catch (\Throwable) {
            $signals['health'] = ['ready' => false];
        }

        // Queue signals
        try {
            $queue = $this->queueTelemetry->snapshot();
            $signals['queue'] = [
                'pending_count' => $queue['pending'] ?? 0,
                'failed_count'  => $queue['failed'] ?? 0,
            ];
        } catch (\Throwable) {
            $signals['queue'] = ['pending_count' => 0, 'failed_count' => 0];
        }

        // Anomaly signals
        try {
            $anomalies = $this->anomalyEngine->scan();
            $signals['anomalies'] = $anomalies;
            // Flatten anomaly codes for policy matching
            foreach ($anomalies as $anomaly) {
                $signals['anomaly']['code'] = $anomaly['code'] ?? '';
            }
        } catch (\Throwable) {
            $signals['anomalies'] = [];
        }

        // Runtime memory signals
        $memoryMb  = memory_get_usage(true) / 1024 / 1024;
        $memAnomaly = $this->anomalyEngine->scanMemory();
        $signals['runtime'] = [
            'memory_mb'       => round($memoryMb, 2),
            'memory_pressure' => $memAnomaly !== null,
        ];

        // Operational health score
        try {
            $healthScore = $this->metricsAggregator->healthScore();
            $signals['platform'] = [
                'health_score' => $healthScore['score'] ?? 100,
                'grade'        => $healthScore['grade'] ?? 'A',
            ];
        } catch (\Throwable) {
            $signals['platform'] = ['health_score' => 100, 'grade' => 'A'];
        }

        $signals['governance_state'] = $this->stateCoordinator->currentState();

        return $signals;
    }

    public function isDeploymentFrozen(): bool
    {
        return (bool) \Illuminate\Support\Facades\Cache::get('governance.deployment.frozen');
    }

    public function unfreezeDeployment(string $reason = 'manual'): void
    {
        \Illuminate\Support\Facades\Cache::forget('governance.deployment.frozen');
        $this->telemetry->info('governance', 'governance.deployment.unfrozen', ['reason' => $reason]);
    }

    public function currentState(): string
    {
        return $this->stateCoordinator->currentState();
    }
}
