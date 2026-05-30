<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsZeroDriftParityService;

/**
 * Phase 10.2.7 — Validate zero visual drift between saas-platform and customer-site.
 *
 * Usage:
 *   php artisan forms:validate-zero-drift
 *   php artisan forms:validate-zero-drift --strict
 *   php artisan forms:validate-zero-drift --json
 */
class ValidateZeroDriftCommand extends Command
{
    protected $signature = 'forms:validate-zero-drift
                            {--strict : Fail unless score is 100}
                            {--json   : Output JSON}';

    protected $description = 'Validate zero visual drift between saas-platform and customer-site (canonical tokens only)';

    public function handle(FormsZeroDriftParityService $service): int
    {
        $strict = (bool) $this->option('strict');
        $json   = (bool) $this->option('json');

        $result = $service->validate($strict);

        if ($json) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>═══ Zero-Drift Frontend Parity Validation (Phase 10.2.7) ═══</>');
        $this->newLine();
        $this->line("  Score           : <fg=white>{$result['score']}/100 ({$result['grade']})</>");
        $this->line("  Total violations: <fg=white>{$result['total_violations']}</>");
        $this->newLine();

        foreach ($result['results'] as $r) {
            $icon = $r['pass'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  $icon [{$r['check']}] {$r['message']}");
            if (! empty($r['violations'])) {
                foreach (array_slice($r['violations'], 0, 5) as $v) {
                    $this->line("       → $v");
                }
                if (count($r['violations']) > 5) {
                    $this->line("       ... and " . (count($r['violations']) - 5) . " more");
                }
            }
        }

        $this->newLine();
        if ($result['passed']) {
            $this->info('Validation PASSED — zero visual drift confirmed.');
        } else {
            $this->error('Validation FAILED — visual drift detected between projects.');
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
