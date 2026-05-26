<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Governance;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics\GovernanceStatusAggregator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Metrics\RuntimeStabilityIndexCalculator;

class RuntimeStatusCommand extends Command
{
    protected $signature = 'governance:runtime-status {--json : Output as JSON}';

    protected $description = 'Show current autonomous runtime governance status';

    public function handle(
        GovernanceStatusAggregator     $aggregator,
        RuntimeStabilityIndexCalculator $stabilityCalc
    ): int {
        $status    = $aggregator->aggregate();
        $stability = $stabilityCalc->calculate();

        if ($this->option('json')) {
            $this->line(json_encode(array_merge($status, ['stability' => $stability]), JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('=== Runtime Governance Status ===');
        $this->line('Platform State : ' . $status['platform_state']);
        $this->line('Stability Index: ' . $stability['index'] . ' / 100 (Grade: ' . $stability['grade'] . ')');
        $this->line('Trend          : ' . $stability['trend']);
        $this->newLine();

        $health = $status['health'];
        $this->info('Health:');
        $this->line('  Overall Score    : ' . $health['overall_score']);
        $this->line('  Circuit Score    : ' . $health['circuit_score']);
        $this->line('  Active Incidents : ' . $health['active_incidents']);
        $this->line('  Has Critical     : ' . ($health['has_critical'] ? 'YES' : 'No'));
        $this->line('  Throttle Active  : ' . ($health['throttle_active'] ? 'YES' : 'No'));
        $this->line('  Deployment Frozen: ' . ($health['deployment_frozen'] ? 'YES' : 'No'));
        $this->newLine();

        $gov = $status['governance'];
        $this->info('Governance (24h):');
        $this->line('  State Transitions : ' . $gov['state_transitions_24h']);
        $this->line('  Recovery Actions  : ' . $gov['recovery_actions_24h']);
        $this->line('  Recovery Rate     : ' . ($gov['recovery_success_rate'] !== null ? $gov['recovery_success_rate'] . '%' : 'N/A'));
        $this->line('  Incidents         : ' . $gov['incidents_24h']);
        $this->newLine();

        if (!empty($status['alerts'])) {
            $this->info('Active Alerts:');
            foreach ($status['alerts'] as $alert) {
                $color = match ($alert['level']) {
                    'emergency', 'critical' => 'red',
                    'warning'               => 'yellow',
                    default                 => 'white',
                };
                $this->line("  <fg={$color}>[{$alert['level']}] {$alert['message']}</>");
            }
        } else {
            $this->line('<fg=green>No active alerts.</>');
        }

        return 0;
    }
}
