<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\FrontendComponentGovernanceService;

class ValidateComponentsCommand extends Command
{
    protected $signature = 'frontend:validate-components
                            {--strict  : Fail on HIGH severity violations as well as CRITICAL}
                            {--json    : Output results as JSON}
                            {--module= : Limit scan to a specific module (forms|tickets|layouts)}';

    protected $description = 'Validate component governance — detect raw HTML elements and non-canonical UI usage across all modules';

    public function handle(FrontendComponentGovernanceService $service): int
    {
        if (! $this->option('json')) {
            $this->line('');
            $this->line('<fg=cyan;options=bold>╔══════════════════════════════════════════════════════╗</>');
            $this->line('<fg=cyan;options=bold>║   Frontend Component Governance Validation v10.1    ║</>');
            $this->line('<fg=cyan;options=bold>╚══════════════════════════════════════════════════════╝</>');
            $this->line('');
        }

        $strict  = (bool) $this->option('strict');
        $result  = $service->validate($strict);
        $module  = $this->option('module');

        // Filter by module if specified
        if ($module) {
            $result['findings'] = array_filter($result['findings'], fn ($f) => str_contains($f['module'], $module));
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        // Stats table
        $this->table(
            ['Metric', 'Value'],
            [
                ['Files Scanned', $result['stats']['files_scanned']],
                ['Total Violations', $result['stats']['total_violations']],
                ['CRITICAL', '<fg=red>' . $result['stats']['critical'] . '</>'],
                ['HIGH', '<fg=yellow>' . $result['stats']['high'] . '</>'],
                ['MEDIUM', '<fg=blue>' . $result['stats']['medium'] . '</>'],
                ['LOW', $result['stats']['low']],
                ['Governance Score', '<options=bold>' . $result['score'] . '/100</>'],
            ]
        );

        // Show findings grouped by severity
        $findings = collect($result['findings']);

        foreach (['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'] as $sev) {
            $group = $findings->where('severity', $sev);
            if ($group->isEmpty()) continue;

            $color = match($sev) {
                'CRITICAL' => 'red',
                'HIGH'     => 'yellow',
                'MEDIUM'   => 'blue',
                default    => 'white',
            };

            $this->line('');
            $this->line("<fg={$color};options=bold>── {$sev} ({$group->count()}) ──────────────────────────────</>");

            foreach ($group->take(20) as $f) {
                $rel = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $f['file']);
                $this->line("   <fg={$color}>•</> [{$f['module']}] {$f['message']}");
                $this->line("     <fg=gray>→ {$rel}" . ($f['line'] > 0 ? ":{$f['line']}" : '') . "</>");
            }

            if ($group->count() > 20) {
                $this->line("   <fg=gray>... and " . ($group->count() - 20) . " more</>");
            }
        }

        $this->line('');
        if ($result['passed']) {
            $this->line('<fg=green;options=bold>✓ Component governance validation passed!</>');
        } else {
            $this->line('<fg=red;options=bold>✗ Component governance validation failed — remediation required.</>');
        }
        $this->line('<options=bold>' . $result['summary'] . '</>');
        $this->line('');

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
