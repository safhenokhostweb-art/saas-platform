<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\FrontendDriftAutoRecoveryService;
use SaaSPlatform\CoreUtils\Frontend\FrontendCrossRepositoryGovernanceValidator;
use SaaSPlatform\CoreUtils\Frontend\FrontendDriftLockdownService;

/**
 * Phase 10.2.5 — Task 6 enhancement
 * Enhanced auto-heal with cross-project repair, namespace auto-repair,
 * missing token injection, canonical component suggestions, CI repair
 * hints, and drift repair recommendations.
 */
class FrontendAutoHealCommand extends Command
{
    protected $signature = 'frontend:auto-heal
                            {--dry-run         : Show what would be repaired without making changes}
                            {--json            : Output results as JSON}
                            {--cross-project   : Include cross-project governance repair analysis}
                            {--suggest-ci      : Show CI repair hints for failing checks}';

    protected $description = 'Auto-heal frontend drift — detect and repair governance violations across both repositories';

    public function handle(
        FrontendDriftAutoRecoveryService $service,
        FrontendCrossRepositoryGovernanceValidator $crossValidator,
        FrontendDriftLockdownService $lockdownService
    ): int {
        $dryRun       = (bool) $this->option('dry-run');
        $json         = (bool) $this->option('json');
        $crossProject = (bool) $this->option('cross-project');
        $suggestCi    = (bool) $this->option('suggest-ci');

        if ($dryRun && !$json) {
            $this->line('  <fg=yellow>DRY RUN — no changes will be made</>');
            $this->newLine();
        }

        // Core heal / diagnose
        $result = $dryRun ? $service->diagnose() : $service->heal(false);

        // Cross-project analysis
        $crossResult   = null;
        $lockdownReport = null;
        if ($crossProject || $dryRun) {
            $crossResult    = $crossValidator->validate(false, false);
            $lockdownReport = $lockdownService->generateLockdownReport(false);
        }

        if ($json) {
            $output = $result;
            if ($crossResult)    $output['cross_project']     = $crossResult;
            if ($lockdownReport) $output['lockdown_report']   = $lockdownReport;
            if ($suggestCi)      $output['ci_repair_hints']   = $this->buildCiRepairHints($crossResult, $lockdownReport);
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        if ($dryRun) {
            $issues = $result['issues'] ?? [];
            $health = $result['health_score'] ?? 100;
            $color  = $health >= 90 ? 'green' : ($health >= 70 ? 'yellow' : 'red');

            $this->line("  <fg={$color};options=bold>Frontend Health Score: {$health}/100</>");
            $this->newLine();

            if (empty($issues)) {
                $this->line('  <fg=green>✓ No local drift issues detected.</>');
            } else {
                $this->warn('  Issues that would be repaired:');
                $rows = [];
                foreach ($issues as $issue) {
                    $rows[] = [
                        $issue['type']        ?? '—',
                        $issue['severity']    ?? '—',
                        substr($issue['description'] ?? '', 0, 60),
                        $issue['repair']      ?? 'manual',
                    ];
                }
                $this->table(['Type', 'Severity', 'Description', 'Repair'], $rows);
            }

            // Cross-project section
            if ($crossResult) {
                $this->newLine();
                $this->line('  <fg=cyan;options=bold>Cross-Repository Governance:</>');
                $csScore = $crossResult['score'];
                $csColor = $csScore >= 90 ? 'green' : ($csScore >= 70 ? 'yellow' : 'red');
                $this->line("  <fg={$csColor}>Cross-repo score: {$csScore}/100 (Grade {$crossResult['grade']})</>");

                if (!empty($crossResult['violations'])) {
                    $this->newLine();
                    $this->warn('  Cross-Repository Violations:');
                    foreach (array_slice($crossResult['violations'], 0, 5) as $v) {
                        $this->line("  ✗ [{$v['dimension']}] {$v['message']}");
                    }
                }
            }

            // Lockdown section
            if ($lockdownReport && $lockdownReport['total_drift'] > 0) {
                $this->newLine();
                $this->line('  <fg=cyan;options=bold>Drift Lockdown Status:</>');
                $bySev = $lockdownReport['by_severity'];
                $this->line("  Total drift items: {$lockdownReport['total_drift']} (CRITICAL: {$bySev['CRITICAL']}, HIGH: {$bySev['HIGH']})");

                // Canonical replacement suggestions
                $this->newLine();
                $this->line('  <fg=cyan>Canonical Replacement Suggestions:</>');
                $suggestions = $this->buildReplacementSuggestions($lockdownReport['items'] ?? []);
                foreach ($suggestions as $raw => $canonical) {
                    $this->line("  → Replace {$raw} with {$canonical}");
                }
            }

            // CI repair hints
            if ($suggestCi || !empty($crossResult['violations'])) {
                $hints = $this->buildCiRepairHints($crossResult, $lockdownReport);
                if (!empty($hints)) {
                    $this->newLine();
                    $this->line('  <fg=cyan>CI Repair Hints:</>');
                    foreach ($hints as $hint) {
                        $this->line("  {$hint}");
                    }
                }
            }

            if (!empty($result['action_needed']) || ($crossResult && !$crossResult['passed'])) {
                $this->newLine();
                $this->line('  Run <fg=cyan>php artisan frontend:auto-heal --cross-project</> to apply all repairs.');
            }

            return self::SUCCESS;
        }

        // Full heal mode
        $repaired = $result['repaired'] ?? 0;
        $skipped  = $result['skipped']  ?? 0;
        $total    = $result['total_issues'] ?? 0;
        $health   = $result['health_score'] ?? 100;

        $this->newLine();
        $this->line('  <fg=green;options=bold>Auto-heal complete</>');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Issues found',    $total],
                ['Repaired',        $repaired],
                ['Requires manual', $skipped],
                ['Health score',    $health . '/100'],
            ]
        );

