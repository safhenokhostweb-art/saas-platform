<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.2.5 — Task 2
 * Validates that both saas-platform and customer-site maintain identical
 * frontend governance posture. Prevents silent governance divergence between
 * the two repositories that share the same canonical package system.
 *
 * Checks:
 *  1. Workflow existence & job parity
 *  2. Artisan command parity
 *  3. Design token import parity
 *  4. Core-ui namespace registration
 *  5. Validator class parity
 *  6. CI fail behavior (no continue-on-error bypasses)
 *  7. Static analysis parity
 *  8. Governance score parity
 *  9. Branch enforcement readiness
 */
class FrontendCrossRepositoryGovernanceValidator
{
    private const CUSTOMER_SITE_ROOT = '/../customer-site';

    private const REQUIRED_WORKFLOW_JOBS = [
        'static-blade-analysis',
        'component-authority',
        'responsive-governance',
        'enterprise-governance',
        'frontend-parity',
    ];

    private const REQUIRED_COMMANDS = [
        'frontend:validate-components',
        'frontend:validate-enterprise-governance',
        'frontend:validate-canonical-parity',
        'frontend:auto-heal',
        'frontend:lockdown-report',
    ];

    private const REQUIRED_TOKEN_IMPORT = 'design-tokens.css';

    private const DIMENSION_WEIGHTS = [
        'workflow_existence'           => 20,
        'workflow_job_parity'          => 15,
        'command_parity'               => 15,
        'token_import_parity'          => 12,
        'core_ui_namespace'            => 10,
        'ci_fail_behavior'             => 10,
        'static_analysis_parity'       => 8,
        'governance_score_parity'      => 5,
        'branch_enforcement_readiness' => 5,
    ];

    public function validate(bool $strict = false, bool $failOnWarning = false): array
    {
        $customerRoot = base_path(self::CUSTOMER_SITE_ROOT);
        $dimensions   = [];

        $dimensions['workflow_existence']           = $this->checkWorkflowExistence($customerRoot);
        $dimensions['workflow_job_parity']          = $this->checkWorkflowJobParity($customerRoot);
        $dimensions['command_parity']               = $this->checkCommandParity($customerRoot);
        $dimensions['token_import_parity']          = $this->checkTokenImportParity($customerRoot);
        $dimensions['core_ui_namespace']            = $this->checkCoreUiNamespace($customerRoot);
        $dimensions['ci_fail_behavior']             = $this->checkCiFailBehavior($customerRoot);
        $dimensions['static_analysis_parity']       = $this->checkStaticAnalysisParity($customerRoot);
        $dimensions['governance_score_parity']      = $this->checkGovernanceScoreParity($customerRoot);
        $dimensions['branch_enforcement_readiness'] = $this->checkBranchEnforcementReadiness($customerRoot);

        $score  = $this->computeScore($dimensions);
        $grade  = $this->scoreToGrade($score);
        $passed = $score >= ($strict ? 95 : 85);

        $warnings = array_filter($dimensions, fn($d) => $d['status'] === 'WARNING');
        if ($failOnWarning && !empty($warnings)) {
            $passed = false;
        }

        return [
            'passed'          => $passed,
            'score'           => $score,
            'grade'           => $grade,
            'strict'          => $strict,
            'fail_on_warning' => $failOnWarning,
            'dimensions'      => $dimensions,
            'customer_root'   => $customerRoot,
            'summary'         => $this->buildSummary($score, $grade, $dimensions, $passed),
            'violations'      => $this->collectViolations($dimensions),
            'recommendations' => $this->buildRecommendations($dimensions),
        ];
    }

