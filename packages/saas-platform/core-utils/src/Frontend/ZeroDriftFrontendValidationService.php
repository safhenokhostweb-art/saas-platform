<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\App;

/**
 * Phase 10.2 — Zero-Drift Frontend Validation Service
 *
 * Master orchestrator: runs all 11 validation dimensions in sequence
 * and produces a single authoritative Enterprise Frontend Governance Score.
 *
 * Dimensions:
 *  1. Component authority (raw controls, unauthorized HTML)
 *  2. Design token enforcement (colors, spacing, motion)
 *  3. Responsive governance (touch targets, breakpoints, RTL)
 *  4. Accessibility governance (WCAG AA)
 *  5. Module inheritance (all modules extend canonical system)
 *  6. Namespace integrity (Blade namespaces registered correctly)
 *  7. Customer-site synchronization (zero divergence)
 *  8. Stale override detection (published views, vendor overrides)
 *  9. Runtime health (component file existence)
 * 10. CSS token coverage (no raw colors in CSS)
 * 11. CI enforcement readiness
 */
class ZeroDriftFrontendValidationService
{
    private array $dimensionResults = [];

    private const DIMENSION_WEIGHTS = [
        'component_authority'      => 20,
        'design_tokens'            => 15,
        'responsive'               => 10,
        'accessibility'            => 10,
        'module_inheritance'       => 15,
        'namespace_integrity'      => 10,
        'customer_site_parity'     => 10,
        'stale_overrides'          => 5,
        'runtime_health'           => 3,
        'css_token_coverage'       => 2,
    ];

    public function validate(bool $strict = false, bool $failOnDrift = false): array
    {
        $this->dimensionResults = [];

        $this->runComponentAuthority();
        $this->runDesignTokens();
        $this->runResponsive();
        $this->runAccessibility();
        $this->runModuleInheritance();
        $this->runNamespaceIntegrity();
        $this->runCustomerSiteParity();
        $this->runStaleOverrides();
        $this->runRuntimeHealth();
        $this->runCssTokenCoverage();

        $score = $this->computeWeightedScore();
        $grade = $this->grade($score);

        $result = [
            'score'       => $score,
            'grade'       => $grade,
            'dimensions'  => $this->dimensionResults,
            'summary'     => $this->buildSummary($score),
            'zero_drift'  => $score >= 95,
            'ci_ready'    => $score >= 90,
        ];

        if ($failOnDrift && $score < 95) {
            throw new \RuntimeException(
                "Zero-drift validation FAILED: score {$score}/100 (threshold: 95). " .
                implode('; ', $this->collectCriticalMessages())
            );
        }

        return $result;
    }

    private function runComponentAuthority(): void
    {
        try {
            $service = App::make(CanonicalComponentAuthorityService::class);
            $result  = $service->validate();
            $this->dimensionResults['component_authority'] = [
                'score'      => $result['score'],
                'violations' => $result['summary']['total_violations'],
                'status'     => $result['score'] >= 90 ? 'pass' : 'fail',
            ];
        } catch (\Throwable $e) {
            $this->dimensionResults['component_authority'] = ['score' => 0, 'error' => $e->getMessage(), 'status' => 'error'];
        }
    }

    private function runDesignTokens(): void
    {
        try {
            $service = App::make(DesignTokenEnforcementService::class);
            $result  = $service->validate();
            $this->dimensionResults['design_tokens'] = [
                'score'      => $result['score'],
                'violations' => $result['summary']['total_violations'],
                'status'     => $result['score'] >= 90 ? 'pass' : 'fail',
            ];
        } catch (\Throwable $e) {
            $this->dimensionResults['design_tokens'] = ['score' => 0, 'error' => $e->getMessage(), 'status' => 'error'];
        }
    }

    private function runResponsive(): void
    {
        try {
            $service = App::make(ResponsiveGovernanceValidationService::class);
            $result  = $service->validate();
            $this->dimensionResults['responsive'] = [
                'score'      => $result['score'],
                'violations' => $result['summary']['total_violations'],
                'status'     => $result['score'] >= 85 ? 'pass' : 'fail',
            ];
        } catch (\Throwable $e) {
            $this->dimensionResults['responsive'] = ['score' => 0, 'error' => $e->getMessage(), 'status' => 'error'];
        }
    }

    private function runAccessibility(): void
    {
        try {
            $service = App::make(AccessibilityGovernanceValidationService::class);
            $result  = $service->validate();
            $this->dimensionResults['accessibility'] = [
                'score'      => $result['score'],
                'wcag_level' => $result['summary']['wcag_level'],
                'status'     => $result['score'] >= 85 ? 'pass' : 'fail',
            ];
        } catch (\Throwable $e) {
            $this->dimensionResults['accessibility'] = ['score' => 0, 'error' => $e->getMessage(), 'status' => 'error'];
        }
    }

    private function runModuleInheritance(): void
    {
        try {
            $service = App::make(ModuleFrontendInheritanceService::class);
            $result  = $service->validateInheritance();
            $this->dimensionResults['module_inheritance'] = [
                'score'            => $result['score'],
                'modules_compliant'=> $result['summary']['fully_compliant'],
                'status'           => $result['score'] >= 90 ? 'pass' : 'fail',
            ];
        } catch (\Throwable $e) {
            $this->dimensionResults['module_inheritance'] = ['score' => 0, 'error' => $e->getMessage(), 'status' => 'error'];
        }
    }