        // Cross-project summary
        if ($crossResult && !$crossResult['passed']) {
            $this->newLine();
            $this->warn('  Cross-repository governance violations require manual fixes:');
            foreach (array_slice($crossResult['recommendations'] ?? [], 0, 5) as $rec) {
                $this->line("  • {$rec}");
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function buildReplacementSuggestions(array $driftItems): array
    {
        $suggestions = [];
        foreach ($driftItems as $item) {
            if (($item['category'] ?? '') === 'unauthorized_components') {
                // Extract the raw element name from the issue description
                if (preg_match('/`(<[a-z]+)`/', $item['issue'] ?? '', $m)) {
                    $suggestions[$m[1]] = $item['fix'] ?? 'x-core-ui::*';
                }
            }
        }
        return $suggestions;
    }

    private function buildCiRepairHints(?array $crossResult, ?array $lockdownReport): array
    {
        $hints = [];

        if ($crossResult) {
            foreach ($crossResult['dimensions'] ?? [] as $key => $dim) {
                if ($dim['score'] < 80) {
                    $hints[] = match($key) {
                        'workflow_existence'     => '→ Create .github/workflows/frontend-governance.yml in customer-site',
                        'workflow_job_parity'    => '→ Add missing CI jobs to customer-site workflow; add fetch-depth: 0',
                        'ci_fail_behavior'       => '→ Remove continue-on-error from governance steps; add EXITCODE=$?',
                        'token_import_parity'    => '→ Add design-tokens.css import to customer-site/resources/css/app.css',
                        'branch_enforcement_readiness' => '→ Configure GitHub branch protection per docs/phase-10.2.5/github-branch-protection-guide.md',
                        default                  => "→ Fix '{$key}': " . implode(', ', array_slice($dim['issues'], 0, 1)),
                    };
                }
            }
        }

        if ($lockdownReport && ($lockdownReport['by_severity']['CRITICAL'] ?? 0) > 0) {
            $hints[] = '→ Run: php artisan frontend:lockdown-report to see all critical drift items';
            $hints[] = '→ Replace raw HTML controls with x-core-ui::* canonical components';
        }

        return $hints;
    }
}
