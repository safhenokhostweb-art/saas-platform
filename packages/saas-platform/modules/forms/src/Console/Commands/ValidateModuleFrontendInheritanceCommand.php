<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\ModuleFrontendAutoInheritanceService;

/**
 * Phase 10.2.6 — Module Frontend Auto-Inheritance Governance Command
 */
class ValidateModuleFrontendInheritanceCommand extends Command
{
    protected $signature = 'frontend:validate-module-inheritance
                            {--retrofit : Auto-apply missing @source directives and governance rules}
                            {--dry-run : Show what retrofit would do without making changes}
                            {--json : Output as JSON}';

    protected $description = 'Phase 10.2.6 — Audit that all modules auto-inherit frontend governance (CSS parity, @source, x-cloak)';

    public function handle(ModuleFrontendAutoInheritanceService $service): int
    {
        $jsonOutput = (bool) $this->option('json');
        $retrofit   = (bool) $this->option('retrofit');
        $dryRun     = (bool) $this->option('dry-run');

        if ($retrofit || $dryRun) {
            $result = $service->retrofit($dryRun);

            if ($jsonOutput) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return isset($result['error']) ? Command::FAILURE : Command::SUCCESS;
            }

            $this->newLine();
            $this->line('  <fg=cyan;options=bold>Module Frontend Auto-Inheritance Retrofit — Phase 10.2.6</>');
            if ($dryRun) {
                $this->line('  <fg=yellow>DRY RUN — no changes made</>');
            }
            $this->newLine();

            if (isset($result['error'])) {
                $this->line("  <fg=red>✗  {$result['error']}</>");
                return Command::FAILURE;
            }

            if (empty($result['changes'])) {
                $this->line('  <fg=green>✓  Already fully governed — no changes needed</>');
            } else {
                foreach ($result['changes'] as $change) {
                    $icon = $dryRun ? '<fg=yellow>–</>' : '<fg=cyan>↻</>';
                    $this->line("  {$icon}  {$change}");
                }
                $this->newLine();
                $this->line("  {$result['message']}");
            }

            $this->newLine();
            return Command::SUCCESS;
        }

        // ── Audit mode ───────────────────────────────────────────────────
        $result = $service->audit();

        if ($jsonOutput) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['compliant'] ? Command::SUCCESS : Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Module Frontend Inheritance Audit — Phase 10.2.6</>');
        $this->line('  <fg=gray>Verifies all modules are governed in customer-site (CSS parity, @source, x-cloak)</>');
        $this->newLine();

        if (isset($result['error'])) {
            $this->line("  <fg=red>✗  {$result['error']}</>");
            return Command::FAILURE;
        }

        foreach ($result['report'] as $key => $item) {
            $icon  = $item['governed'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $label = str_pad(str_replace('_', ' ', $key), 40);
            $this->line("  {$icon}  {$label}");
            foreach ($item['issues'] as $issue) {
                $this->line("        <fg=gray>↳ {$issue}</>");
            }
        }

        $this->newLine();
        $score = $result['score'] ?? 0;
        $color = $score >= 95 ? 'green' : ($score >= 80 ? 'yellow' : 'red');
        $this->line("  Governed: {$result['governed']}/{$result['total']}  Score: <fg={$color};options=bold>{$score}/100</>");

        if (! $result['compliant']) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>Governance gaps found. Run with --retrofit to auto-apply fixes.</>');
            $this->newLine();
            return Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓  All modules governed — frontend inheritance is complete</>');
        $this->newLine();
        return Command::SUCCESS;
    }
}
