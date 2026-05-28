<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\FrontendDriftLockdownService;

class FrontendLockdownReportCommand extends Command
{
    protected $signature = 'frontend:lockdown-report
                            {--json   : Output results as JSON}
                            {--strict : Fail on any drift item, not just CRITICAL/HIGH}';

    protected $description = 'Generate a drift lockdown report — detect all frontend governance drift across both repositories';

    public function handle(FrontendDriftLockdownService $service): int
    {
        $strict = (bool) $this->option('strict');
        $json   = (bool) $this->option('json');

        $report = $service->generateLockdownReport($strict);

        if ($json) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $report['passed'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('  <options=bold>Frontend Drift Lockdown Report</>');
        $this->line('  ──────────────────────────────────────────');
        $this->newLine();

        $score = $report['score'];
        $color = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');
        $this->line("  <fg={$color};options=bold>Lockdown Score: {$score}/100</>");
        $this->newLine();

        // Summary by severity
        $sev = $report['by_severity'];
        $this->table(
            ['Category', 'Count'],
            [
                ['Total Drift Items', $report['total_drift']],
                ['CRITICAL',         "<fg=red>{$sev['CRITICAL']}</>"],
                ['HIGH',             "<fg=yellow>{$sev['HIGH']}</>"],
                ['MEDIUM',           $sev['MEDIUM']],
            ]
        );

        // Category breakdown
        if (!empty($report['by_category'])) {
            $this->newLine();
            $this->line('  <fg=cyan>Drift by Category:</>');
            foreach ($report['by_category'] as $cat => $count) {
                if ($count === 0) continue;
                $this->line("  • " . str_replace('_', ' ', $cat) . ": {$count}");
            }
        }

        // Top violations
        $critical = array_filter($report['items'], fn($i) => $i['severity'] === 'CRITICAL');
        $high     = array_filter($report['items'], fn($i) => $i['severity'] === 'HIGH');

        if (!empty($critical)) {
            $this->newLine();
            $this->error('  CRITICAL Drift Items:');
            foreach (array_slice($critical, 0, 10) as $item) {
                $this->line("  ✗ [{$item['category']}] {$item['location']}");
                $this->line("      Issue: {$item['issue']}");
                $this->line("      Fix:   <fg=cyan>{$item['fix']}</>");
                $this->newLine();
            }
        }

        if (!empty($high)) {
            $this->newLine();
            $this->warn('  HIGH Severity Drift Items:');
            foreach (array_slice($high, 0, 5) as $item) {
                $this->line("  ⚠ [{$item['category']}] {$item['location']}: {$item['issue']}");
            }
        }

        $this->newLine();
        $this->line('  ' . $report['summary']);
        $this->newLine();

        if (!$report['passed']) {
            $this->error('  Drift lockdown FAILED. Run php artisan frontend:auto-heal to repair violations.');
            return self::FAILURE;
        }

        $this->line('  <fg=green>✓ No critical/high drift detected. Frontend is locked down.</>');
        return self::SUCCESS;
    }
}
