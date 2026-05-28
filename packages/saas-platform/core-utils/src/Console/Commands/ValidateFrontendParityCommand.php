<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\ZeroDriftFrontendValidationService;

class ValidateFrontendParityCommand extends Command
{
    protected $signature = 'frontend:validate-parity
                            {--json            : Output results as JSON}
                            {--strict          : Exit non-zero if any dimension < 95}
                            {--fail-on-drift   : Exit non-zero if zero-drift not achieved}
                            {--fail-below=95   : Exit non-zero if score below given threshold}';

    protected $description = 'Validate full frontend parity — runs all 10 governance dimensions';

    public function handle(ZeroDriftFrontendValidationService $service): int
    {
        $threshold  = (int) $this->option('fail-below');
        $strict     = $this->option('strict');
        $failOnDrift= $this->option('fail-on-drift');

        try {
            $result = $service->validate($strict, $failOnDrift);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return $result['score'] >= $threshold ? self::SUCCESS : self::FAILURE;
        }

        $score = $result['score'];
        $grade = $result['grade'];
        $color = $score >= 95 ? 'green' : ($score >= 80 ? 'yellow' : 'red');

        $this->newLine();
        $this->line("  <fg={$color};options=bold>Enterprise Frontend Governance Score: {$score}/100 (Grade {$grade})</>");
        $this->line("  Zero-Drift: " . ($result['zero_drift'] ? '<fg=green>✓ ACHIEVED</>' : '<fg=red>✗ NOT ACHIEVED</>'));
        $this->newLine();

        $rows = [];
        foreach ($result['dimensions'] as $dim => $data) {
            $dimScore  = $data['score'] ?? 0;
            $status    = $data['status'] ?? 'unknown';
            $statusFmt = match($status) {
                'pass'  => '<fg=green>✓ Pass</>',
                'fail'  => '<fg=red>✗ Fail</>',
                'warn'  => '<fg=yellow>⚠ Warn</>',
                'error' => '<fg=red>⚠ Error</>',
                default => $status,
            };
            $rows[] = [str_replace('_', ' ', ucfirst($dim)), "{$dimScore}/100", $statusFmt];
        }

        $this->table(['Dimension', 'Score', 'Status'], $rows);

        $summary = $result['summary'];
        $this->newLine();
        $this->line("  Dimensions passing: <fg=green>{$summary['dimensions_passing']}/{$summary['total_dimensions']}</>");
        $this->line("  Recommendation: <fg=cyan>{$summary['recommendation']}</>");
        $this->newLine();

        return $score >= $threshold ? self::SUCCESS : self::FAILURE;
    }
}
