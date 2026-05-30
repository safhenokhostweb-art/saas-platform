<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsTailwindParityValidationService;

class ValidateFormsTailwindParityCommand extends Command
{
    protected $signature = 'forms:validate-tailwind-parity
                            {--json : Output raw JSON report}
                            {--strict : Fail on warnings (score < 100)}
                            {--fail-below=95 : Minimum acceptable score (default 95)}';

    protected $description = 'Phase 10.2.8 — Validate Tailwind v4 parity between saas-platform and customer-site for Forms module';

    public function handle(FormsTailwindParityValidationService $service): int
    {
        if (!$this->option('json')) {
            $this->line('');
            $this->line('<fg=cyan;options=bold>Phase 10.2.8 — Forms Tailwind v4 Parity Validation</>');
            $this->line('<fg=gray>Checking Tailwind runtime parity across both projects...</>');
            $this->line('');
        }

        $report = $service->validate();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $report['overall_score'] >= (int) $this->option('fail-below') ? 0 : 1;
        }

        $this->renderReport($report);

        $threshold = (int) $this->option('fail-below');
        $score     = $report['overall_score'];

        if ($score < $threshold) {
            $this->line('');
            $this->error("❌ Parity score {$score}/100 is below threshold {$threshold}/100");
            return 1;
        }

        $this->line('');
        $this->info("✅ Tailwind v4 parity confirmed — score {$score}/100 ({$report['grade']})");
        return 0;
    }

    private function renderReport(array $report): void
    {
        $this->line("<fg=white;options=bold>Overall Score: {$report['overall_score']}/100 ({$report['grade']})</>");
        $this->line("<fg=gray>Passing: {$report['passing']}/{$report['dimensions']} | Failing: {$report['failing']}/{$report['dimensions']}</>");
        $this->line('');

        $this->line('<options=bold>Validation Dimensions:</>');
        foreach ($report['results'] as $dim => $result) {
            $icon    = $result['passing'] ? '✅' : '❌';
            $score   = $result['score'] ?? 0;
            $label   = str_pad(str_replace('_', ' ', ucwords($dim, '_')), 35);
            $msg     = $result['message'] ?? '';
            $this->line("  {$icon} {$label} [{$score}/100] {$msg}");
        }

        if (!empty($report['errors'])) {
            $this->line('');
            $this->line('<fg=red>Errors:</>');
            foreach ($report['errors'] as $err) {
                $this->line("  • {$err}");
            }
        }

        $this->line('');
        $color   = $report['overall_score'] >= 95 ? 'green' : 'yellow';
        $this->line("<fg={$color}>{$report['summary']}</>");
    }
}
