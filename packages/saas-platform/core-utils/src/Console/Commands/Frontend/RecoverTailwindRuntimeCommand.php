<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\FrontendRuntimeUnificationRecoveryService;

class RecoverTailwindRuntimeCommand extends Command
{
    protected $signature = 'frontend:recover-tailwind-runtime
                            {--dry-run : Show what would be repaired without making changes}
                            {--json : Output raw JSON report}
                            {--cross-project : Include cross-project drift analysis}
                            {--detect-only : Run detection only, do not attempt repair}';

    protected $description = 'Phase 10.2.8 — Detect and auto-repair Tailwind v4 runtime inconsistencies between saas-platform and customer-site';

    public function handle(FrontendRuntimeUnificationRecoveryService $service): int
    {
        $dryRun       = $this->option('dry-run');
        $detectOnly   = $this->option('detect-only');
        $crossProject = $this->option('cross-project');

        if (!$this->option('json')) {
            $this->line('');
            $this->line('<fg=cyan;options=bold>Phase 10.2.8 — Frontend Runtime Unification Recovery</>');

            if ($dryRun) {
                $this->line('<fg=yellow>Mode: DRY RUN — no changes will be made</>');
            } elseif ($detectOnly) {
                $this->line('<fg=blue>Mode: DETECT ONLY</>');
            }

            $this->line('');
        }

        if ($detectOnly) {
            $result = $service->detect();
        } else {
            $result = $service->recover($dryRun, $crossProject);
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return empty($result['issues']) ? 0 : ($result['status'] === 'repaired' ? 0 : 1);
        }

        $this->renderResult($result, $detectOnly);

        if ($detectOnly) {
            return empty($result['issues']) ? 0 : 1;
        }

        return $result['status'] === 'healthy' || $result['status'] === 'repaired' ? 0 : 1;
    }

    private function renderResult(array $result, bool $detectOnly): void
    {
        if ($detectOnly || isset($result['detected'])) {
            $detected = $result['detected'] ?? count($result['issues'] ?? []);

            if ($detected === 0) {
                $this->info('✅ No Tailwind runtime inconsistencies detected — system is healthy');
                return;
            }

            $this->warn("⚠️  {$detected} issue(s) detected:");
            $this->line('');

            foreach ($result['issues'] as $issue) {
                $sev   = strtoupper($issue['severity'] ?? 'info');
                $color = match($issue['severity'] ?? 'info') {
                    'critical' => 'red',
                    'high'     => 'yellow',
                    'medium'   => 'blue',
                    default    => 'gray',
                };
                $fixable = ($issue['repairable'] ?? false) ? '🔧 auto-fixable' : '⚙️  manual';
                $this->line("  <fg={$color}>[{$sev}]</> {$issue['description']} ({$fixable})");
                if (!empty($issue['action'])) {
                    $this->line("         → {$issue['action']}");
                }
            }
            return;
        }

        if ($result['status'] === 'healthy') {
            $this->info('✅ ' . $result['message']);
            return;
        }

        $this->line("<fg=white;options=bold>Recovery Status: {$result['status']}</>");
        $this->line("Detected: {$result['detected']} | Repaired: {$result['repaired']} | Skipped: {$result['skipped']}");
        $this->line('');

        if (!empty($result['repaired_items'])) {
            $this->line('<fg=green;options=bold>Repaired:</>');
            foreach ($result['repaired_items'] as $item) {
                $dryTag = ($item['dry_run'] ?? false) ? ' [DRY RUN]' : '';
                $this->line("  ✅{$dryTag} {$item['action']}");
            }
            $this->line('');
        }

        if (!empty($result['skipped_items'])) {
            $this->line('<fg=yellow;options=bold>Requires Manual Action:</>');
            foreach ($result['skipped_items'] as $item) {
                $actionText = $item['action'] ?? $item['reason'];
                $this->line("  ⚙️  {$item['issue']}");
                $this->line("      → {$actionText}");
            }
        }
    }
}