    private function runNamespaceIntegrity(): void
    {
        try {
            $hints  = app('view')->getFinder()->getHints();
            $hasUi  = isset($hints['core-ui']);
            $score  = $hasUi ? 100 : 0;

            $this->dimensionResults['namespace_integrity'] = [
                'score'           => $score,
                'core_ui_bound'   => $hasUi,
                'registered_hints'=> array_keys($hints),
                'status'          => $hasUi ? 'pass' : 'fail',
            ];
        } catch (\Throwable $e) {
            $this->dimensionResults['namespace_integrity'] = ['score' => 0, 'error' => $e->getMessage(), 'status' => 'error'];
        }
    }

    private function runCustomerSiteParity(): void
    {
        $customerSite = base_path('../customer-site');

        if (!is_dir($customerSite)) {
            $this->dimensionResults['customer_site_parity'] = [
                'score'  => 100,
                'status' => 'pass',
                'note'   => 'customer-site not present — skipped',
            ];
            return;
        }

        $issues = [];

        // Check design-tokens import
        $css = $customerSite . '/resources/css/app.css';
        if (file_exists($css) && !str_contains(file_get_contents($css), 'design-tokens')) {
            $issues[] = 'customer-site missing canonical design-tokens import';
        }

        // Forbidden module view overrides
        $forbidden = [
            $customerSite . '/resources/views/admin/forms',
            $customerSite . '/resources/views/admin/tickets',
        ];
        foreach ($forbidden as $p) {
            if (is_dir($p)) $issues[] = "customer-site has forbidden override: {$p}";
        }

        $score = count($issues) === 0 ? 100 : max(0, 100 - count($issues) * 25);

        $this->dimensionResults['customer_site_parity'] = [
            'score'  => $score,
            'issues' => $issues,
            'status' => $score >= 90 ? 'pass' : 'fail',
        ];
    }

    private function runStaleOverrides(): void
    {
        $stale = [];
        $vendorViewPath = resource_path('vendor');

        if (is_dir($vendorViewPath)) {
            $dirs = glob($vendorViewPath . '/saas-platform*', GLOB_ONLYDIR) ?: [];
            foreach ($dirs as $d) $stale[] = $d;
        }

        $score = count($stale) === 0 ? 100 : max(0, 100 - count($stale) * 20);

        $this->dimensionResults['stale_overrides'] = [
            'score'       => $score,
            'stale_paths' => $stale,
            'status'      => $score === 100 ? 'pass' : 'warn',
        ];
    }

    private function runRuntimeHealth(): void
    {
        $componentPath = base_path('packages/saas-platform/core-utils/resources/views/components');
        $exists        = is_dir($componentPath);
        $count         = $exists ? count(glob($componentPath . '/*.blade.php') ?: []) : 0;

        $this->dimensionResults['runtime_health'] = [
            'score'            => $exists ? 100 : 0,
            'component_dir'    => $exists,
            'component_count'  => $count,
            'status'           => $exists ? 'pass' : 'fail',
        ];
    }

    private function runCssTokenCoverage(): void
    {
        $tokenCss = base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css');
        $exists   = file_exists($tokenCss);
        $count    = 0;

        if ($exists) {
            preg_match_all('/--ui-[\w-]+\s*:/', file_get_contents($tokenCss), $m);
            $count = count($m[0]);
        }

        $score = !$exists ? 0 : ($count >= 100 ? 100 : (int) round($count / 100 * 100));

        $this->dimensionResults['css_token_coverage'] = [
            'score'          => $score,
            'tokens_defined' => $count,
            'token_file'     => $exists,
            'status'         => $score >= 80 ? 'pass' : 'fail',
        ];
    }

    private function computeWeightedScore(): int
    {
        $total  = 0;
        $weight = 0;

        foreach (self::DIMENSION_WEIGHTS as $dimension => $w) {
            $score   = $this->dimensionResults[$dimension]['score'] ?? 0;
            $total  += $score * $w;
            $weight += $w;
        }

        return $weight > 0 ? (int) round($total / $weight) : 0;
    }

    private function buildSummary(int $score): array
    {
        $passing = count(array_filter($this->dimensionResults, fn($d) => ($d['status'] ?? '') === 'pass'));
        $failing = count(array_filter($this->dimensionResults, fn($d) => ($d['status'] ?? '') === 'fail'));
        $errors  = count(array_filter($this->dimensionResults, fn($d) => ($d['status'] ?? '') === 'error'));

        return [
            'enterprise_score'     => $score,
            'dimensions_passing'   => $passing,
            'dimensions_failing'   => $failing,
            'dimensions_errored'   => $errors,
            'total_dimensions'     => count($this->dimensionResults),
            'zero_drift_achieved'  => $score >= 95,
            'enterprise_grade'     => $this->grade($score),
            'recommendation'       => $this->recommendation($score),
        ];
    }

    private function collectCriticalMessages(): array
    {
        $messages = [];

        foreach ($this->dimensionResults as $name => $result) {
            if (($result['status'] ?? '') === 'fail') {
                $messages[] = "{$name}: score={$result['score']}";
            }
        }

        return $messages;
    }

    private function recommendation(int $score): string
    {
        return match(true) {
            $score >= 97 => 'Platform is at peak enterprise frontend governance. Maintain standards.',
            $score >= 93 => 'Excellent. Address remaining warnings to reach 100/100.',
            $score >= 85 => 'Good standing. Fix failing dimensions before next release.',
            $score >= 70 => 'Moderate issues. Frontend drift risk is present. Prioritize violations.',
            default      => 'Critical governance failures. Block deployment until resolved.',
        };
    }

    private function grade(int $score): string
    {
        return match(true) {
            $score >= 97 => 'A+',
            $score >= 93 => 'A',
            $score >= 90 => 'A-',
            $score >= 87 => 'B+',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            default      => 'F',
        };
    }
}
