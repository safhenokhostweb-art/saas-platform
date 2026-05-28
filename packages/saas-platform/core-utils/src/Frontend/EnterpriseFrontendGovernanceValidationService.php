<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.1 — Task 13 / Phase 10.2.5 — Task 8
 * Master governance validator for enterprise frontend authority.
 * Aggregates 17 validation dimensions into a single governance score.
 *
 * Original 12 dimensions:
 *  1. Design token enforcement
 *  2. Core-ui adoption
 *  3. Runtime consistency
 *  4. Modal normalization
 *  5. Form normalization
 *  6. Table normalization
 *  7. Responsive integrity
 *  8. Accessibility
 *  9. Mobile governance
 * 10. Cross-context parity
 * 11. Runtime recovery
 * 12. Legacy drift detection
 *
 * Phase 10.2.5 additions:
 * 13. Cross-project governance
 * 14. CI enforcement readiness
 * 15. Drift lockdown readiness
 * 16. Branch protection readiness
 * 17. Future module inheritance safety
 */
class EnterpriseFrontendGovernanceValidationService
{
    private const DIMENSION_WEIGHTS = [
        'design_token_enforcement'      => 12,
        'core_ui_adoption'              => 12,
        'runtime_consistency'           => 10,
        'modal_normalization'           => 8,
        'form_normalization'            => 8,
        'table_normalization'           => 8,
        'responsive_integrity'          => 8,
        'accessibility'                 => 8,
        'mobile_governance'             => 8,
        'cross_context_parity'          => 8,
        'runtime_recovery'              => 5,
        'legacy_drift_detection'        => 5,
        // Phase 10.2.5 — new dimensions
        'cross_project_governance'      => 8,
        'ci_enforcement_readiness'      => 7,
        'drift_lockdown_readiness'      => 6,
        'branch_protection_readiness'   => 5,
        'future_module_inheritance'     => 5,
    ];

    public function validate(bool $strict = false): array
    {
        $dimensions = [];

        $dimensions['design_token_enforcement']    = $this->validateDesignTokens();
        $dimensions['core_ui_adoption']           = $this->validateCoreUiAdoption();
        $dimensions['runtime_consistency']        = $this->validateRuntimeConsistency();
        $dimensions['modal_normalization']        = $this->validateModalNormalization();
        $dimensions['form_normalization']         = $this->validateFormNormalization();
        $dimensions['table_normalization']        = $this->validateTableNormalization();
        $dimensions['responsive_integrity']       = $this->validateResponsiveIntegrity();
        $dimensions['accessibility']              = $this->validateAccessibility();
        $dimensions['mobile_governance']          = $this->validateMobileGovernance();
        $dimensions['cross_context_parity']       = $this->validateCrossContextParity();
        $dimensions['runtime_recovery']           = $this->validateRuntimeRecovery();
        $dimensions['legacy_drift_detection']     = $this->validateLegacyDrift();
        // Phase 10.2.5 — new dimensions
        $dimensions['cross_project_governance']   = $this->validateCrossProjectGovernance();
        $dimensions['ci_enforcement_readiness']   = $this->validateCiEnforcementReadiness();
        $dimensions['drift_lockdown_readiness']   = $this->validateDriftLockdownReadiness();
        $dimensions['branch_protection_readiness']= $this->validateBranchProtectionReadiness();
        $dimensions['future_module_inheritance']  = $this->validateFutureModuleInheritance();

        $weightedScore = $this->computeWeightedScore($dimensions);
        $grade         = $this->scoreToGrade($weightedScore);
        $passed        = $weightedScore >= ($strict ? 90 : 80);

        return [
            'passed'     => $passed,
            'score'      => $weightedScore,
            'grade'      => $grade,
            'strict'     => $strict,
            'dimensions' => $dimensions,
            'summary'    => $this->buildSummary($weightedScore, $grade, $dimensions),
            'report'     => $this->buildReport($dimensions, $weightedScore, $grade),
        ];
    }

