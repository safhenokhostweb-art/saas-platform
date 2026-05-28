<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\ResponsiveExperienceValidationService;

class ValidateResponsiveCommand extends Command
{
    protected $signature = 'frontend:validate-responsive
                            {--json   : Output as JSON}
                            {--strict : Fail on HIGH as well as CRITICAL}';

    protected $description = 'Validate responsive design and mobile experience governance across all modules';

    public function handle(ResponsiveExperienceValidationService $service): int
    {
        if (! $this->option('json')) {
            $this->line('');
            $this->line('<fg=cyan;options=bold>╔══════════════════════════════════════════════════════╗</>');
            $this->line('<fg=cyan;options=bold>║    Responsive Experience Governance Validation      ║</>');
            $this->line('<fg=cyan;options=bold>╚══════════════════════════════════════════════════════╝</>');
            $this->line('');
        }

        $result  = $service->validate();
        $strict  = (bool) $this->option('strict');

        $critical = count(array_filter($result['violations'], fn ($v) => $v['severity'] === 'CRITICAL'));
        $high     = count(array_filter($result['violations'], fn ($v) => $v['severity'] === 'HIGH'));
        $passed   = $critical === 0 && (! $strict || $high === 0);

        if ($this->option('json')) {
            $this->line(json_encode(array_merge($result, ['passed' => $passed]), JSON_PRETTY_PRINT));
            return $passed ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Check', 'Value'],
            [
                ['Files Scanned', $result['files_scanned']],
                ['Total Violations', count($result['violations'])],
                ['CRITICAL', '<fg=red>' . $critical . '</>'],
                ['HIGH', '<fg=yellow>' . $high . '</>'],
                ['Score', '<options=bold>' . $result['score'] . '/100</>'],
            ]
        );

        foreach (['CRITICAL', 'HIGH', 'MEDIUM'] as $sev) {
            $group = array_filter($result['violations'], fn ($v) => $v['severity'] === $sev);
            if (empty($group)) continue;

            $color = match($sev) { 'CRITICAL' => 'red', 'HIGH' => 'yellow', default => 'blue' };
            $this->line('');
            $this->line("<fg={$color};options=bold>── {$sev} ──────────────────────────────</>");
            foreach ($group as $v) {
                $this->line("   <fg={$color}>•</> [{$v['module']}] {$v['description']}");
                $this->line("     <fg=gray>→ {$v['file']}</>");
            }
        }

        if (! empty($result['recommendations'])) {
            $this->line('');
            $this->line('<fg=blue;options=bold>── Recommendations ───────────────────────────────────</>');
            foreach ($result['recommendations'] as $rec) {
                $this->line("   • {$rec}");
            }
        }

        $this->line('');
        $this->line($passed
            ? '<fg=green;options=bold>✓ Responsive governance validation passed!</>'
            : '<fg=red;options=bold>✗ Responsive governance validation failed.</>');
        $this->line('<options=bold>' . $result['summary'] . '</>');
        $this->line('');

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
