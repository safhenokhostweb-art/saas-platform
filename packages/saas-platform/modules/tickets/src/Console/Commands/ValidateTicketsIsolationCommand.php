<?php

namespace SaaSPlatform\Modules\Tickets\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Tickets\Services\TicketsIsolationValidationService;

/**
 * php artisan tickets:validate-isolation
 * php artisan tickets:validate-isolation --strict
 * php artisan tickets:validate-isolation --json
 * php artisan tickets:validate-isolation --fail-on-warning
 */
class ValidateTicketsIsolationCommand extends Command
{
    protected $signature = 'tickets:validate-isolation
                            {--strict          : Treat any failure as fatal}
                            {--json            : Output results as JSON}
                            {--fail-on-warning : Exit with code 1 if any check fails}';

    protected $description = 'Validate that the Tickets module is fully isolated from App\\ namespace (Phase 8.1)';

    public function handle(TicketsIsolationValidationService $validator): int
    {
        $result = $validator->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['passed'] || !$this->option('strict')) ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>TICKETS MODULE — PACKAGE BOUNDARY ISOLATION — PHASE 8.1</>');
        $this->line('  ───────────────────────────────────────────────────────');
        $this->newLine();

        foreach ($result['checks'] as $check) {
            $icon   = $check['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $id     = str_pad($check['id'], 4);
            $label  = str_pad($check['label'], 42);
            $this->line("  [{$icon}] {$id} {$label} {$check['detail']}");
        }

        $this->newLine();

        if ($result['passed']) {
            $this->line("  <fg=green;options=bold>✓ ALL CHECKS PASSED ({$result['score']}) — Tickets module is fully isolated</>");
        } else {
            $this->line("  <fg=red;options=bold>✗ CHECKS: {$result['score']} — Review failures above</>");
        }

        $this->newLine();

        if (!$result['passed'] && ($this->option('fail-on-warning') || $this->option('strict'))) {
            return 1;
        }

        return 0;
    }
}
