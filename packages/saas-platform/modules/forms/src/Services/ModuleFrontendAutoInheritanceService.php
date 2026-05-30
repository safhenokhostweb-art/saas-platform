<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;

/**
 * Phase 10.2.6 — Module Frontend Auto-Inheritance Service
 *
 * Ensures all existing and future modules automatically inherit frontend governance:
 *  - Verifies customer-site app.css imports design-tokens.css
 *  - Verifies @source directives exist for each module's view directory
 *  - Verifies [x-cloak] FOUC guard is present
 *  - Verifies Vazirmatn font and --font-sans token
 *  - Reports which modules are "governed" (parity-safe) and which need intervention
 *
 * This service is the enforcement layer for the architecture rule:
 * "The frontend of ALL modules MUST be structurally identical between saas-platform and customer-site."
 */
class ModuleFrontendAutoInheritanceService
{
    private const MODULES_BASE = '/packages/saas-platform/modules/';
    private const CUSTOMER_CSS = '/resources/css/app.css';
    private const DESIGN_TOKENS_IMPORT = 'design-tokens.css';
    private const X_CLOAK_RULE = '[x-cloak]{display:none!important}';
    private const FONT_TOKEN = '--font-sans';

    /** @var array<string, array{governed: bool, issues: string[]}> */
    private array $report = [];

    public function audit(): array
    {
        $this->report = [];

        $tenantCssPath = $this->customerSitePath(self::CUSTOMER_CSS);

        if (! file_exists($tenantCssPath)) {
            return [
                'error'  => 'customer-site app.css not found at ' . $tenantCssPath,
                'report' => [],
            ];
        }

        $tenantCss = file_get_contents($tenantCssPath);

        // ── Global governance checks ──────────────────────────────────────
        $this->auditGlobalCssRule('design_tokens_import', $tenantCss, self::DESIGN_TOKENS_IMPORT,
            'design-tokens.css import',
            'design-tokens.css NOT imported — all --ui-* tokens will be undefined in customer-site');

        $this->auditGlobalCssRule('x_cloak_fouc_guard', $tenantCss, self::X_CLOAK_RULE,
            '[x-cloak] FOUC guard',
            '[x-cloak]{display:none!important} MISSING — Alpine FOUC will occur on every page load');

        $this->auditGlobalCssRule('font_token', $tenantCss, self::FONT_TOKEN,
            'Vazirmatn --font-sans token',
            '--font-sans token not defined — typography will diverge from saas-platform');

        // ── Per-module @source directive checks ───────────────────────────
        $modulesPath = base_path(ltrim(self::MODULES_BASE, '/'));

        if (is_dir($modulesPath)) {
            foreach (File::directories($modulesPath) as $moduleDir) {
                $moduleName = basename($moduleDir);
                $viewsPath  = $moduleDir . '/resources/views';

                if (! is_dir($viewsPath)) {
                    continue;
                }

                $expectedSource = 'modules/' . $moduleName . '/resources/views';
                $hasSource      = str_contains($tenantCss, $expectedSource);
                $issues         = [];

                if (! $hasSource) {
                    $issues[] = "@source directive missing for {$moduleName} views — Tailwind will purge module CSS in production build";
                }

                // Check module-specific CSS classes are mirrored
                $saasCssPath = base_path('resources/css/app.css');
                if (file_exists($saasCssPath)) {
                    $mismatches = $this->findMissingModuleClasses($moduleName, $saasCssPath, $tenantCss);
                    foreach ($mismatches as $cls) {
                        $issues[] = "CSS class {$cls} defined in saas-platform but missing in customer-site";
                    }
                }

                $this->report["module_{$moduleName}"] = [
                    'governed' => empty($issues),
                    'issues'   => $issues,
                ];
            }
        }

        $governed  = count(array_filter($this->report, fn ($r) => $r['governed']));
        $total     = count($this->report);
        $critical  = $this->collectCriticalIssues();

        return [
            'governed'  => $governed,
            'total'     => $total,
            'score'     => $total > 0 ? (int) round($governed / $total * 100) : 100,
            'critical'  => $critical,
            'report'    => $this->report,
            'compliant' => empty($critical),
        ];
    }

