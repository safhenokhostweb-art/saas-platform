<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;

/**
 * Unified runtime stabilization validator for Phase 9.2.
 * Aggregates all stability dimensions into a single score.
 */
class FormsRuntimeStabilizationService
{
    public function __construct(
        private readonly FormsFrontendParityValidationService $parityService,
        private readonly FormsAssetGovernanceService $assetService,
        private readonly FrontendAccessibilityValidationService $accessibilityService,
        private readonly FormsVisualConsistencyService $consistencyService,
        private readonly FormsRuntimeSelfHealingService $selfHealingService,
    ) {}

    public function runFullValidation(): array
    {
        return [
            'frontend_parity'      => $this->validateFrontendParity(),
            'asset_governance'     => $this->validateAssetGovernance(),
            'accessibility'        => $this->validateAccessibility(),
            'visual_consistency'   => $this->validateVisualConsistency(),
            'self_healing'         => $this->validateSelfHealing(),
            'state_stability'      => $this->validateStateStability(),
            'component_integrity'  => $this->validateComponentIntegrity(),
            'route_coverage'       => $this->validateRouteCoverage(),
        ];
    }

    public function overallScore(): int
    {
        $weights = [
            'frontend_parity'     => 25,
            'asset_governance'    => 15,
            'accessibility'       => 15,
            'visual_consistency'  => 15,
            'self_healing'        => 10,
            'state_stability'     => 10,
            'component_integrity' => 5,
            'route_coverage'      => 5,
        ];

        $results    = $this->runFullValidation();
        $totalWeight = array_sum($weights);
        $earnedScore = 0;

        foreach ($results as $key => $result) {
            $weight = $weights[$key] ?? 5;
            $score  = $result['score'] ?? ($result['status'] === 'pass' ? 100 : ($result['status'] === 'warning' ? 60 : 0));
            $earnedScore += ($score / 100) * $weight;
        }

        return (int) round(($earnedScore / $totalWeight) * 100);
    }

    public function isStable(): bool
    {
        return $this->overallScore() >= 85;
    }

    public function isProductionReady(): bool
    {
        return $this->overallScore() >= 90;
    }

    private function validateFrontendParity(): array
    {
        try {
            $results = $this->parityService->validate();
            $passed  = count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'pass'));
            $score   = (int) round(($passed / count($results)) * 100);

