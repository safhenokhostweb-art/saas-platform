<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsAssetGovernanceService;
use SaaSPlatform\Modules\Forms\Services\FormsVisualConsistencyService;
use SaaSPlatform\Modules\Forms\Services\FrontendAccessibilityValidationService;

class ValidateFormsFrontendConsistencyCommand extends Command
{
    protected $signature = 'forms:validate-frontend-consistency
                            {--json : Output results as JSON}
                            {--strict : Treat warnings as failures}
                            {--fail-on-warning : Exit with code 1 if any warnings exist}';

    protected $description = 'Validate Forms module frontend consistency — assets, visual design, and accessibility';

    public function handle(
        FormsAssetGovernanceService $assetService,
        FormsVisualConsistencyService $consistencyService,
        FrontendAccessibilityValidationService $accessibilityService
    ): int {
        $assetResults       = $assetService->validate();
        $consistencyResults = $consistencyService->validate();
        $accessResults      = $accessibilityService->validate();

        $assetScore       = $assetService->score();
        $consistencyScore = $consistencyService->score();
        $accessScore      = $accessibilityService->score();
        $overallScore     = (int) round(($assetScore + $consistencyScore + $accessScore) / 3);

        if ($this->option('json')) {
            $this->line(json_encode([
                'command'            => 'forms:validate-frontend-consistency',
                'overall_score'      => $overallScore,
                'asset_score'        => $assetScore,
                'consistency_score'  => $consistencyScore,
                'accessibility_score' => $accessScore,
                'asset_results'      => $assetResults,
                'consistency_results' => $consistencyResults,
                'accessibility_results' => $accessResults,
                'timestamp'          => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return $this->resolveExitCode($assetResults + $consistencyResults + $accessResults, $overallScore);
        }

        $this->newLine();
        $this->line('<fg=blue;options=bold>═════════════════════════════════════════════</fg=blue;options=bold>');
        $this->line('<fg=blue;options=bold>  Forms Frontend Consistency Validation       </fg=blue;options=bold>');
        $this->line('<fg=blue;options=bold>═════════════════════════════════════════════</fg=blue;options=bold>');

        // Asset Governance
        $this->newLine();
        $this->line('<options=bold>Asset Governance</options=bold>');
        $this->renderResults($assetResults, $assetScore);

        // Visual Consistency
        $this->newLine();
        $this->line('<options=bold>Visual Consistency</options=bold>');
        $this->renderResults($consistencyResults, $consistencyScore);

        // Accessibility
        $this->newLine();
        $this->line('<options=bold>Accessibility</options=bold>');
        $this->renderResults($accessResults, $accessScore);

        $this->newLine();
        $scoreColor = $overallScore >= 85 ? 'green' : ($overallScore >= 65 ? 'yellow' : 'red');
        $this->line("<fg={$scoreColor};options=bold>  Overall Frontend Consistency: {$overallScore}/100</fg={$scoreColor};options=bold>");
        $this->line("  Asset Governance:   {$assetScore}/100");
        $this->line("  Visual Consistency: {$consistencyScore}/100");
        $this->line("  Accessibility:      {$accessScore}/100");
        $this->newLine();

        return $this->resolveExitCode(
            array_merge($assetResults, $consistencyResults, $accessResults),
            $overallScore
        );
    }

    private function renderResults(array $results, int $score): void
    {
        $table = [];
        foreach ($results as $key => $result) {
            $status = $result['status'] ?? 'unknown';
            $icon   = match ($status) {
                'pass'    => '<fg=green>✓</>',
                'warning' => '<fg=yellow>⚠</>',
                default   => '<fg=red>✗</>',
            };
            $table[] = [
                $icon,
                str_replace('_', ' ', $key),
                substr($result['message'] ?? '', 0, 80),
            ];
        }
        $this->table(['', 'Check', 'Message'], $table);
        $scoreColor = $score >= 80 ? 'green' : ($score >= 60 ? 'yellow' : 'red');
        $this->line("  Score: <fg={$scoreColor}>{$score}/100</>");
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
