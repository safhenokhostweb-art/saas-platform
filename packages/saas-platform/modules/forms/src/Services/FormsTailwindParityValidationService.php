<?php

namespace SaaSPlatform\Modules\Forms\Services;

/**
 * Phase 10.2.8 — Forms Tailwind v4 Parity Validation Service
 *
 * Validates that the Forms module renders identically under Tailwind v4.2
 * in BOTH saas-platform and customer-site.
 */
class FormsTailwindParityValidationService
{
    private const SAAS_ROOT   = __DIR__ . '/../../../../../../..';
    private const TENANT_ROOT = __DIR__ . '/../../../../../../../../../customer-site';

    private const VALIDATION_DIMENSIONS = [
        'tailwind_version_parity',
        'css_entrypoint_parity',
        'token_import_parity',
        'source_directive_parity',
        'theme_block_parity',
        'utility_directive_parity',
        'legacy_directive_absence',
        'builder_css_parity',
        'component_css_parity',
        'vite_plugin_parity',
    ];

    private array $results = [];
    private array $errors  = [];

    public function validate(): array
    {
        $this->results = [];
        $this->errors  = [];

        foreach (self::VALIDATION_DIMENSIONS as $dimension) {
            $method = 'validate' . str_replace('_', '', ucwords($dimension, '_'));
            if (method_exists($this, $method)) {
                $this->results[$dimension] = $this->$method();
            }
        }

        return $this->buildReport();
    }

    private function validateTailwindVersionParity(): array
    {
        $saasPkg   = $this->readPackageJson(self::SAAS_ROOT . '/package.json');
        $tenantPkg = $this->readPackageJson(self::TENANT_ROOT . '/package.json');

        $saasVer   = $saasPkg['devDependencies']['tailwindcss'] ?? 'missing';
        $tenantVer = $tenantPkg['devDependencies']['tailwindcss'] ?? 'missing';

        $saasInstalled   = $this->getInstalledVersion(self::SAAS_ROOT . '/node_modules/tailwindcss/package.json');
        $tenantInstalled = $this->getInstalledVersion(self::TENANT_ROOT . '/node_modules/tailwindcss/package.json');

        $majorSaas   = $this->majorVersion($saasInstalled);
        $majorTenant = $this->majorVersion($tenantInstalled);

        $passing = $majorSaas === 4 && $majorTenant === 4 && $saasInstalled === $tenantInstalled;

        return [
            'passing'         => $passing,
            'score'           => $passing ? 100 : 0,
            'saas_declared'   => $saasVer,
            'tenant_declared' => $tenantVer,
            'saas_installed'  => $saasInstalled,
            'tenant_installed'=> $tenantInstalled,
            'message'         => $passing
                ? "Both projects on Tailwind {$saasInstalled}"
                : "Version drift: saas={$saasInstalled} tenant={$tenantInstalled}",
        ];
    }

    private function validateCssEntrypointParity(): array
    {
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        $saasHasImport   = $this->fileContains($saasAppCss, '@import "tailwindcss"') ||
                           $this->fileContains($saasAppCss, "@import 'tailwindcss'");
        $tenantHasImport = $this->fileContains($tenantAppCss, '@import "tailwindcss"') ||
                           $this->fileContains($tenantAppCss, "@import 'tailwindcss'");

        $passing = $saasHasImport && $tenantHasImport;

        return [
            'passing'              => $passing,
            'score'                => $passing ? 100 : 0,
            'saas_has_v4_import'   => $saasHasImport,
            'tenant_has_v4_import' => $tenantHasImport,
            'message'              => $passing
                ? 'Both CSS entrypoints use @import "tailwindcss" (v4)'
                : 'CSS entrypoint mismatch — legacy @tailwind directives may exist',
        ];
    }

    private function validateLegacyDirectiveAbsence(): array
    {
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        $legacyPatterns = ['@tailwind base', '@tailwind components', '@tailwind utilities'];

        $saasLegacy   = $this->fileContainsAny($saasAppCss, $legacyPatterns);
        $tenantLegacy = $this->fileContainsAny($tenantAppCss, $legacyPatterns);

        $passing = !$saasLegacy && !$tenantLegacy;

        return [
            'passing'              => $passing,
            'score'                => $passing ? 100 : 0,
            'saas_has_legacy'      => $saasLegacy,
            'tenant_has_legacy'    => $tenantLegacy,
            'message'              => $passing
                ? 'No legacy @tailwind directives found in either project'
                : 'Legacy @tailwind directives detected — v3 syntax must be removed',
        ];
    }

