<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\BuilderAlpineValidationService;

/**
 * Phase 10.2.7 — Validate Forms Builder Alpine bindings.
 *
 * Detects Alpine variables erroneously bound with Blade's PHP-evaluated
 * `:attr` syntax on Blade components — the root cause of the
 * "Undefined constant historyIndex" crash.
 *
 * Usage:
 *   php artisan forms:validate-builder-alpine
 *   php artisan forms:validate-builder-alpine --strict
 *   php artisan forms:validate-builder-alpine --json
 */
class ValidateBuilderAlpineCommand extends Command
{
    protected $signature = 'forms:validate-builder-alpine
                            {--strict  : Fail unless score is 100}
                            {--json    : Output JSON}';

    protected $description = 'Validate Forms Builder for Alpine/Blade binding collisions (historyIndex crash prevention)';

    public function handle(BuilderAlpineValidationService $service): int
    {
        $strict = (bool) $this->option('strict');
        $json   = (bool) $this->option('json');

        $result = $service->validate($strict);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>═══ Forms Builder Alpine Validation (Phase 10.2.7) ═══</>');
        $this->newLine();

        if ($result['error']) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->line("  Lines checked : <fg=white>{$result['checked_lines']}</>");
        $this->line("  Score         : <fg=white>{$result['score']}/100 ({$result['grade']})</>");
        $this->line("  Violations    : <fg=white>" . count($result['violations']) . "</>");
        $this->newLine();

        if (empty($result['violations'])) {
            $this->line('  <fg=green>✓ No Alpine/Blade binding violations found.</>');
            $this->line('  <fg=green>✓ Builder will open without PHP constant crashes.</>');
        } else {
            foreach ($result['violations'] as $v) {
                $lineInfo = isset($v['line']) ? " (line {$v['line']})" : '';
                $this->line("  <fg=red>✗ [{$v['type']}]{$lineInfo}</>");
                $this->line("    Expression : {$v['expression']}");
                $this->line("    Severity   : {$v['severity']}");
                $this->line("    Fix        : {$v['fix']}");
                $this->newLine();
            }
        }

        $this->line("  Summary: {$result['summary']}");
        $this->newLine();

        if (! $result['passed']) {
            $this->error('Validation FAILED — builder will crash on open due to PHP constant evaluation.');
            return self::FAILURE;
        }

        $this->info('Validation PASSED — builder Alpine bindings are correct.');
        return self::SUCCESS;
    }
}
