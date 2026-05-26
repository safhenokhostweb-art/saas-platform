<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Metrics;

class OperationalMetricsAggregator
{
    public function __construct(
        private RuntimeMetricsRepository    $runtime,
        private DeploymentMetricsRepository $deployment,
        private QueueMetricsRepository      $queue,
        private TenantMetricsRepository     $tenant
    ) {}

    public function aggregate(int $hours = 24): array
    {
        return [
            'runtime'    => $this->runtime->current(),
            'events'     => [
                'by_severity' => $this->runtime->eventCountBySeverity($hours),
                'by_module'   => $this->runtime->eventCountByModule($hours),
                'recent_errors' => $this->runtime->recentErrors(10),
            ],
            'deployment' => $this->deployment->summary(),
            'queue'      => $this->queue->summary(),
            'tenants'    => $this->tenant->maintenanceSummary(),
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $hours,
        ];
    }

    public function healthScore(): array
    {
        $scores = [];

        // Runtime health (0-25)
        $memMb       = round(memory_get_usage(true) / 1024 / 1024, 2);
        $scores['runtime'] = $memMb < 256 ? 25 : ($memMb < 512 ? 15 : 5);

        // Queue health (0-25)
        $queueData = $this->queue->summary();
        $failed    = $queueData['failed'] ?? 0;
        $scores['queue'] = $failed === 0 ? 25 : ($failed < 10 ? 15 : ($failed < 50 ? 5 : 0));

        // Deployment health (0-25)
        $deployData     = $this->deployment->summary();
        $successRate    = $deployData['success_rate'] ?? 100;
        $scores['deployment'] = $successRate >= 95 ? 25 : ($successRate >= 80 ? 15 : 5);

        // Error rate health (0-25)
        $bySeverity = $this->runtime->eventCountBySeverity(1);
        $critical   = ($bySeverity['critical'] ?? 0);
        $scores['errors'] = $critical === 0 ? 25 : ($critical < 5 ? 15 : 5);

        $total = array_sum($scores);
        $grade = match (true) {
            $total >= 95 => 'A+',
            $total >= 85 => 'A',
            $total >= 75 => 'B',
            $total >= 60 => 'C',
            $total >= 40 => 'D',
            default      => 'F',
        };

        return [
            'score'        => $total,
            'grade'        => $grade,
            'dimensions'   => $scores,
            'computed_at'  => now()->toIso8601String(),
        ];
    }
}