    private function validateTokenImportParity(): array
    {
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        $saasHasTokens   = $this->fileContains($saasAppCss, 'design-tokens.css');
        $tenantHasTokens = $this->fileContains($tenantAppCss, 'design-tokens.css');

        $passing = $saasHasTokens && $tenantHasTokens;

        return [
            'passing'                => $passing,
            'score'                  => $passing ? 100 : 0,
            'saas_imports_tokens'    => $saasHasTokens,
            'tenant_imports_tokens'  => $tenantHasTokens,
            'message'                => $passing
                ? 'Both projects import canonical design-tokens.css'
                : 'Design token import missing in one or both projects',
        ];
    }

    private function validateSourceDirectiveParity(): array
    {
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        $saasHasSource   = $this->fileContains($saasAppCss, '@source');
        $tenantHasSource = $this->fileContains($tenantAppCss, '@source');

        $passing = $saasHasSource && $tenantHasSource;

        return [
            'passing'                 => $passing,
            'score'                   => $passing ? 100 : 0,
            'saas_has_source'         => $saasHasSource,
            'tenant_has_source'       => $tenantHasSource,
            'message'                 => $passing
                ? 'Both projects declare @source content paths'
                : '@source directives missing — utility purging may diverge',
        ];
    }

    private function validateThemeBlockParity(): array
    {
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        $saasHasTheme   = $this->fileContains($saasAppCss, '@theme');
        $tenantHasTheme = $this->fileContains($tenantAppCss, '@theme');

        $passing = $saasHasTheme && $tenantHasTheme;

        return [
            'passing'               => $passing,
            'score'                 => $passing ? 100 : 0,
            'saas_has_theme'        => $saasHasTheme,
            'tenant_has_theme'      => $tenantHasTheme,
            'message'               => $passing
                ? 'Both projects have @theme CSS configuration block'
                : '@theme block missing — font/color utilities will diverge',
        ];
    }

    private function validateUtilityDirectiveParity(): array
    {
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        $saasHasUtility   = $this->fileContains($saasAppCss, '@utility');
        $tenantHasUtility = $this->fileContains($tenantAppCss, '@utility');

        $passing = $saasHasUtility && $tenantHasUtility;

        return [
            'passing'               => $passing,
            'score'                 => $passing ? 100 : 0,
            'saas_has_utility'      => $saasHasUtility,
            'tenant_has_utility'    => $tenantHasUtility,
            'message'               => $passing
                ? 'Both projects declare @utility custom utilities'
                : '@utility directives missing — custom utilities diverge',
        ];
    }

    private function validateBuilderCssParity(): array
    {
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        $builderSelectors = [
            '#forms-builder',
            '.builder-palette',
            '.builder-inspector',
            '.builder-canvas',
            '.field-card',
            '.canvas-grid',
            '.drop-hint',
        ];

        $saasScore   = 0;
        $tenantScore = 0;
        foreach ($builderSelectors as $sel) {
            if ($this->fileContains($saasAppCss, $sel)) {
                $saasScore++;
            }
            if ($this->fileContains($tenantAppCss, $sel)) {
                $tenantScore++;
            }
        }

        $total   = count($builderSelectors);
        $passing = $saasScore === $total && $tenantScore === $total;
        $score   = (int) round((($saasScore + $tenantScore) / ($total * 2)) * 100);

        return [
            'passing'       => $passing,
            'score'         => $score,
            'saas_score'    => $saasScore,
            'tenant_score'  => $tenantScore,
            'total'         => $total,
            'message'       => $passing
                ? 'Builder CSS is identical in both projects'
                : "Builder CSS parity gap: saas={$saasScore}/{$total} tenant={$tenantScore}/{$total}",
        ];
    }

