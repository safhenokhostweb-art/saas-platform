<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsVisualParityEngine;

/**
 * Phase 10.2.6 — Forms Visual Parity Validation Command
 */
class ValidateFormsVisualParityCommand extends Command
{
    protected $signature = 'forms:validate-visual-parity
                            {--strict : Require 95+ score AND zero critical failures}
                            {--json : Output results as JSON}
                            {--fail-below= : Fail if score is below this threshold (default 95)}';

    protected $description = 'Phase 10.2.6 — Deep structural visual parity check between saas-platform and customer-site Forms module';

    public function handle(FormsVisualParityEngine $engine): int
    {
        $strict     = (bool) $this->option('strict');
        $jsonOutput = (bool) $this->option('json');
        $threshold  = (int) ($this->option('fail-below') ?? 95);

        $result = $engine->run();

        if ($jsonOutput) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['parity'] ? Command::SUCCESS : Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Forms Visual Parity Engine — Phase 10.2.6</>');
        $this->line('  <fg=gray>Structural identity check: saas-platform ↔ customer-site</>');
        $this->newLine();

        foreach ($result['checks'] as $key => $check) {
            $icon = $check['pass']
                ? '<fg=green>✓</>'
                : ($check['severity'] === 'critical' ? '<fg=red>✗</>' : '<fg=yellow>⚠</>');

            $label = str_pad(str_replace('_', ' ', $key), 36);
            $this->line("  {$icon}  {$label}  <fg=gray>{$check['detail']}</>");
        }

        $this->newLine();

        $score  = $result['score'];
        $grade  = $result['grade'];
        $color  = $score >= 95 ? 'green' : ($score >= 83 ? 'yellow' : 'red');

        $this->line("  Score: <fg={$color};options=bold>{$score}/100  Grade {$grade}</>  ({$result['passed']}/{$result['total']} passed | {$result['critical']} critical | {$result['warnings']} warnings)");

        $fail = ($strict && ! $result['parity']) || $score < $threshold;

        if ($fail) {
            $this->newLine();
            if ($result['critical'] > 0) {
                $this->line("  <fg=red;options=bold>FAILED — {$result['critical']} critical parity violation(s)</>");
            } else {
                $this->line("  <fg=red;options=bold>FAILED — score {$score} is below threshold {$threshold}</>");
            }
            $this->line('  <fg=gray>Run php artisan forms:recover-builder-runtime to auto-repair recoverable issues</>');
            $this->newLine();
            return Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓  Visual parity confirmed — saas-platform and customer-site are structurally identical</>');
        $this->newLine();
        return Command::SUCCESS;
    }
}
