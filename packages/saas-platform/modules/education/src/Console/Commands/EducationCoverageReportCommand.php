<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Services\EducationCoverageTracker;

class EducationCoverageReportCommand extends Command
{
    protected $signature   = 'education:coverage {--json : Output as JSON}';
    protected $description = 'Report education documentation coverage across platform modules';

    public function handle(EducationCoverageTracker $tracker): int
    {
        $report = $tracker->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('Education Coverage Report');
        $this->line('─────────────────────────────────────');
        $this->line("  Total modules:   {$report['total_modules']}");
        $this->line("  Covered:         {$report['covered']}");
        $this->line("  Uncovered:       {$report['uncovered']}");

        $color = $report['coverage_pct'] >= 80 ? 'green' : ($report['coverage_pct'] >= 50 ? 'yellow' : 'red');
        $this->line("  Coverage:        <fg={$color}>{$report['coverage_pct']}%</>");

        if (! empty($report['uncovered_slugs'])) {
            $this->newLine();
            $this->warn('Modules without any published article:');
            foreach ($report['uncovered_slugs'] as $slug) {
                $this->line("  · {$slug}");
            }
        }

        return self::SUCCESS;
    }
}
