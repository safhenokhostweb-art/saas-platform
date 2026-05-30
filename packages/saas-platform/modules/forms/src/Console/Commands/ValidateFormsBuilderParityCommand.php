<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsBuilderParityValidationService;

/**
 * Phase 10.2.6 — Builder Parity Validation Command
 */
class ValidateFormsBuilderParityCommand extends Command
{
    protected $signature = 'forms:validate-builder-parity
                            {--strict : Require 100% score to pass}
                            {--json : Output results as JSON}
                            {--fail-on-warning : Exit non-zero even on partial failures}';

    protected $description = 'Phase 10.2.6 — Validate Forms Builder runtime parity between saas-platform and customer-site';

    public function handle(FormsBuilderParityValidationService $service): int
    {
        $strict     = (bool) $this->option('strict');
        $jsonOutput = (bool) $this->option('json');

        $result = $service->validate($strict);

        if ($jsonOutput) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? Command::SUCCESS : Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Forms Builder Parity Validation — Phase 10.2.6</>');
        $this->line('  <fg=gray>Checks structural identity between saas-platform and customer-site builder runtime</>');
        $this->newLine();

        foreach ($result['results'] as $key => $check) {
            $icon  = $check['pass'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $label = str_pad(str_replace('_', ' ', $key), 38);
            $msg   = "<fg=gray>{$check['message']}</>";
            $this->line("  {$icon}  {$label}  {$msg}");
        }

        $this->newLine();

        $score  = $result['score'];
        $grade  = $result['grade'];
        $color  = $score >= 98 ? 'green' : ($score >= 88 ? 'yellow' : 'red');
        $thresh = $strict ? 100 : 95;

        $passed = count(array_filter($result['results'], fn ($r) => $r['pass']));
        $total  = count($result['results']);

        $this->line("  Score: <fg={$color};options=bold>{$score}/100  Grade {$grade}</>  ({$passed}/{$total} checks passed)");

        if (! $result['passed']) {
            $this->newLine();
            $this->line("  <fg=red;options=bold>FAILED — score {$score} is below threshold {$thresh}</>");
            $this->line('  <fg=gray>Run php artisan forms:recover-builder-runtime to auto-repair</>');
            $this->newLine();
            return Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓  Builder parity validated successfully</>');
        $this->newLine();
        return Command::SUCCESS;
    }
}
