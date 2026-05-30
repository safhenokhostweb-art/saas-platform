<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsBuilderRuntimeStabilityService;

/**
 * Phase 10.2.7 — Validate Forms Builder runtime stability.
 *
 * Usage:
 *   php artisan forms:validate-builder-runtime-stability
 *   php artisan forms:validate-builder-runtime-stability --strict
 *   php artisan forms:validate-builder-runtime-stability --json
 */
class ValidateBuilderRuntimeStabilityCommand extends Command
{
    protected $signature = 'forms:validate-builder-runtime-stability
                            {--strict : Fail unless score is 100}
                            {--json   : Output JSON}';

    protected $description = 'Validate Forms Builder runtime stability (undo/redo, autosave, modals, memory leaks)';

    public function handle(FormsBuilderRuntimeStabilityService $service): int
    {
        $strict = (bool) $this->option('strict');
        $json   = (bool) $this->option('json');

        $result = $service->validate($strict);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>═══ Builder Runtime Stability Validation (Phase 10.2.7) ═══</>');
        $this->newLine();
        $this->line("  Score : <fg=white>{$result['score']}/100 ({$result['grade']})</>");

        if (! empty($result['critical_issues'])) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>CRITICAL ISSUES (blocking):</>');
            foreach ($result['critical_issues'] as $r) {
                $this->line("  <fg=red>  ✗ {$r['message']}</>");
            }
        }

        $this->newLine();
        foreach ($result['results'] as $r) {
            $icon = $r['pass'] ? '<fg=green>✓</>' : '<fg=yellow>✗</>';
            $this->line("  $icon {$r['message']}");
        }

        $this->newLine();
        if ($result['passed']) {
            $this->info('Validation PASSED — builder runtime is stable.');
        } else {
            $this->error('Validation FAILED — builder has runtime stability issues.');
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
