<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\ResponsiveGovernanceValidationService;

class ValidateResponsiveGovernanceCommand extends Command
{
    protected $signature = 'frontend:validate-responsive-governance
                            {--json   : Output results as JSON}
                            {--strict : Treat warnings as failures}';

    protected $description = 'Validate responsive governance — touch targets, table wrappers, RTL, breakpoints';

    public function handle(ResponsiveGovernanceValidationService $service): int
    {
        $strict = $this->option('strict');
        $result = $service->validate($strict);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $result['score'] >= 85 ? self::SUCCESS : self::FAILURE;
        }

        $score = $result['score'];
        $grade = $result['grade'];
        $color = $score >= 95 ? 'green' : ($score >= 80 ? 'yellow' : 'red');

        $this->newLine();
        $this->line("  <fg={$color};options=bold>Responsive Governance Score: {$score}/100 (Grade {$grade})</>");
        $this->newLine();

        $d = $result['summary'];
        $this->table(
            ['Dimension', 'Result', 'Status'],
            [
                ['Touch target issues',     $d['touch_target_issues'],  $d['touch_target_issues']  === 0 ? '<fg=green>✓ Pass</>' : '<fg=yellow>⚠ Warn</>'],
                ['Table wrapper issues',    $d['table_wrapper_issues'], $d['table_wrapper_issues'] === 0 ? '<fg=green>✓ Pass</>' : '<fg=yellow>⚠ Warn</>'],
                ['RTL logical prop issues', $d['rtl_violations'],       $d['rtl_violations']       === 0 ? '<fg=green>✓ Pass</>' : '<fg=yellow>⚠ Warn</>'],
                ['prefers-reduced-motion',  $d['reduced_motion_ok'] ? 'yes' : 'no', $d['reduced_motion_ok'] ? '<fg=green>✓ Pass</>' : '<fg=red>✗ Fail</>'],
                ['Breakpoint coverage',     ($result['dimensions']['breakpoint_coverage'] ?? 100) . '%', ($result['dimensions']['breakpoint_coverage'] ?? 100) >= 80 ? '<fg=green>✓ Pass</>' : '<fg=yellow>⚠ Warn</>'],
            ]
        );

        if (!empty($result['violations'])) {
            $this->newLine();
            $this->error('  Violations (must fix):');
            foreach ($result['violations'] as $v) {
                $this->line("  <fg=red>[{$v['dimension']}]</> {$v['message']}");
            }
        }

        if (!empty($result['warnings']) && $strict) {
            $this->newLine();
            $this->warn('  Warnings (strict mode — treated as failures):');
            foreach (array_slice($result['warnings'], 0, 10) as $w) {
                $this->line("  <fg=yellow>[{$w['dimension']}]</> {$w['message']}");
            }
        }

        $this->newLine();

        if ($strict) return $result['score'] >= 95 ? self::SUCCESS : self::FAILURE;
        return count($result['violations']) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
