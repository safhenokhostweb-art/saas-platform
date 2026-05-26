<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Scalability;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Scalability\RuntimeLoadSimulationEngine;

class SimulateLoadCommand extends Command
{
    protected $signature = 'scalability:simulate-load
                            {--tenants=* : Tenant IDs to include in simulation}
                            {--pending=0 : Simulated pending job count}
                            {--failed=0  : Simulated failed job count}
                            {--json      : Output as JSON}';

    protected $description = 'Simulate platform load and report scalability scores';

    public function handle(RuntimeLoadSimulationEngine $engine): int
    {
        $tenantIds = array_map('intval', $this->option('tenants') ?: []);

        $options = [
            'queue' => [
                'pending_jobs' => (int) $this->option('pending'),
                'failed_jobs'  => (int) $this->option('failed'),
            ],
        ];

        $result = $engine->runFullSimulation($tenantIds, $options);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('=== Load Simulation Results ===');
        $this->line('Overall Score : ' . $result['overall_score'] . ' / 100 (Grade: ' . $result['grade'] . ')');
        $this->line('Tenant Count  : ' . $result['tenant_count']);
        $this->newLine();

        $this->info('Scores:');
        $this->line('  Queue Score    : ' . $result['queue_score']);
        $this->line('  Capacity Score : ' . $result['capacity_score']);
        $this->line('  Cluster Score  : ' . $result['cluster_score']);
        $this->line('  Fairness Score : ' . $result['fairness_score']);
        $this->newLine();

        $pressure = $result['queue_simulation']['pressure_level'] ?? 'unknown';
        $this->line('Queue Pressure : ' . $pressure);

        if (!empty($result['queue_simulation']['recommendations'])) {
            $this->newLine();
            $this->warn('Recommendations:');
            foreach ($result['queue_simulation']['recommendations'] as $rec) {
                $this->line('  - ' . $rec);
            }
        }

        return 0;
    }
}
