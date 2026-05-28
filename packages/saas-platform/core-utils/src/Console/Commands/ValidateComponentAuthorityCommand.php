<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\CanonicalComponentAuthorityService;

class ValidateComponentAuthorityCommand extends Command
{
    protected $signature = 'frontend:validate-component-authority
                            {--json            : Output results as JSON}
                            {--strict          : Treat warnings as violations}
                            {--fail-on-warning : Exit non-zero if warnings exist}
                            {--fail-on-drift   : Exit non-zero on any score < 95}';

    protected $description = 'Validate canonical component authority — detect raw controls, inline styles, hardcoded colors';

    public function handle(CanonicalComponentAuthorityService $service): int
    {
        $strict = $this->option('strict') || $this->option('fail-on-warning');
        $result = $service->validate($strict);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $this->exitCode($result, $strict);
        }

        $score = $result['score'];
        $grade = $result['grade'];
        $color = $score >= 95 ? 'green' : ($score >= 80 ? 'yellow' : 'red');

        $this->newLine();
        $this->line("  <fg={$color};options=bold>Component Authority Score: {$score}/100 (Grade {$grade})</>");
        $this->newLine();

        $summary = $result['summary'];
        $this->table(
            ['Check', 'Count', 'Status'],
            [
                ['Raw control violations',    $summary['raw_control_violations'],    $summary['raw_control_violations']    === 0 ? '<fg=green>✓ Pass</>' : '<fg=red>✗ Fail</>'],
                ['Hardcoded color violations', $summary['hardcoded_color_violations'], $summary['hardcoded_color_violations'] === 0 ? '<fg=green>✓ Pass</>' : '<fg=red>✗ Fail</>'],
                ['Inline style violations',   $summary['inline_style_violations'],   $summary['inline_style_violations']   === 0 ? '<fg=green>✓ Pass</>' : '<fg=red>✗ Fail</>'],
                ['Forbidden Tailwind warns',  $summary['forbidden_tw_violations'],   $summary['forbidden_tw_violations']   === 0 ? '<fg=green>✓ Pass</>' : '<fg=yellow>⚠ Warn</>'],
                ['Total violations',          $summary['total_violations'],          $summary['total_violations']          === 0 ? '<fg=green>✓ Clean</>' : '<fg=red>✗ ' . $summary['total_violations'] . ' issues</>'],
            ]
        );

        if (!empty($result['violations'])) {
            $this->newLine();
            $this->error('  Violations:');
            foreach (array_slice($result['violations'], 0, 20) as $v) {
                $this->line("  <fg=red>[{$v['category']}]</> {$v['file']}:{$v['line']} — {$v['message']}");
                if (!empty($v['snippet'])) {
                    $this->line("    <fg=gray>» " . substr($v['snippet'], 0, 90) . "</>");
                }
            }
            if (count($result['violations']) > 20) {
                $this->line("  <fg=yellow>... and " . (count($result['violations']) - 20) . " more. Use --json for full list.</>");
            }
        }

        $this->newLine();

        return $this->exitCode($result, $strict);
    }

    private function exitCode(array $result, bool $strict): int
    {
        if ($this->option('fail-on-drift') && $result['score'] < 95) return self::FAILURE;
        if ($strict && $result['summary']['total_violations'] > 0) return self::FAILURE;
        if ($result['summary']['raw_control_violations'] > 0) return self::FAILURE;
        if ($result['summary']['hardcoded_color_violations'] > 0) return self::FAILURE;
        if ($result['summary']['inline_style_violations'] > 0) return self::FAILURE;
        return self::SUCCESS;
    }
}
