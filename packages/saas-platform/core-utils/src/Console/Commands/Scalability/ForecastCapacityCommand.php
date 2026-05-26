<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Scalability;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Scalability\CapacityValidationReport;
use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimeCapacityPlanner;

class ForecastCapacityCommand extends Command
{
    protected $signature = 'scalability:forecast-capacity
                            {--hours=24       : Forecast horizon in hours}
                            {--tenants=*      : Tenant IDs for storage/cache forecast}
                            {--expand=0       : Simulate adding N additional tenants}
                            {--json           : Output as JSON}';

    protected $description = 'Forecast runtime capacity and identify scaling needs';

    public function handle(
        RuntimeCapacityPlanner  $planner,
        CapacityValidationReport $report
    ): int {
        $hours    = (int) $this->option('hours');
        $tenantIds = array_map('intval', $this->option('tenants') ?: []);
        $expand    = (int) $this->option('expand');

        $plan      = $planner->plan($hours);
        $fullReport = $report->generate($tenantIds);

        if ($expand > 0) {
            $expansionPlan = $planner->expansionPlan($expand);
        }

        if ($this->option('json')) {
            $this->line(json_encode(array_merge($fullReport, [
                'expansion_plan' => $expansionPlan ?? null,
            ]), JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('=== Capacity Forecast (next ' . $hours . 'h) ===');
        $this->line('Overall Score  : ' . $fullReport['overall_score'] . ' / 100 (Grade: ' . $fullReport['grade'] . ')');
        $this->line('Pressure Level : ' . ($plan['pressure']['pressure_level'] ?? 'unknown'));
        $this->newLine();

        $this->info('Dimension Scores:');
        foreach ($fullReport['dimensions'] as $dim => $score) {
            $color = $score >= 70 ? 'green' : ($score >= 50 ? 'yellow' : 'red');
            $this->line(sprintf('  %-18s <fg=%s>%d</>', $dim, $color, $score));
        }

        $this->newLine();
        $this->info('Queue Forecast (4h):');
        $queue = $plan['queue'];
        $this->line('  Current Depth      : ' . ($queue['current_depth'] ?? 0));
        $this->line('  Projected (4h)     : ' . ($queue['projected_depth'] ?? 0));
        $this->line('  Saturation Risk    : ' . ($queue['saturation_risk'] ?? 'unknown'));

        $advisor = $plan['advisor'];
        if (!empty($advisor['recommendations'])) {
            $this->newLine();
            $this->warn('Scaling Recommendations (urgency: ' . $advisor['urgency'] . '):');
            foreach ($advisor['recommendations'] as $rec) {
                $type = $rec['type'] ?? 'general';
                $action = $rec['action'] ?? '';
                $this->line('  [' . strtoupper($type) . '] ' . $action);
            }
        }

        if (!empty($fullReport['blockers'])) {
            $this->newLine();
            $this->error('Blockers:');
            foreach ($fullReport['blockers'] as $blocker) {
                $this->line('  - ' . $blocker);
            }
        }

        if (!empty($expansionPlan)) {
            $this->newLine();
            $this->info('Tenant Expansion (+' . $expand . ' tenants):');
            $this->line('  Safe to Expand : ' . ($expansionPlan['safe_to_expand'] ? 'Yes' : 'No'));
            $this->line('  Expansion Risk : ' . $expansionPlan['expansion_risk']);
        }

        return 0;
    }
}