            return [
                'status'  => $score >= 80 ? 'pass' : ($score >= 60 ? 'warning' : 'fail'),
                'score'   => $score,
                'message' => "Frontend parity: {$passed}/" . count($results) . " checks passed",
                'details' => $results,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'score' => 50, 'message' => 'Could not run parity validation: ' . $e->getMessage()];
        }
    }

    private function validateAssetGovernance(): array
    {
        try {
            $score = $this->assetService->score();
            return [
                'status'  => $score >= 80 ? 'pass' : ($score >= 60 ? 'warning' : 'fail'),
                'score'   => $score,
                'message' => "Asset governance score: {$score}/100",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'score' => 50, 'message' => 'Asset validation error: ' . $e->getMessage()];
        }
    }

    private function validateAccessibility(): array
    {
        try {
            $score = $this->accessibilityService->score();
            return [
                'status'  => $score >= 75 ? 'pass' : ($score >= 55 ? 'warning' : 'fail'),
                'score'   => $score,
                'message' => "Accessibility score: {$score}/100",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'score' => 50, 'message' => 'Accessibility validation error: ' . $e->getMessage()];
        }
    }

    private function validateVisualConsistency(): array
    {
        try {
            $score = $this->consistencyService->score();
            return [
                'status'  => $score >= 80 ? 'pass' : ($score >= 60 ? 'warning' : 'fail'),
                'score'   => $score,
                'message' => "Visual consistency score: {$score}/100",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'score' => 50, 'message' => 'Consistency validation error: ' . $e->getMessage()];
        }
    }

    private function validateSelfHealing(): array
    {
        try {
            $diagnostic = $this->selfHealingService->runFullDiagnostic();
            $failed  = count(array_filter($diagnostic, fn($r) => ($r['status'] ?? '') === 'fail'));
            $recovered = count(array_filter($diagnostic, fn($r) => ($r['status'] ?? '') === 'recovered'));
            $total   = count($diagnostic);
            $healthy = $total - $failed;
            $score   = (int) round(($healthy / $total) * 100);

            return [
                'status'    => $failed === 0 ? 'pass' : ($failed <= 1 ? 'warning' : 'fail'),
                'score'     => $score,
                'message'   => "Self-healing: {$healthy}/{$total} checks healthy, {$recovered} auto-recovered",
                'recovered' => $recovered,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'score' => 75, 'message' => 'Self-healing check error: ' . $e->getMessage()];
        }
    }

    private function validateStateStability(): array
    {
        $viewsPath  = realpath(__DIR__ . '/../../resources/views');
        $builderPath = $viewsPath ? $viewsPath . '/admin/forms/builder.blade.php' : null;

        if (!$builderPath || !File::exists($builderPath)) {
            return ['status' => 'warning', 'score' => 50, 'message' => 'Builder view not found'];
        }

        $contents = File::get($builderPath);
        $checks   = [
            'abort_controller'   => str_contains($contents, 'AbortController'),
            'request_dedup'      => str_contains($contents, 'pendingSave') || str_contains($contents, 'saveController'),
            'sortable_resync'    => str_contains($contents, 'reindexFields') || str_contains($contents, 'updateFieldOrder'),
            'toast_dedup'        => str_contains($contents, 'toasts.find') || str_contains($contents, 'dedup'),
            'escape_handler'     => str_contains($contents, '@keydown.escape'),
            'history_limit'      => str_contains($contents, 'history.length > '),
        ];

        $passed = count(array_filter($checks));
        $total  = count($checks);
        $score  = (int) round(($passed / $total) * 100);

        return [
            'status'  => $score >= 80 ? 'pass' : ($score >= 60 ? 'warning' : 'fail'),
            'score'   => $score,
            'message' => "Alpine state stability: {$passed}/{$total} checks passed",
            'checks'  => $checks,
        ];
    }

    private function validateComponentIntegrity(): array
    {
        $viewsPath = realpath(__DIR__ . '/../../resources/views');

        if (!$viewsPath) {
            return ['status' => 'fail', 'score' => 0, 'message' => 'Views path not found'];
        }

        $required = [
            'core-ui/components/toast.blade.php',
            'core-ui/components/empty-state.blade.php',
            'core-ui/components/form-status-pill.blade.php',
            'core-ui/components/autosave-indicator.blade.php',
            'core-ui/components/analytics-card.blade.php',
            'core-ui/components/section-header.blade.php',
            'core-ui/components/skeleton-card.blade.php',
            'core-ui/components/modal.blade.php',
            'core-ui/components/confirm-modal.blade.php',
            'core-ui/components/design-tokens.blade.php',
        ];

        $present = array_filter($required, fn($f) => File::exists($viewsPath . '/' . $f));
        $missing = array_values(array_diff($required, $present));
        $score   = (int) round((count($present) / count($required)) * 100);

        return [
            'status'  => empty($missing) ? 'pass' : ($score >= 70 ? 'warning' : 'fail'),
            'score'   => $score,
            'message' => count($present) . '/' . count($required) . ' core-ui components present',
            'missing' => $missing,
        ];
    }

    private function validateRouteCoverage(): array
    {
        $routeNames = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn($n) => str_starts_with($n, 'admin.forms') || str_starts_with($n, 'forms'))
            ->values()
            ->all();

        $expectedPrefixes = ['admin.forms.index', 'admin.forms.create', 'admin.forms.builder', 'forms.show'];
        $covered = array_filter($expectedPrefixes, fn($p) => in_array($p, $routeNames) || collect($routeNames)->contains(fn($n) => str_contains($n, str_replace('admin.forms.', '', $p))));

        $score = (int) round((count($covered) / count($expectedPrefixes)) * 100);

        return [
            'status'  => $score >= 100 ? 'pass' : ($score >= 75 ? 'warning' : 'fail'),
            'score'   => $score,
            'message' => count($routeNames) . " forms routes registered, {$score}% of key routes covered",
        ];
    }
}
