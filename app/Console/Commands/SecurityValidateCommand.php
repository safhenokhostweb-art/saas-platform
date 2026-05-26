<?php

namespace App\Console\Commands;

use App\Services\SecurityValidationService;
use Illuminate\Console\Command;

/**
 * PHASE 8.0 — Security validation suite.
 *
 * Usage:
 *   php artisan security:validate
 *   php artisan security:validate --json
 *   php artisan security:validate --fail-on-warning
 *   php artisan security:validate --strict
 */
class SecurityValidateCommand extends Command
{
    protected $signature = 'security:validate
        {--json           : Output results as JSON}
        {--fail-on-warning : Exit 1 on HIGH/MEDIUM findings (not just CRITICAL)}
        {--strict         : Exit 1 on ANY finding including LOW}';

    protected $description = 'Run the full platform security validation suite';

    public function handle(SecurityValidationService $validator): int
    {
        $this->line('Running security validation...');
        $this->newLine();

        $results  = $validator->runAll();
        $allFindings = collect($results)->flatten(1);

        $criticals = $allFindings->where('severity', 'CRITICAL');
        $highs     = $allFindings->where('severity', 'HIGH');
        $mediums   = $allFindings->where('severity', 'MEDIUM');
        $lows      = $allFindings->where('severity', 'LOW');

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => [
                    'critical' => $criticals->count(),
                    'high'     => $highs->count(),
                    'medium'   => $mediums->count(),
                    'low'      => $lows->count(),
                ],
                'checks' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $this->exitCode($criticals->count(), $highs->count() + $mediums->count(), $lows->count());
        }

        foreach ($results as $checkName => $findings) {
            $label = str_replace('_', ' ', ucfirst($checkName));

            if (empty($findings)) {
                $this->line("  <fg=green>✓</fg=green> {$label}");
                continue;
            }

            $this->line("  <fg=red>✗</fg=red> {$label}");
            foreach ($findings as $finding) {
                $color = match ($finding['severity']) {
                    'CRITICAL' => 'red',
                    'HIGH'     => 'yellow',
                    'MEDIUM'   => 'cyan',
                    default    => 'white',
                };
                $this->line("      <fg={$color}>[{$finding['severity']}]</fg={$color}> {$finding['message']}");
                if (!empty($finding['location'])) {
                    $this->line("              Location: {$finding['location']}");
                }
            }
        }

        $this->newLine();

        $totalIssues = $allFindings->count();
        if ($totalIssues === 0) {
            $this->info('✓ All security checks passed.');
            return self::SUCCESS;
        }

        $this->warn(sprintf(
            'Security issues found: %d critical, %d high, %d medium, %d low',
            $criticals->count(), $highs->count(), $mediums->count(), $lows->count()
        ));

        return $this->exitCode($criticals->count(), $highs->count() + $mediums->count(), $lows->count());
    }

    private function exitCode(int $criticals, int $warnings, int $lows): int
    {
        if ($criticals > 0) {
            return self::FAILURE;
        }
        if ($warnings > 0 && ($this->option('fail-on-warning') || $this->option('strict'))) {
            return self::FAILURE;
        }
        if ($lows > 0 && $this->option('strict')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
