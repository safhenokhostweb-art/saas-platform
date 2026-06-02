<?php

namespace App\Console\Commands;

use App\Services\PlatformMessaging\PlatformBoundaryValidator;
use Illuminate\Console\Command;

/**
 * PHASE M3.2 — DOMAIN B validation command.
 *
 * Usage:
 *   php artisan platform:validate-messaging-boundaries
 *   php artisan platform:validate-messaging-boundaries --json
 *   php artisan platform:validate-messaging-boundaries --fail-below=95
 */
class ValidatePlatformMessagingBoundaries extends Command
{
    protected $signature = 'platform:validate-messaging-boundaries
                            {--json : Output results as JSON}
                            {--fail-below=95 : Exit with code 1 if score is below threshold}';

    protected $description = 'Validates DOMAIN B — Platform Communication Runtime boundary integrity';

    public function handle(PlatformBoundaryValidator $validator): int
    {
        $result    = $validator->validate();
        $score     = $result['score'];
        $threshold = (int) $this->option('fail-below');

        if ($this->option('json')) {
            $this->line(json_encode([
                'score'   => $score,
                'passed'  => $result['passed'],
                'results' => $result['results'],
            ], JSON_PRETTY_PRINT));
            return $score >= $threshold ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>DOMAIN B — Platform Communication Runtime Boundaries</> ');
        $this->line('  ─────────────────────────────────────────────────── ');
        $this->newLine();

        foreach ($result['results'] as $key => $check) {
            $icon  = match ($check['status']) {
                'pass' => '<fg=green>✓</>',
                'fail' => '<fg=red>✗</>',
                'warn' => '<fg=yellow>⚠</>',
                default => '?',
            };
            $label = str_pad(str_replace('_', ' ', $key), 35);
            $this->line("  {$icon}  <fg=white>{$label}</> {$check['message']}");
        }

        $this->newLine();
        $color = $score >= 95 ? 'green' : ($score >= 80 ? 'yellow' : 'red');
        $grade = $score >= 95 ? 'A+' : ($score >= 85 ? 'A' : ($score >= 75 ? 'B' : 'C'));
        $this->line("  Score: <fg={$color};options=bold>{$score}/100 (Grade {$grade})</>");
        $this->newLine();

        if ($score < $threshold) {
            $this->error("  ✗ Score {$score} is below threshold {$threshold}. Platform boundaries are at risk.");
            return self::FAILURE;
        }

        $this->info("  ✓ Platform messaging boundaries validated. Score: {$score}/100.");
        return self::SUCCESS;
    }
}