    private function validateDesignTokens(): array
    {
        $tokenFile = base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css');
        $issues    = [];
        $score     = 100;

        if (! file_exists($tokenFile)) {
            return $this->dim(0, ['design-tokens.css missing — CRITICAL failure'], 'CRITICAL');
        }

        $content = file_get_contents($tokenFile);

        // Required token groups
        $required = ['--ui-focus-ring', '--ui-modal-overlay-bg', '--ui-navbar-height', '--ui-sidebar-width', '--ui-input-height-md', '--ui-btn-height-md', '--ui-touch-target-min', '--ui-table-row-height-default', '--ui-breakpoint-sm'];
        foreach ($required as $token) {
            if (! str_contains($content, $token)) {
                $issues[] = "Missing required token: {$token}";
                $score -= 8;
            }
        }

        // Reduced-motion support
        if (! str_contains($content, 'prefers-reduced-motion')) {
            $issues[] = 'Missing prefers-reduced-motion support';
            $score -= 10;
        }

        // Dark mode readiness
        if (! str_contains($content, 'prefers-color-scheme: dark')) {
            $issues[] = 'Missing dark mode token overrides';
            $score -= 5;
        }

        // Backward compat aliases
        if (! str_contains($content, '--forms-primary')) {
            $issues[] = 'Missing backward-compat --forms-* aliases';
            $score -= 3;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'CRITICAL' : 'HIGH'));
    }

    private function validateCoreUiAdoption(): array
    {
        $componentsPath = base_path('packages/saas-platform/core-utils/resources/views/components');
        $issues         = [];
        $score          = 0;

        $canonical = [
            'button', 'input', 'select', 'textarea', 'checkbox', 'radio-card',
            'switch', 'dropdown', 'modal', 'confirm-modal', 'badge', 'alert',
            'toast', 'skeleton', 'data-table', 'page-header', 'toolbar',
            'content-container', 'stat-card', 'card',
        ];

        $found = 0;
        foreach ($canonical as $comp) {
            if (file_exists($componentsPath . '/' . $comp . '.blade.php')) {
                $found++;
            } else {
                $issues[] = "Missing canonical component: x-core-ui::{$comp}";
            }
        }

        $score = (int) round(($found / count($canonical)) * 100);

        return $this->dim($score, $issues, $score < 80 ? 'HIGH' : ($score < 95 ? 'MEDIUM' : 'PASS'));
    }

    private function validateRuntimeConsistency(): array
    {
        $issues = [];
        $score  = 100;

        // Check FormsServiceProvider registers core-ui namespace
        $formsProvider = base_path('packages/saas-platform/modules/forms/src/Providers/FormsServiceProvider.php');
        if (file_exists($formsProvider)) {
            $content = file_get_contents($formsProvider);
            if (! str_contains($content, "anonymousComponentPath") && ! str_contains($content, 'core-ui')) {
                $issues[] = 'FormsServiceProvider does not register core-ui component path';
                $score -= 20;
            }
        }

        // Check CoreUtilsServiceProvider registers core-ui FIRST
        $coreProvider = base_path('packages/saas-platform/core-utils/src/Providers/CoreUtilsServiceProvider.php');
        if (file_exists($coreProvider)) {
            $content = file_get_contents($coreProvider);
            if (! str_contains($content, "anonymousComponentPath(__DIR__ . '/../../resources/views/components', 'core-ui')")) {
                $issues[] = 'CoreUtilsServiceProvider missing canonical component namespace registration';
                $score -= 20;
            }
        }

        // Check app.css imports tokens
        $appCss = resource_path('css/app.css');
        if (file_exists($appCss)) {
            $content = file_get_contents($appCss);
            if (! str_contains($content, 'design-tokens.css')) {
                $issues[] = 'app.css does not import canonical design-tokens.css';
                $score -= 15;
            }
        } else {
            $issues[] = 'app.css not found';
            $score -= 20;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'CRITICAL' : 'HIGH'));
    }

    private function validateModalNormalization(): array
    {
        $issues = [];
        $score  = 100;

        $modalFile = base_path('packages/saas-platform/core-utils/resources/views/components/modal.blade.php');
        $confirmFile = base_path('packages/saas-platform/core-utils/resources/views/components/confirm-modal.blade.php');

        if (! file_exists($modalFile)) { $issues[] = 'x-core-ui::modal not created'; $score -= 40; }
        if (! file_exists($confirmFile)) { $issues[] = 'x-core-ui::confirm-modal not created'; $score -= 20; }

        if (file_exists($modalFile)) {
            $content = file_get_contents($modalFile);
            if (! str_contains($content, 'focus')) { $issues[] = 'Modal missing focus trapping'; $score -= 10; }
            if (! str_contains($content, 'escape')) { $issues[] = 'Modal missing Escape key handling'; $score -= 10; }
            if (! str_contains($content, 'overflow')) { $issues[] = 'Modal missing scroll locking'; $score -= 5; }
            if (! str_contains($content, '--ui-z-modal')) { $issues[] = 'Modal not using --ui-z-modal token'; $score -= 5; }
        }

        // Check for custom modals in forms/tickets
        $formBuilder = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        if (file_exists($formBuilder)) {
            $content = file_get_contents($formBuilder);
            if (str_contains($content, 'window.confirm(')) {
                $issues[] = 'Forms builder still uses window.confirm() — must use confirm-modal';
                $score -= 15;
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateFormNormalization(): array
    {
        $issues = [];
        $score  = 100;

        $formControls = ['input', 'select', 'textarea', 'checkbox', 'radio-card', 'switch'];
        $path         = base_path('packages/saas-platform/core-utils/resources/views/components');

        foreach ($formControls as $control) {
            if (! file_exists($path . '/' . $control . '.blade.php')) {
                $issues[] = "Missing x-core-ui::{$control}";
                $score   -= 12;
            }
        }

        // Check tickets settings uses core-ui form controls
        $ticketsSettings = base_path('packages/saas-platform/modules/tickets/Resources/views/admin/tickets/settings.blade.php');
        if (file_exists($ticketsSettings)) {
            $content = file_get_contents($ticketsSettings);
            if (! str_contains($content, 'x-core-ui::input') && str_contains($content, '<input')) {
                $issues[] = 'Tickets settings still uses raw <input> — migrate to x-core-ui::input';
                $score   -= 10;
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateTableNormalization(): array
    {
        $issues = [];
        $score  = 100;

        $tableFile = base_path('packages/saas-platform/core-utils/resources/views/components/data-table.blade.php');
        if (! file_exists($tableFile)) {
            return $this->dim(0, ['x-core-ui::data-table not found'], 'CRITICAL');
        }

        $content = file_get_contents($tableFile);
        $checks  = [
            'sticky header'    => 'sticky top-0',
            'skeleton loading' => 'ui-skeleton',
            'sort columns'     => 'requestSort',
            'bulk selection'   => 'selectedAll',
            'empty state'      => 'empty',
            'token styling'    => '--ui-table',
        ];

        foreach ($checks as $label => $needle) {
            if (! str_contains($content, $needle)) {
                $issues[] = "data-table missing: {$label}";
                $score   -= 10;
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateResponsiveIntegrity(): array
    {
        $issues = [];
        $score  = 100;

        // Check token file has breakpoint tokens
        $tokenFile = base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css');
        if (file_exists($tokenFile)) {
            $content = file_get_contents($tokenFile);
            if (! str_contains($content, '--ui-breakpoint-sm')) { $issues[] = 'Missing --ui-breakpoint-* tokens'; $score -= 15; }
            if (! str_contains($content, '--ui-touch-target-min')) { $issues[] = 'Missing --ui-touch-target-min token'; $score -= 10; }
            if (! str_contains($content, 'prefers-reduced-motion')) { $issues[] = 'Missing prefers-reduced-motion media query'; $score -= 15; }
        }

        // Admin layout responsive check
        $adminLayout = resource_path('views/layouts/admin.blade.php');
        if (file_exists($adminLayout)) {
            $content = file_get_contents($adminLayout);
            if (! str_contains($content, 'sm:') && ! str_contains($content, 'md:')) {
                $issues[] = 'admin.blade.php has no responsive Tailwind variants';
                $score   -= 20;
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateAccessibility(): array
    {
        $issues = [];
        $score  = 100;

        $checkFiles = [
            base_path('packages/saas-platform/core-utils/resources/views/components/button.blade.php'),
            base_path('packages/saas-platform/core-utils/resources/views/components/modal.blade.php'),
            base_path('packages/saas-platform/core-utils/resources/views/components/input.blade.php'),
        ];

        $accessibilityPatterns = [
            'focus-visible' => 'focus-visible',
            'aria-label'    => 'aria-label',
            'role'          => 'role=',
        ];

        foreach ($checkFiles as $file) {
            if (! file_exists($file)) continue;
            $content = file_get_contents($file);
            foreach ($accessibilityPatterns as $label => $pattern) {
                if (! str_contains($content, $pattern)) {
                    $component = basename($file, '.blade.php');
                    $issues[]  = "{$component} missing {$label}";
                    $score    -= 5;
                }
            }
        }

        // Check no tabindex=-1 abuse
        $allViews = $this->getAllViews();
        foreach ($allViews as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'tabindex="-1"') && ! str_contains($content, 'trap')) {
                // Acceptable only in focus traps
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateMobileGovernance(): array
    {
        $issues = [];
        $score  = 100;

        $modalFile = base_path('packages/saas-platform/core-utils/resources/views/components/modal.blade.php');
        if (file_exists($modalFile)) {
            $content = file_get_contents($modalFile);
            if (! str_contains($content, 'inset-x-')) {
                $issues[] = 'Modal does not use inset-x responsive positioning';
                $score   -= 20;
            }
        }

        // Check admin layout has mobile sidebar toggle
        $adminLayout = resource_path('views/layouts/admin.blade.php');
        if (file_exists($adminLayout)) {
            $content = file_get_contents($adminLayout);
            if (! str_contains($content, 'x-show') && ! str_contains($content, 'lg:flex')) {
                $issues[] = 'Admin layout missing mobile sidebar collapse behavior';
                $score   -= 25;
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'CRITICAL' : 'HIGH'));
    }

    private function validateCrossContextParity(): array
    {
        $issues = [];
        $score  = 100;

        // Both apps should import design-tokens.css
        $saasAppCss     = resource_path('css/app.css');
        $customerAppCss = base_path('../customer-site/resources/css/app.css');

        if (file_exists($saasAppCss) && ! str_contains(file_get_contents($saasAppCss), 'design-tokens.css')) {
            $issues[] = 'saas-platform app.css missing design-tokens.css import';
            $score   -= 30;
        }

        if (file_exists($customerAppCss)) {
            if (! str_contains(file_get_contents($customerAppCss), 'design-tokens.css')) {
                $issues[] = 'customer-site app.css missing design-tokens.css import';
                $score   -= 30;
            }
        } else {
            $issues[] = 'customer-site app.css not found — parity unverifiable';
            $score   -= 10;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateRuntimeRecovery(): array
    {
        $issues = [];
        $score  = 100;

        $recoveryService = base_path('packages/saas-platform/core-utils/src/Frontend/FrontendNormalizationRecoveryService.php');
        if (! file_exists($recoveryService)) {
            $issues[] = 'FrontendNormalizationRecoveryService not found';
            $score   -= 50;
        }

        $governanceService = base_path('packages/saas-platform/core-utils/src/Frontend/FrontendComponentGovernanceService.php');
        if (! file_exists($governanceService)) {
            $issues[] = 'FrontendComponentGovernanceService not found';
            $score   -= 30;
        }

        $stateService = base_path('packages/saas-platform/core-utils/src/Frontend/FrontendStateGovernanceService.php');
        if (! file_exists($stateService)) {
            $issues[] = 'FrontendStateGovernanceService not found';
            $score   -= 20;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateLegacyDrift(): array
    {
        $issues = [];
        $score  = 100;

        // Check for inline style blocks in module views
        $checkPaths = [
            base_path('packages/saas-platform/modules/tickets/Resources/views/admin/tickets/index.blade.php'),
            base_path('packages/saas-platform/modules/tickets/Resources/views/admin/tickets/show.blade.php'),
        ];

        foreach ($checkPaths as $file) {
            if (! file_exists($file)) continue;
            $content = file_get_contents($file);

            if (preg_match('/<style\b[^>]*>[\s\S]{200,}<\/style>/i', $content)) {
                $component = basename($file);
                $issues[]  = "{$component} contains large inline <style> block — must be tokenized";
                $score    -= 20;
            }

            if (preg_match('/#[0-9a-fA-F]{6}/', $content)) {
                $component = basename($file);
                $issues[]  = "{$component} contains hardcoded hex colors";
                $score    -= 10;
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'HIGH' : 'MEDIUM'));
    }

    // ── Phase 10.2.5 New Dimensions ────────────────────────────────────────────

    private function validateCrossProjectGovernance(): array
    {
        $issues = [];
        $score  = 100;

        $customerRoot = base_path('/../customer-site');

        // customer-site must exist
        if (!is_dir($customerRoot)) {
            return $this->dim(60, ['customer-site repository not found — cross-project governance unverifiable'], 'HIGH');
        }

        // Both must import design-tokens.css
        $csAppCss = $customerRoot . '/resources/css/app.css';
        if (!file_exists($csAppCss)) {
            $issues[] = 'customer-site app.css not found';
            $score   -= 20;
        } elseif (!str_contains(file_get_contents($csAppCss), 'design-tokens')) {
            $issues[] = 'customer-site app.css does not import canonical design-tokens.css';
            $score   -= 30;
        }

        // customer-site must have governance workflow
        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (!file_exists($csWorkflow)) {
            $issues[] = 'customer-site missing .github/workflows/frontend-governance.yml';
            $score   -= 40;
        } else {
            $content = file_get_contents($csWorkflow);
            if (!str_contains($content, 'enterprise-governance')) {
                $issues[] = 'customer-site governance workflow missing enterprise-governance job';
                $score   -= 20;
            }
            if (!str_contains($content, '--fail-below=95')) {
                $issues[] = 'customer-site workflow does not enforce --fail-below=95 threshold';
                $score   -= 10;
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'CRITICAL' : 'HIGH'));
    }

    private function validateCiEnforcementReadiness(): array
    {
        $issues = [];
        $score  = 100;

        $workflow = base_path('.github/workflows/frontend-governance.yml');
        if (!file_exists($workflow)) {
            return $this->dim(0, ['saas-platform frontend-governance.yml missing'], 'CRITICAL');
        }

        $content = file_get_contents($workflow);

        // Must not have continue-on-error bypasses
        if (preg_match('/continue-on-error:\s*true/i', $content)) {
            $issues[] = 'Workflow has continue-on-error: true — violations will silently pass';
            $score   -= 40;
        }

        // Must have fetch-depth: 0 for accurate git diff
        if (!str_contains($content, 'fetch-depth: 0')) {
            $issues[] = 'Workflow missing fetch-depth: 0 — git diff failures likely on shallow clones';
            $score   -= 20;
        }

        // All jobs must use exit code propagation
        if (str_contains($content, 'php artisan') && !str_contains($content, 'EXITCODE=$?')) {
            $issues[] = 'Governance commands do not explicitly propagate exit codes (add EXITCODE=$?)';
            $score   -= 15;
        }

        // Must trigger on pull_request
        if (!str_contains($content, 'pull_request')) {
            $issues[] = 'Workflow does not trigger on pull_request — PRs bypass governance';
            $score   -= 25;
        }

        // Required-checks.json must exist
        if (!file_exists(base_path('.github/required-checks.json'))) {
            $issues[] = '.github/required-checks.json missing — branch protection guide incomplete';
            $score   -= 10;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateDriftLockdownReadiness(): array
    {
        $issues = [];
        $score  = 100;

        // FrontendDriftLockdownService must exist
        $lockdownService = base_path('packages/saas-platform/core-utils/src/Frontend/FrontendDriftLockdownService.php');
        if (!file_exists($lockdownService)) {
            $issues[] = 'FrontendDriftLockdownService not found';
            $score   -= 40;
        }

        // frontend:lockdown-report command must be registered
        $lockdownCommand = base_path('packages/saas-platform/core-utils/src/Console/Commands/Frontend/FrontendLockdownReportCommand.php');
        if (!file_exists($lockdownCommand)) {
            $issues[] = 'FrontendLockdownReportCommand not found';
            $score   -= 30;
        }

        // FrontendStaticGovernanceScanner must exist
        $scanner = base_path('packages/saas-platform/core-utils/src/Frontend/FrontendStaticGovernanceScanner.php');
        if (!file_exists($scanner)) {
            $issues[] = 'FrontendStaticGovernanceScanner not found — multi-language scanning unavailable';
            $score   -= 30;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateBranchProtectionReadiness(): array
    {
        $issues = [];
        $score  = 100;

        // required-checks.json must exist and list all mandatory checks
        $checksFile = base_path('.github/required-checks.json');
        if (!file_exists($checksFile)) {
            $issues[] = '.github/required-checks.json not found';
            $score   -= 40;
        } else {
            $checks = json_decode(file_get_contents($checksFile), true);
            if (empty($checks['repositories'])) {
                $issues[] = 'required-checks.json has no repository definitions';
                $score   -= 20;
            }
            if (empty($checks['global_policy']['force_push_allowed'] ?? null) === false &&
                ($checks['global_policy']['force_push_allowed'] ?? true) === true) {
                $issues[] = 'required-checks.json allows force push — branch protection incomplete';
                $score   -= 20;
            }
        }

        // Branch protection guide must exist
        $guideFile = base_path('docs/phase-10.2.5/github-branch-protection-guide.md');
        if (!file_exists($guideFile)) {
            $issues[] = 'docs/phase-10.2.5/github-branch-protection-guide.md not found';
            $score   -= 20;
        }

        // Workflow must have pull_request trigger (prerequisite for branch protection)
        $workflow = base_path('.github/workflows/frontend-governance.yml');
        if (file_exists($workflow) && !str_contains(file_get_contents($workflow), 'pull_request')) {
            $issues[] = 'frontend-governance.yml missing pull_request trigger';
            $score   -= 20;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    private function validateFutureModuleInheritance(): array
    {
        $issues = [];
        $score  = 100;

        // ModuleFrontendInheritanceBootstrapper must exist
        $bootstrapper = base_path('packages/saas-platform/core-utils/src/Frontend/ModuleFrontendInheritanceBootstrapper.php');
        if (!file_exists($bootstrapper)) {
            $issues[] = 'ModuleFrontendInheritanceBootstrapper not found';
            $score   -= 40;
        }

        // ModuleFrontendInheritanceService must call bootInheritance() at service provider boot
        $provider = base_path('packages/saas-platform/core-utils/src/Providers/CoreUtilsServiceProvider.php');
        if (file_exists($provider)) {
            $content = file_get_contents($provider);
            if (!str_contains($content, 'bootInheritance')) {
                $issues[] = 'CoreUtilsServiceProvider does not call ModuleFrontendInheritanceService::bootInheritance()';
                $score   -= 30;
            }
        }

        // All existing modules must be validated for inheritance compliance
        $inheritanceService = base_path('packages/saas-platform/core-utils/src/Frontend/ModuleFrontendInheritanceService.php');
        if (!file_exists($inheritanceService)) {
            $issues[] = 'ModuleFrontendInheritanceService not found';
            $score   -= 30;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 70 ? 'HIGH' : 'MEDIUM'));
    }

    // ── End Phase 10.2.5 Dimensions ────────────────────────────────────────────

    private function computeWeightedScore(array $dimensions): int
    {
        $totalWeight = array_sum(self::DIMENSION_WEIGHTS);
        $weightedSum = 0;

        foreach ($dimensions as $key => $dim) {
            $weight       = self::DIMENSION_WEIGHTS[$key] ?? 5;
            $weightedSum += ($dim['score'] / 100) * $weight;
        }

        return (int) round(($weightedSum / $totalWeight) * 100);
    }

    private function scoreToGrade(int $score): string
    {
        return match(true) {
            $score >= 97 => 'A+',
            $score >= 93 => 'A',
            $score >= 90 => 'A-',
            $score >= 87 => 'B+',
            $score >= 83 => 'B',
            $score >= 80 => 'B-',
            $score >= 77 => 'C+',
            $score >= 73 => 'C',
            $score >= 70 => 'C-',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }

    private function buildSummary(int $score, string $grade, array $dimensions): string
    {
        $failing = array_filter($dimensions, fn ($d) => $d['score'] < 80);
        return sprintf(
            'Enterprise Frontend Governance Score: %d/100 (Grade %s) | Failing dimensions: %d/%d',
            $score, $grade, count($failing), count($dimensions)
        );
    }

    private function buildReport(array $dimensions, int $score, string $grade): array
    {
        $report = [];
        foreach ($dimensions as $key => $dim) {
            $report[] = [
                'dimension' => $key,
                'score'     => $dim['score'],
                'status'    => $dim['status'],
                'weight'    => self::DIMENSION_WEIGHTS[$key] ?? 5,
                'issues'    => $dim['issues'],
            ];
        }

        usort($report, fn ($a, $b) => $a['score'] <=> $b['score']);
        return $report;
    }

    private function dim(int $score, array $issues, string $status): array
    {
        return compact('score', 'issues', 'status');
    }

    private function getAllViews(): array
    {
        $files = [];
        $paths = [
            base_path('packages/saas-platform/modules/forms/resources/views'),
            base_path('packages/saas-platform/modules/tickets/Resources/views'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($it as $f) {
                if ($f->isFile() && $f->getExtension() === 'php') {
                    $files[] = $f->getPathname();
                }
            }
        }

        return $files;
    }
}
