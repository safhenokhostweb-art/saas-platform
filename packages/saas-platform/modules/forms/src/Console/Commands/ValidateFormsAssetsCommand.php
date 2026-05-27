<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsAssetGovernanceService;

class ValidateFormsAssetsCommand extends Command
{
    protected $signature = 'forms:validate-assets
                            {--json : Output results as JSON}
                            {--strict : Treat warnings as failures}
                            {--fail-on-warning : Exit with code 1 if any warnings exist}';

    protected $description = 'Validate Forms module asset pipeline — Vite, Tailwind, CDN, SRI, and override detection';

    public function handle(FormsAssetGovernanceService $service): int
    {
        $results = $service->validate();
        $score   = $service->score();

        if ($this->option('json')) {
            $this->line(json_encode([
                'command'   => 'forms:validate-assets',
                'score'     => $score,
                'results'   => $results,
                'timestamp' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return $this->resolveExit($results, $score);
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>════════════════════════════════════</fg=cyan;options=bold>');
        $this->line('<fg=cyan;options=bold>  Forms Asset Governance Validation  </fg=cyan;options=bold>');
        $this->line('<fg=cyan;options=bold>════════════════════════════════════</fg=cyan;options=bold>');
        $this->newLine();

        $table = [];
        foreach ($results as $key => $result) {
            $status = $result['status'] ?? 'unknown';
            $icon   = match ($status) {
                'pass'    => '<fg=green>✓ PASS</>',
                'warning' => '<fg=yellow>⚠ WARN</>',
                default   => '<fg=red>✗ FAIL</>',
            };
            $table[] = [
                str_replace('_', ' ', $key),
                $icon,
                substr($result['message'] ?? '', 0, 90),
            ];
        }

        $this->table(['Check', 'Status', 'Message'], $table);
        $this->newLine();

        $scoreColor = $score >= 80 ? 'green' : ($score >= 60 ? 'yellow' : 'red');
        $this->line("<fg={$scoreColor};options=bold>  Asset Governance Score: {$score}/100</fg={$scoreColor};options=bold>");
        $this->newLine();

        return $this->resolveExit($results, $score);
    }

    private function resolveExit(array $results, int $score): int
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
