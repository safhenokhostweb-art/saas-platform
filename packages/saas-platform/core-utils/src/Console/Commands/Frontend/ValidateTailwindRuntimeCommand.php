<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\TailwindRuntimeGovernanceService;

class ValidateTailwindRuntimeCommand extends Command
{
    protected $signature = 'frontend:validate-tailwind-runtime
                            {--json : Output raw JSON report}
                            {--fail-below=95 : Minimum acceptable weighted score}';

    protected $description = 'Phase 10.2.8 — Validate Tailwind v4 runtime parity across saas-platform and customer-site';

    public function handle(TailwindRuntimeGovernanceService $service): int
    {
        if (!$this->option('json')) {
            $this->line('');
            $this->line('<fg=cyan;options=bold>Phase 10.2.8 — Tailwind Runtime Governance Validation</>');
            $this->line('<fg=gray>Auditing Tailwind v4 runtime across both projects...</>');
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

        if (!$report['drift_free']) {
            $this->line('');
            $this->error('❌ TAILWIND RUNTIME DRIFT DETECTED');
            foreach ($report['drifts'] as $drift) {
                $this->line("   • {$drift}");
            }
            return 1;
        }

        if ($score < $threshold) {
            $this->line('');
            $this->error("❌ Score {$score}/100 below threshold {$threshold}/100");
            return 1;
        }

        $this->line('');
        $this->info("✅ {$report['verdict']}");
        return 0;
    }

    private function renderReport(array $report): void
    {
        $scoreColor = $report['overall_score'] >= 95 ? 'green' : ($report['overall_score'] >= 80 ? 'yellow' : 'red');

        $this->line("<fg=white;options=bold>Weighted Score: <fg={$scoreColor}>{$report['overall_score']}/100 ({$report['grade']})</></>");
        $this->line("<fg=gray>Drift-Free: " . ($report['drift_free'] ? '✅ Yes' : '❌ No') . " | Passing: {$report['passing']}/{$report['dimensions']} | Failing: {$report['failing']}/{$report['dimensions']}</>");
        $this->line('');

        $this->line('<options=bold>Governance Dimensions (weighted):</>');
        foreach ($report['results'] as $dim => $result) {
            $icon    = $result['passing'] ? '✅' : '❌';
            $score   = str_pad($result['score'] ?? 0, 3);
            $weight  = $result['weight'] ?? 0;
            $label   = str_pad(str_replace('_', ' ', ucwords($dim, '_')), 30);
            $msg     = $result['message'] ?? '';
            $this->line("  {$icon} {$label} [{$score}/100 × w{$weight}]  {$msg}");
        }

        if (!empty($report['drifts'])) {
            $this->line('');
            $this->line('<fg=red;options=bold>DRIFT DETECTED IN:</>');
            foreach ($report['drifts'] as $drift) {
                $this->line("  ⚠️  {$drift}");
            }
        }

        $this->line('');
        $color = $report['drift_free'] ? 'green' : 'red';
        $this->line("<fg={$color}>{$report['verdict']}</>");
    }
}
