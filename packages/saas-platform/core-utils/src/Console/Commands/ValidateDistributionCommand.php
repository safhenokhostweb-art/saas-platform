<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\PackageDistributionValidationService;

/**
 * php artisan package:validate-distribution
 * php artisan package:validate-distribution --strict
 * php artisan package:validate-distribution --json
 * php artisan package:validate-distribution --fail-on-warning
 */
class ValidateDistributionCommand extends Command
{
    protected $signature = 'package:validate-distribution
                            {--strict          : Score must be 100% — any imperfection fails}
                            {--json            : Output results as JSON}
                            {--fail-on-warning : Exit with code 1 if any package fails}';

    protected $description = 'Score all packages across boundary isolation, contract coverage, portability, and framework independence (Phase 8.1)';

    public function handle(PackageDistributionValidationService $validator): int
    {
        $strict = $this->option('strict');
        $result = $validator->run($strict);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? 0 : 1;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>PACKAGE DISTRIBUTABILITY AUDIT — PHASE 8.1</>');
        $this->line('  ─────────────────────────────────────────────');
        $this->newLine();

        foreach ($result['packages'] as $pkg => $data) {
            $icon  = $data['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $grade = $data['passed'] ? "<fg=green>{$data['grade']}</>" : "<fg=red>{$data['grade']}</>";
            $score = str_pad((string) $data['score'] . '%', 6);

            $this->line("  [{$icon}] {$grade}  {$score}  <options=bold>{$pkg}</>");

            foreach ($data['dimensions'] as $dim) {
                $dimIcon  = $dim['score'] >= 100 ? '<fg=green>✓</>' : ($dim['score'] >= 75 ? '<fg=yellow>~</>' : '<fg=red>✗</>');
                $dimScore = str_pad((string) $dim['score'] . '%', 5);
                $dimName  = str_pad($dim['name'], 24);
                $this->line("          {$dimIcon} {$dimScore} {$dimName} {$dim['detail']}");
            }

            $this->newLine();
        }

        $this->line('  ─────────────────────────────────────────────');
        $totalLabel = $result['passed']
            ? "<fg=green;options=bold>✓ PASSED — Grade: {$result['grade']} — Total: {$result['total']}</>"
            : "<fg=red;options=bold>✗ FAILED — Grade: {$result['grade']} — Total: {$result['total']}</>";

        $this->line("  {$totalLabel}");
        $this->newLine();

        if (!$result['passed'] && ($this->option('fail-on-warning') || $strict)) {
            return 1;
        }

        return 0;
    }
}
