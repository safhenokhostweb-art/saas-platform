<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsIsolationValidationService;

/**
 * php artisan forms:validate-isolation
 * php artisan forms:validate-isolation --strict
 * php artisan forms:validate-isolation --json
 * php artisan forms:validate-isolation --fail-on-warning
 */
class ValidateFormsIsolationCommand extends Command
{
    protected $signature = 'forms:validate-isolation
                            {--strict          : Treat any failure as fatal}
                            {--json            : Output results as JSON}
                            {--fail-on-warning : Exit with code 1 if any check fails}';

    protected $description = 'Validate that the Forms module is fully isolated from App\\ namespace (Phase 8.1)';

    public function handle(FormsIsolationValidationService $validator): int
    {
        $result = $validator->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['passed'] || !$this->option('strict')) ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>FORMS MODULE — PACKAGE BOUNDARY ISOLATION — PHASE 8.1</>');
        $this->line('  ──────────────────────────────────────────────────────');
        $this->newLine();

        foreach ($result['checks'] as $check) {
            $icon   = $check['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $id     = str_pad($check['id'], 4);
            $label  = str_pad($check['label'], 42);
            $this->line("  [{$icon}] {$id} {$label} {$check['detail']}");
        }

        $this->newLine();

        if ($result['passed']) {
            $this->line("  <fg=green;options=bold>✓ ALL CHECKS PASSED ({$result['score']}) — Forms module is fully isolated</>");
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