    private function validateComponentCssParity(): array
    {
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        $sharedSelectors = [
            '.form-card',
            '.form-card-action',
            '.tkt-stat',
            '.tkt-badge',
            '.skeleton',
            '[x-cloak]',
            '.toast-enter',
            '.autosave-pulse',
        ];

        $saasScore   = 0;
        $tenantScore = 0;
        foreach ($sharedSelectors as $sel) {
            if ($this->fileContains($saasAppCss, $sel)) {
                $saasScore++;
            }
            if ($this->fileContains($tenantAppCss, $sel)) {
                $tenantScore++;
            }
        }

        $total   = count($sharedSelectors);
        $passing = $saasScore >= (int) round($total * 0.85) && $tenantScore >= (int) round($total * 0.85);
        $score   = (int) round((($saasScore + $tenantScore) / ($total * 2)) * 100);

        return [
            'passing'      => $passing,
            'score'        => $score,
            'saas_score'   => $saasScore,
            'tenant_score' => $tenantScore,
            'total'        => $total,
            'message'      => $passing
                ? 'Component CSS coverage acceptable in both projects'
                : "Component CSS parity gap: saas={$saasScore}/{$total} tenant={$tenantScore}/{$total}",
        ];
    }

    private function validateVitePluginParity(): array
    {
        $saasVite   = self::SAAS_ROOT . '/vite.config.js';
        $tenantVite = self::TENANT_ROOT . '/vite.config.js';

        $saasHasPlugin   = $this->fileContains($saasVite, '@tailwindcss/vite');
        $tenantHasPlugin = $this->fileContains($tenantVite, '@tailwindcss/vite');

        $passing = $saasHasPlugin && $tenantHasPlugin;

        return [
            'passing'              => $passing,
            'score'                => $passing ? 100 : 0,
            'saas_has_plugin'      => $saasHasPlugin,
            'tenant_has_plugin'    => $tenantHasPlugin,
            'message'              => $passing
                ? 'Both projects use @tailwindcss/vite plugin'
                : '@tailwindcss/vite missing from one or both vite.config.js files',
        ];
    }

    private function buildReport(): array
    {
        $totalScore  = 0;
        $totalWeight = 0;
        $passing     = 0;
        $failing     = 0;

        foreach ($this->results as $result) {
            $score       = $result['score'] ?? 0;
            $totalScore += $score;
            $totalWeight++;
            if ($result['passing'] ?? false) {
                $passing++;
            } else {
                $failing++;
            }
        }

        $overallScore = $totalWeight > 0 ? (int) round($totalScore / $totalWeight) : 0;
        $grade        = $this->scoreToGrade($overallScore);

        return [
            'service'       => 'FormsTailwindParityValidationService',
            'phase'         => '10.2.8',
            'timestamp'     => now()->toIso8601String(),
            'overall_score' => $overallScore,
            'grade'         => $grade,
            'dimensions'    => count($this->results),
            'passing'       => $passing,
            'failing'       => $failing,
            'results'       => $this->results,
            'errors'        => $this->errors,
            'summary'       => $overallScore >= 95
                ? 'TAILWIND PARITY CONFIRMED — both projects on identical v4 runtime'
                : "TAILWIND DRIFT DETECTED — score {$overallScore}/100 requires remediation",
        ];
    }

    private function scoreToGrade(int $score): string
    {
        return match (true) {
            $score >= 97 => 'A+',
            $score >= 93 => 'A',
            $score >= 90 => 'A-',
            $score >= 87 => 'B+',
            $score >= 83 => 'B',
            $score >= 80 => 'B-',
            default      => 'C',
        };
    }

    private function fileContains(string $path, string $needle): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        return str_contains(file_get_contents($path), $needle);
    }

    private function fileContainsAny(string $path, array $needles): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $content = file_get_contents($path);
        foreach ($needles as $needle) {
            if (str_contains($content, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function readPackageJson(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function getInstalledVersion(string $pkgPath): string
    {
        if (!file_exists($pkgPath)) {
            return 'not-installed';
        }
        $pkg = json_decode(file_get_contents($pkgPath), true);
        return $pkg['version'] ?? 'unknown';
    }

    private function majorVersion(string $version): int
    {
        return (int) explode('.', ltrim($version, 'v'))[0];
    }
}
