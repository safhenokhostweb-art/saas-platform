<?php

namespace SaaSPlatform\Modules\Tickets\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Tickets\Services\TicketCanonicalRuntimeValidationService;

/**
 * PHASE 7.2 — TASK 9
 *
 * php artisan tickets:validate-runtime-authority
 * php artisan tickets:validate-runtime-authority --json
 * php artisan tickets:validate-runtime-authority --fail-on-warning
 */
class ValidateRuntimeAuthorityCommand extends Command
{
    protected $signature = 'tickets:validate-runtime-authority
                            {--json            : Output results as JSON}
                            {--fail-on-warning : Exit with code 1 if any check fails}';

    protected $description = 'Validate that the canonical Tickets module has full runtime authority (PHASE 7.2)';

    public function handle(TicketCanonicalRuntimeValidationService $validator): int
    {
        $result = $validator->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>TICKETS CANONICAL RUNTIME AUTHORITY — PHASE 7.2</>');
        $this->line('  ─────────────────────────────────────────────');
        $this->newLine();

        foreach ($result['checks'] as $check) {
            $icon   = $check['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $id     = str_pad($check['id'], 4);
            $label  = str_pad($check['label'], 40);
            $detail = $check['detail'];

            $this->line("  [{$icon}] {$id} {$label} {$detail}");
        }

        $this->newLine();

        if ($result['passed']) {
            $this->line("  <fg=green;options=bold>✓ ALL {$result['score']} CHECKS PASSED — Tickets module is canonical authority</>");
        } else {
            $this->line("  <fg=red;options=bold>✗ {$result['score']} CHECKS PASSED — Review failures above</>");
        }

        $this->newLine();

        if (!$result['passed'] && $this->option('fail-on-warning')) {
            return 1;
        }

        return 0;
    }
}
