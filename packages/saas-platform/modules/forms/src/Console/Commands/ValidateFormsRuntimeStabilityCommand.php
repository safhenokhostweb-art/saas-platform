<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsRuntimeStabilizationService;

class ValidateFormsRuntimeStabilityCommand extends Command
{
    protected $signature = 'forms:validate-runtime-stability
                            {--json : Output results as JSON}
                            {--strict : Treat warnings as failures}
                            {--fail-on-warning : Exit with code 1 if any warnings exist}';

    protected $description = 'Validate Forms module runtime stability across all dimensions';

    public function handle(FormsRuntimeStabilizationService $service): int
    {
        $results     = $service->runFullValidation();
        $score       = $service->overallScore();
        $stable      = $service->isStable();
        $prodReady   = $service->isProductionReady();

        if ($this->option('json')) {
            $this->line(json_encode([
                'command'       => 'forms:validate-runtime-stability',
                'score'         => $score,
                'stable'        => $stable,
                'prod_ready'    => $prodReady,
                'dimensions'    => $results,
                'timestamp'     => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return $this->resolveExitCode($results, $score);
        }

        $this->newLine();
        $this->line('<fg=blue;options=bold>═══════════════════════════════════════════════════</fg=blue;options=bold>');
        $this->line('<fg=blue;options=bold>  Forms Runtime Stability Validation — Phase 9.2   </fg=blue;options=bold>');
        $this->line('<fg=blue;options=bold>═══════════════════════════════════════════════════</fg=blue;options=bold>');
        $this->newLine();

        $table = [];
        foreach ($results as $dimension => $result) {
            $status  = $result['status'] ?? 'unknown';
            $dimScore = $result['score'] ?? ($status === 'pass' ? 100 : ($status === 'warning' ? 60 : 0));
            $icon    = match ($status) {
                'pass'      => '<fg=green>✓ PASS</>',
                'warning'   => '<fg=yellow>⚠ WARN</>',
                'recovered' => '<fg=cyan>↺ RCVR</>',
                default     => '<fg=red>✗ FAIL</>',
            };
            $table[] = [
                str_replace('_', ' ', ucwords($dimension, '_')),
                $icon,
                "{$dimScore}/100",
                $result['message'] ?? '',
            ];
        }

        $this->table(
            ['Dimension', 'Status', 'Score', 'Message'],
            $table
        );

        $this->newLine();
        $scoreColor = $score >= 90 ? 'green' : ($score >= 75 ? 'yellow' : 'red');
        $this->line("<fg={$scoreColor};options=bold>  Overall Stability Score: {$score}/100</fg={$scoreColor};options=bold>");
        $this->line("  Stable:          " . ($stable ? '<fg=green>YES</>' : '<fg=red>NO</>'));
        $this->line("  Production Ready: " . ($prodReady ? '<fg=green>YES</>' : '<fg=yellow>NOT YET</>'));
        $this->newLine();

        return $this->resolveExitCode($results, $score);
    }

    private function resolveExitCode(array $results, int $score): int
    {
        $hasFail    = in_array('fail', array_column($results, 'status'));
        $hasWarning = in_array('warning', array_column($results, 'status'));

        if ($hasFail) {
            return self::FAILURE;
        }

        if ($hasWarning && ($this->option('strict') || $this->option('fail-on-warning'))) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
