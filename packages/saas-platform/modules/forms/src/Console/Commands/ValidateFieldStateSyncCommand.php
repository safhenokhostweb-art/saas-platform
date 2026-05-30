<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsFieldStateSynchronizationService;

/**
 * Phase 10.2.7 — Validate field property state synchronization.
 *
 * Usage:
 *   php artisan forms:validate-field-state-sync
 *   php artisan forms:validate-field-state-sync --strict
 *   php artisan forms:validate-field-state-sync --json
 */
class ValidateFieldStateSyncCommand extends Command
{
    protected $signature = 'forms:validate-field-state-sync
                            {--strict : Fail unless score is 100}
                            {--json   : Output JSON}';

    protected $description = 'Validate field property state synchronization (required/disabled toggles, debounce, AbortController)';

    public function handle(FormsFieldStateSynchronizationService $service): int
    {
        $strict = (bool) $this->option('strict');
        $json   = (bool) $this->option('json');

        $result = $service->validate($strict);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>═══ Field State Synchronization Validation (Phase 10.2.7) ═══</>');
        $this->newLine();
        $this->line("  Score : <fg=white>{$result['score']}/100 ({$result['grade']})</>");
        $this->newLine();

        foreach ($result['results'] as $r) {
            $icon = $r['pass'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  $icon {$r['message']}");
        }

        $this->newLine();
        if ($result['passed']) {
            $this->info('Validation PASSED — field property sync is correct.');
        } else {
            $this->error('Validation FAILED — field property sync has issues.');
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
