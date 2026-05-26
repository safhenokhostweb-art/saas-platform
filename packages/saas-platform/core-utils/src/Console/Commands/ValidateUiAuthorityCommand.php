<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\CanonicalFrontendValidationService;

/**
 * PHASE 7.15 — TASK 8: UI Authority Validation Command
 *
 * Runs all 10 frontend canonical checks and outputs a formatted table.
 *
 * Usage:
 *   php artisan platform:validate-ui-authority
 *   php artisan platform:validate-ui-authority --json
 *   php artisan platform:validate-ui-authority --fail-on-warning
 */
class ValidateUiAuthorityCommand extends Command
{
    protected $signature = 'platform:validate-ui-authority
                            {--json : Output results as JSON}
                            {--fail-on-warning : Exit with code 1 if any check fails}';

    protected $description = 'Validate the canonical UI authority model across all modules';

    public function handle(CanonicalFrontendValidationService $validator): int
    {
        $this->line('');
        $this->line('  <fg=blue>PHASE 7.15 — Canonical UI Authority Validation</>');
        $this->line('  ' . str_repeat('─', 52));
        $this->line('');

        $result = $validator->validate();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($result['checks'] as $check) {
            $icon   = $check['passed'] ? '<fg=green>✅</>' : '<fg=red>❌</>';
            $label  = str_pad("CHECK {$check['check']}", 10);
            $status = $check['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';

            $this->line("  {$icon}  {$label}  {$status}  {$check['label']}");

            if (! $check['passed'] && $check['error']) {
                $this->line("            <fg=red>→ {$check['error']}</>");
            }

            if (! empty($check['details']) && is_array($check['details'])) {
                foreach (array_slice($check['details'], 0, 3) as $detail) {
                    $path = $detail['path'] ?? ($detail['resolved'] ?? '');
                    if ($path) {
                        $this->line("            <fg=yellow>  • {$path}</>");
                    }
                }

                if (count($check['details']) > 3) {
                    $remaining = count($check['details']) - 3;
                    $this->line("            <fg=yellow>  ... and {$remaining} more</>");
                }
            }
        }

        $this->line('');
        $this->line('  ' . str_repeat('─', 52));

        if ($result['passed']) {
            $this->line("  <fg=green>✅ All {$result['total']} checks passed — UI authority is canonical.</>");
        } else {
            $this->line("  <fg=red>❌ {$result['failed']} of {$result['total']} checks FAILED.</>");
            $this->line('  <fg=yellow>   Run: php artisan drift:blades to find Blade drift.</>');
            $this->line('  <fg=yellow>   See: docs/phase-7.15/UI-AUTHORITY-AUDIT.md</>');
        }

        $this->line('');

        if ($this->option('fail-on-warning') && ! $result['passed']) {
            return self::FAILURE;
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
