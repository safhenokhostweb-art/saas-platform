<?php

namespace SaaSPlatform\Modules\Tickets\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Tickets\Governance\TicketsFrontendGovernanceValidationService;

/**
 * Phase 9.1 — Task 10
 *
 * php artisan tickets:validate-frontend-governance
 * php artisan tickets:validate-frontend-governance --json
 * php artisan tickets:validate-frontend-governance --strict
 *
 * Exits with code 1 if any governance check fails (architectural regression detected).
 * Designed to run in CI pipelines to enforce canonical frontend authority.
 */
class ValidateFrontendGovernanceCommand extends Command
{
    protected $signature = 'tickets:validate-frontend-governance
                            {--json   : Output results as JSON}
                            {--strict : Alias for fail behaviour (always on)}';

    protected $description = 'Validate that all ticket frontend originates from the canonical package views (Phase 9.1)';

    public function handle(TicketsFrontendGovernanceValidationService $service): int
    {
        $result = $service->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>TICKETS MODULE — FRONTEND GOVERNANCE — PHASE 9.1</>');
        $this->line('  ─────────────────────────────────────────────────────────────────');
        $this->line('  Architecture law: ALL ticket UI must originate from the canonical');
        $this->line('  package views. Frontend divergence = architectural regression.');
        $this->line('  ─────────────────────────────────────────────────────────────────');
        $this->newLine();

        foreach ($result['checks'] as $check) {
            $icon   = $check['passed']
                ? '<fg=green;options=bold>PASS</>'
                : '<fg=red;options=bold>FAIL</>';
            $id     = str_pad($check['id'], 4);
            $label  = str_pad($check['label'], 44);
            $detail = $check['detail'];

            $this->line("  [{$icon}] {$id} {$label} {$detail}");
        }

        $this->newLine();

        if ($result['passed']) {
            $this->line("  <fg=green;options=bold>✓ GOVERNANCE VALID — Score: {$result['score']} — No frontend divergence detected</>");
            $this->line('  <fg=green>  Canonical package authority is fully enforced.</>');
        } else {
            $this->line("  <fg=red;options=bold>✗ GOVERNANCE FAILURE — Score: {$result['score']} — Frontend divergence detected!</>");
            $this->line('  <fg=red>  ARCHITECTURAL REGRESSION: Fix all FAIL checks before merging.</>');
            $this->line('  <fg=yellow>  All ticket UI MUST originate from: packages/saas-platform/modules/tickets/Resources/views/</>');
        }

        $this->newLine();

        return $result['passed'] ? 0 : 1;
    }
}