    /**
     * Retrofit: write missing @source directives and [x-cloak] rule into customer-site app.css.
     * Returns a list of changes made.
     */
    public function retrofit(bool $dryRun = false): array
    {
        $tenantCssPath = $this->customerSitePath(self::CUSTOMER_CSS);

        if (! file_exists($tenantCssPath)) {
            return ['error' => 'customer-site app.css not found'];
        }

        $original = file_get_contents($tenantCssPath);
        $content  = $original;
        $changes  = [];

        // Ensure x-cloak rule
        if (! str_contains($content, self::X_CLOAK_RULE)) {
            $content  .= "\n" . self::X_CLOAK_RULE . "\n";
            $changes[] = 'Appended [x-cloak] FOUC guard';
        }

        // Ensure @source directives for all module view directories
        $modulesPath = base_path(ltrim(self::MODULES_BASE, '/'));
        if (is_dir($modulesPath)) {
            foreach (File::directories($modulesPath) as $moduleDir) {
                $moduleName = basename($moduleDir);
                $viewsPath  = $moduleDir . '/resources/views';

                if (! is_dir($viewsPath)) {
                    continue;
                }

                $expectedSource = "modules/{$moduleName}/resources/views";
                if (! str_contains($content, $expectedSource)) {
                    // Insert before the last @theme block or append at end of @source section
                    $directive = "\n@source '../../../saas-platform/packages/saas-platform/{$expectedSource}/**/*.blade.php';";
                    $content  .= $directive;
                    $changes[] = "Added @source directive for {$moduleName}";
                }
            }
        }

        if (empty($changes)) {
            return ['changes' => [], 'message' => 'customer-site app.css already fully governed — no changes needed'];
        }

        if (! $dryRun) {
            file_put_contents($tenantCssPath, $content);
        }

        return [
            'dry_run' => $dryRun,
            'changes' => $changes,
            'message' => $dryRun
                ? 'DRY RUN — would apply ' . count($changes) . ' change(s)'
                : 'Applied ' . count($changes) . ' change(s) to customer-site app.css',
        ];
    }

    // ─────────────────────── Helpers ───────────────────────

    private function auditGlobalCssRule(string $key, string $css, string $needle, string $label, string $failMsg): void
    {
        $has    = str_contains($css, $needle);
        $issues = $has ? [] : [$failMsg];

        $this->report[$key] = [
            'governed' => $has,
            'issues'   => $issues,
        ];
    }

    /** @return string[] */
    private function findMissingModuleClasses(string $moduleName, string $saasCssPath, string $tenantCss): array
    {
        // Only check well-known module-prefixed CSS classes that have a naming convention
        $patterns = [
            'forms'   => ['.form-card', '.builder-palette', '.builder-canvas', '#forms-builder'],
            'tickets' => ['.ticket-card', '.ticket-badge', '.ticket-timeline'],
        ];

        if (! isset($patterns[$moduleName])) {
            return [];
        }

        $saasCss = file_get_contents($saasCssPath);
        $missing = [];

        foreach ($patterns[$moduleName] as $cls) {
            if (str_contains($saasCss, $cls) && ! str_contains($tenantCss, $cls)) {
                $missing[] = $cls;
            }
        }

        return $missing;
    }

    private function collectCriticalIssues(): array
    {
        $critical = [];
        foreach ($this->report as $key => $item) {
            if (! $item['governed']) {
                foreach ($item['issues'] as $issue) {
                    $critical[] = "[{$key}] {$issue}";
                }
            }
        }
        return $critical;
    }

    private function customerSitePath(string $relative): string
    {
        return base_path('../../customer-site' . $relative);
    }
}
