<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsBuilderOrderingEngine;

/**
 * Phase 10.2.7 — Validate Forms Builder field ordering pipeline.
 *
 * Usage:
 *   php artisan forms:validate-builder-ordering
 *   php artisan forms:validate-builder-ordering --strict
 *   php artisan forms:validate-builder-ordering --json
 */
class ValidateBuilderOrderingCommand extends Command
{
    protected $signature = 'forms:validate-builder-ordering
                            {--strict : Fail unless score is 100}
                            {--json   : Output JSON}';

    protected $description = 'Validate Forms Builder field ordering engine (SortableJS + Alpine synchronization)';

    public function handle(FormsBuilderOrderingEngine $engine): int
    {
        $strict = (bool) $this->option('strict');
        $json   = (bool) $this->option('json');

        $result = $engine->validate($strict);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>═══ Forms Builder Ordering Engine Validation (Phase 10.2.7) ═══</>');
        $this->newLine();
        $this->line("  Score : <fg=white>{$result['score']}/100 ({$result['grade']})</>");
        $this->newLine();

        foreach ($result['results'] as $r) {
            $icon = $r['pass'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  $icon [{$r['check']}]");
            $this->line("      {$r['message']}");
        }

        $this->newLine();
        if ($result['passed']) {
            $this->info('Validation PASSED — ordering engine is structurally correct.');
        } else {
            $this->error('Validation FAILED — drag/drop ordering has structural issues.');
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
