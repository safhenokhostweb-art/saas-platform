<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsFrontendParityValidationService;

class ValidateFormsFrontendParityCommand extends Command
{
    protected $signature = 'forms:validate-frontend-parity
                            {--json : Output results as JSON}
                            {--strict : Require 95+ score to pass}
                            {--fail-on-warning : Treat warnings as failures}';

    protected $description = 'Phase 9.1 — Validate Forms module frontend parity between platform and tenant contexts';

    public function handle(FormsFrontendParityValidationService $service): int
    {
        $strict        = (bool) $this->option('strict');
        $jsonOutput    = (bool) $this->option('json');
        $failOnWarning = (bool) $this->option('fail-on-warning');

        $result = $service->validate($strict);

        if ($jsonOutput) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['pass'] ? Command::SUCCESS : Command::FAILURE;
        }

        // ── Header ──────────────────────────────────────────────────────────
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Forms Frontend Parity Validation — Phase 9.1</>');
        $this->line('  <fg=gray>Canonical authority: packages/saas-platform/modules/forms/resources/views/</>');
        $this->newLine();

        // ── Individual checks ────────────────────────────────────────────────
        foreach ($result['results'] as $check) {
            $icon = match ($check['status']) {
                'pass' => '<fg=green>✓</>',
                'warn' => '<fg=yellow>⚠</>',
                'fail' => '<fg=red>✗</>',
                default => '·',
            };

            $name   = str_pad(str_replace('_', ' ', $check['check']), 40);
            $detail = $check['detail'] ? "  <fg=gray>{$check['detail']}</>" : '';

            $this->line("  {$icon}  {$name}{$detail}");
        }

        $this->newLine();

        // ── Warnings ────────────────────────────────────────────────────────
        if (! empty($result['warnings'])) {
            $this->line('  <fg=yellow;options=bold>Warnings:</>');
            foreach ($result['warnings'] as $w) {
                $this->line("  <fg=yellow>  ⚠  {$w}</>");
            }
            $this->newLine();
        }

        // ── Failures ─────────────────────────────────────────────────────────
        if (! empty($result['failures'])) {
            $this->line('  <fg=red;options=bold>Failures:</>');
            foreach ($result['failures'] as $f) {
                $this->line("  <fg=red>  ✗  {$f}</>");
            }
            $this->newLine();
        }

        // ── Score ─────────────────────────────────────────────────────────
        $score     = $result['score'];
        $threshold = $strict ? 95 : 80;
        $color     = $score >= 95 ? 'green' : ($score >= 80 ? 'yellow' : 'red');
        $grade     = $score >= 95 ? 'A+' : ($score >= 85 ? 'A' : ($score >= 75 ? 'B' : 'C'));

        $this->line("  Score: <fg={$color};options=bold>{$score}/100 (Grade {$grade})</>");
        $this->line("  Checks: {$result['passed']}/{$result['checks']} passed");

        if ($failOnWarning && ! empty($result['warnings'])) {
            $this->newLine();
            $this->line('  <fg=red>FAILED — warnings treated as failures (--fail-on-warning)</>');
            return Command::FAILURE;
        }

        if (! $result['pass']) {
            $this->newLine();
            $this->line("  <fg=red>FAILED — score {$score} is below threshold {$threshold}</>  (use --strict for 95 threshold)");
            return Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓  Frontend parity validated successfully</>');
        $this->newLine();

        return Command::SUCCESS;
    }
}