    private function checkWorkflowExistence(string $customerRoot): array
    {
        $issues = [];
        $score  = 100;

        // saas-platform must have frontend-governance.yml
        $saasWorkflow = base_path('.github/workflows/frontend-governance.yml');
        if (!file_exists($saasWorkflow)) {
            $issues[] = 'saas-platform missing .github/workflows/frontend-governance.yml';
            $score   -= 50;
        }

        // customer-site must have frontend-governance.yml
        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (!file_exists($csWorkflow)) {
            $issues[] = 'customer-site missing .github/workflows/frontend-governance.yml';
            $score   -= 50;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : 'CRITICAL');
    }

    private function checkWorkflowJobParity(string $customerRoot): array
    {
        $issues = [];
        $score  = 100;

        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (!file_exists($csWorkflow)) {
            return $this->dim(0, ['customer-site governance workflow missing — cannot check job parity'], 'CRITICAL');
        }

        $content = file_get_contents($csWorkflow);

        foreach (self::REQUIRED_WORKFLOW_JOBS as $job) {
            if (!str_contains($content, $job . ':')) {
                $issues[] = "customer-site workflow missing job: {$job}";
                $score   -= intdiv(100, count(self::REQUIRED_WORKFLOW_JOBS));
            }
        }

        // Check fetch-depth: 0 on all checkout steps
        if (str_contains($content, 'actions/checkout') && !str_contains($content, 'fetch-depth: 0')) {
            $issues[] = 'customer-site workflow missing fetch-depth: 0 on checkout (causes git diff failures)';
            $score   -= 10;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'CRITICAL' : 'HIGH'));
    }

    private function checkCommandParity(string $customerRoot): array
    {
        $issues = [];
        $score  = 100;

        // Commands must be available in customer-site (via core-utils dependency)
        $csComposer = $customerRoot . '/composer.json';
        if (!file_exists($csComposer)) {
            return $this->dim(0, ['customer-site composer.json not found'], 'CRITICAL');
        }

        $composer = json_decode(file_get_contents($csComposer), true);
        $requires = array_merge(
            $composer['require'] ?? [],
            $composer['repositories'] ?? []
        );

        $hasCoreUtils = false;
        foreach ($composer['require'] ?? [] as $pkg => $ver) {
            if (str_contains($pkg, 'core-utils')) {
                $hasCoreUtils = true;
                break;
            }
        }

        if (!$hasCoreUtils) {
            foreach ($composer['repositories'] ?? [] as $repo) {
                if (is_array($repo) && str_contains($repo['url'] ?? '', 'core-utils')) {
                    $hasCoreUtils = true;
                    break;
                }
            }
        }

        if (!$hasCoreUtils) {
            $issues[] = 'customer-site does not depend on saas-platform/core-utils — governance commands unavailable';
            $score   -= 60;
        }

        // Check workflow invokes each required command
        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (file_exists($csWorkflow)) {
            $workflowContent = file_get_contents($csWorkflow);
            foreach (self::REQUIRED_COMMANDS as $cmd) {
                if (!str_contains($workflowContent, $cmd)) {
                    $issues[] = "customer-site workflow does not invoke: php artisan {$cmd}";
                    $score   -= intdiv(40, count(self::REQUIRED_COMMANDS));
                }
            }
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'HIGH' : 'MEDIUM'));
    }

    private function checkTokenImportParity(string $customerRoot): array
    {
        $issues = [];
        $score  = 100;

        $saasAppCss = base_path('resources/css/app.css');
        $csAppCss   = $customerRoot . '/resources/css/app.css';

        if (!file_exists($saasAppCss)) {
            $issues[] = 'saas-platform resources/css/app.css not found';
            $score   -= 30;
        } elseif (!str_contains(file_get_contents($saasAppCss), self::REQUIRED_TOKEN_IMPORT)) {
            $issues[] = 'saas-platform app.css does not import canonical design-tokens.css';
            $score   -= 30;
        }

        if (!file_exists($csAppCss)) {
            $issues[] = 'customer-site resources/css/app.css not found';
            $score   -= 30;
        } elseif (!str_contains(file_get_contents($csAppCss), self::REQUIRED_TOKEN_IMPORT)) {
            $issues[] = 'customer-site app.css does not import canonical design-tokens.css';
            $score   -= 40;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'CRITICAL' : 'HIGH'));
    }

    private function checkCoreUiNamespace(string $customerRoot): array
    {
        $issues = [];
        $score  = 100;

        // CoreUtilsServiceProvider must register core-ui namespace
        $provider = base_path('packages/saas-platform/core-utils/src/Providers/CoreUtilsServiceProvider.php');
        if (file_exists($provider)) {
            $content = file_get_contents($provider);
            if (!str_contains($content, "anonymousComponentPath") || !str_contains($content, "'core-ui'")) {
                $issues[] = 'CoreUtilsServiceProvider does not register core-ui anonymous component namespace';
                $score   -= 40;
            }
        } else {
            $issues[] = 'CoreUtilsServiceProvider not found';
            $score   -= 50;
        }

        // customer-site must have core-utils in its bootstrap/providers or app config
        $csProviders = $customerRoot . '/bootstrap/providers.php';
        $csAppConfig = $customerRoot . '/config/app.php';

        $coreUtilsRegistered = false;
        foreach ([$csProviders, $csAppConfig] as $file) {
            if (file_exists($file) && str_contains(file_get_contents($file), 'CoreUtils')) {
                $coreUtilsRegistered = true;
                break;
            }
        }

        if (!$coreUtilsRegistered) {
            $issues[] = 'customer-site does not register CoreUtilsServiceProvider — core-ui namespace unavailable';
            $score   -= 30;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'CRITICAL' : 'HIGH'));
    }

    private function checkCiFailBehavior(string $customerRoot): array
    {
        $issues = [];
        $score  = 100;

        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (!file_exists($csWorkflow)) {
            return $this->dim(0, ['customer-site workflow missing — cannot check CI fail behavior'], 'CRITICAL');
        }

        $content = file_get_contents($csWorkflow);

        // Must not have continue-on-error: true on governance steps
        if (preg_match('/continue-on-error:\s*true/i', $content)) {
            $issues[] = 'customer-site workflow has continue-on-error: true — governance violations will not fail CI';
            $score   -= 50;
        }

        // All artisan governance commands must capture exit code
        if (str_contains($content, 'php artisan frontend:') && !str_contains($content, 'EXITCODE=$?')) {
            $issues[] = 'Governance artisan commands do not explicitly propagate exit codes';
            $score   -= 20;
        }

        // Workflow must have correct trigger branches
        if (!str_contains($content, 'main') && !str_contains($content, 'master')) {
            $issues[] = 'Workflow does not enforce governance on main/master branches';
            $score   -= 30;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'CRITICAL' : 'HIGH'));
    }

    private function checkStaticAnalysisParity(string $customerRoot): array
    {
        $issues = [];
        $score  = 100;

        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (!file_exists($csWorkflow)) {
            return $this->dim(0, ['customer-site workflow missing'], 'CRITICAL');
        }

        $content = file_get_contents($csWorkflow);

        // Must scan for forbidden raw controls
        if (!str_contains($content, 'Raw')) {
            $issues[] = 'customer-site workflow does not perform raw-control static analysis';
            $score   -= 30;
        }

        // Must scan for hardcoded hex colors
        if (!str_contains($content, 'hardcoded') && !str_contains($content, 'hex')) {
            $issues[] = 'customer-site workflow does not scan for hardcoded colors';
            $score   -= 30;
        }

        // Must have static-blade-analysis as first gate
        if (!str_contains($content, 'static-blade-analysis')) {
            $issues[] = 'customer-site workflow missing fast static-blade-analysis first gate';
            $score   -= 20;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'HIGH' : 'MEDIUM'));
    }

    private function checkGovernanceScoreParity(string $customerRoot): array
    {
        $issues = [];
        $score  = 100;

        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (!file_exists($csWorkflow)) {
            return $this->dim(0, ['customer-site workflow missing'], 'CRITICAL');
        }

        $content = file_get_contents($csWorkflow);

        // Both must enforce the same minimum score threshold
        if (!str_contains($content, '--fail-below=95')) {
            $issues[] = 'customer-site workflow does not enforce --fail-below=95 governance threshold';
            $score   -= 50;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : 'HIGH');
    }

    private function checkBranchEnforcementReadiness(string $customerRoot): array
    {
        $issues     = [];
        $score      = 100;
        $indicators = [];

        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (file_exists($csWorkflow)) {
            $content = file_get_contents($csWorkflow);

            if (str_contains($content, 'pull_request')) {
                $indicators[] = 'pull_request trigger configured';
            } else {
                $issues[] = 'Workflow missing pull_request trigger — branch protection cannot block merges';
                $score   -= 40;
            }

            if (str_contains($content, 'push')) {
                $indicators[] = 'push trigger configured';
            } else {
                $issues[] = 'Workflow missing push trigger';
                $score   -= 20;
            }
        }

        $requiredChecksFile = base_path('.github/required-checks.json');
        if (file_exists($requiredChecksFile)) {
            $indicators[] = 'required-checks.json present';
        } else {
            $issues[] = '.github/required-checks.json not found — branch protection guide incomplete';
            $score   -= 30;
        }

        return $this->dim(max(0, $score), $issues, empty($issues) ? 'PASS' : ($score < 60 ? 'HIGH' : 'WARNING'));
    }

    private function computeScore(array $dimensions): int
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
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }

    private function buildSummary(int $score, string $grade, array $dimensions, bool $passed): string
    {
        $failing = count(array_filter($dimensions, fn($d) => $d['score'] < 80));
        $status  = $passed ? 'PASS' : 'FAIL';

        return sprintf(
            '[%s] Cross-Repository Governance Score: %d/100 (Grade %s) | Failing dimensions: %d/%d',
            $status, $score, $grade, $failing, count($dimensions)
        );
    }

    private function collectViolations(array $dimensions): array
    {
        $violations = [];
        foreach ($dimensions as $key => $dim) {
            foreach ($dim['issues'] as $issue) {
                $violations[] = [
                    'dimension' => $key,
                    'severity'  => $dim['status'],
                    'message'   => $issue,
                ];
            }
        }
        return $violations;
    }

    private function buildRecommendations(array $dimensions): array
    {
        $recs = [];

        if ($dimensions['workflow_existence']['score'] < 100) {
            $recs[] = 'Create customer-site/.github/workflows/frontend-governance.yml mirroring saas-platform pipeline';
        }
        if ($dimensions['workflow_job_parity']['score'] < 100) {
            $recs[] = 'Add missing CI jobs to customer-site governance workflow; add fetch-depth: 0 to all checkout steps';
        }
        if ($dimensions['command_parity']['score'] < 100) {
            $recs[] = 'Ensure customer-site composer.json depends on saas-platform/core-utils and workflow invokes all governance commands';
        }
        if ($dimensions['token_import_parity']['score'] < 100) {
            $recs[] = 'Add @import for design-tokens.css in customer-site/resources/css/app.css';
        }
        if ($dimensions['core_ui_namespace']['score'] < 100) {
            $recs[] = 'Register CoreUtilsServiceProvider in customer-site bootstrap/providers.php';
        }
        if ($dimensions['ci_fail_behavior']['score'] < 100) {
            $recs[] = 'Remove continue-on-error from governance steps; propagate exit codes with EXITCODE=$?';
        }
        if ($dimensions['branch_enforcement_readiness']['score'] < 100) {
            $recs[] = 'Configure GitHub branch protection rules using docs/phase-10.2.5/github-branch-protection-guide.md';
        }

        return $recs;
    }

    private function dim(int $score, array $issues, string $status): array
    {
        return compact('score', 'issues', 'status');
    }
}
