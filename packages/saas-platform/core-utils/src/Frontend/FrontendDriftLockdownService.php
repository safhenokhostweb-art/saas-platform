<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.2.5 — Task 5
 * Drift Lockdown Service.
 *
 * Detects all forms of frontend governance drift and provides
 * canonical replacement suggestions. This is the authoritative
 * source for drift classification and lockdown recommendations.
 *
 * Detects:
 *  - Unauthorized component usage
 *  - Frontend divergence between saas-platform and customer-site
 *  - Module-local style systems
 *  - Duplicate UI systems
 *  - Runtime drift (dynamic style injection)
 *  - Auto-suggests canonical replacements
 */
class FrontendDriftLockdownService
{
    private const CUSTOMER_SITE_ROOT = '/../customer-site';

    private const DRIFT_CATEGORIES = [
        'unauthorized_components',
        'frontend_divergence',
        'module_local_styles',
        'duplicate_ui_systems',
        'runtime_drift',
        'token_bypass',
    ];

    private const CANONICAL_REPLACEMENTS = [
        '<button'     => 'x-core-ui::button',
        '<input'      => 'x-core-ui::input',
        '<select'     => 'x-core-ui::select',
        '<textarea'   => 'x-core-ui::textarea',
        '<table'      => 'x-core-ui::data-table',
        'window.alert'   => 'x-core-ui::alert (toast)',
        'window.confirm' => 'x-core-ui::confirm-modal',
    ];

    public function generateLockdownReport(bool $strict = false): array
    {
        $driftItems  = [];
        $saasRoot    = base_path();
        $customerRoot = base_path(self::CUSTOMER_SITE_ROOT);

        $driftItems = array_merge(
            $driftItems,
            $this->detectUnauthorizedComponents($saasRoot),
            $this->detectUnauthorizedComponents($customerRoot, 'customer-site'),
            $this->detectFrontendDivergence($saasRoot, $customerRoot),
            $this->detectModuleLocalStyles($saasRoot),
            $this->detectDuplicateUiSystems($saasRoot),
            $this->detectDuplicateUiSystems($customerRoot, 'customer-site'),
            $this->detectRuntimeDrift($saasRoot)
        );

        $bySeverity = $this->groupBySeverity($driftItems);
        $byCategory = $this->groupByCategory($driftItems);
        $score      = $this->computeLockdownScore($driftItems);
        $passed     = $strict
            ? (count($driftItems) === 0)
            : (($bySeverity['CRITICAL'] ?? 0) === 0 && ($bySeverity['HIGH'] ?? 0) === 0);

        return [
            'passed'       => $passed,
            'score'        => $score,
            'total_drift'  => count($driftItems),
            'by_severity'  => $bySeverity,
            'by_category'  => $byCategory,
            'items'        => $driftItems,
            'replacements' => $this->buildReplacementMap($driftItems),
            'summary'      => $this->buildSummary($score, count($driftItems), $passed),
        ];
    }

    private function detectUnauthorizedComponents(string $root, string $context = 'saas-platform'): array
    {
        $items     = [];
        $viewPaths = [
            $root . '/packages/saas-platform/modules',
            $root . '/resources/views',
        ];

        if ($context === 'customer-site') {
            $viewPaths = [$root . '/resources/views'];
        }

        foreach ($viewPaths as $viewPath) {
            if (!is_dir($viewPath)) continue;

            $files = $this->getAllBladeFiles($viewPath);
            foreach ($files as $file) {
                // Skip core-ui component definitions
                if (str_contains($file, 'core-utils/resources/views/components/')) continue;

                $content = @file_get_contents($file) ?: '';
                $rel     = $this->relativePath($file, $root);

                foreach (self::CANONICAL_REPLACEMENTS as $raw => $canonical) {
                    if (str_starts_with($raw, '<') && str_contains($content, $raw)) {
                        // Exclude Alpine x-for template contexts
                        if (str_contains($content, '<template x-for')) continue;
                        $items[] = $this->driftItem(
                            'unauthorized_components',
                            'CRITICAL',
                            "[{$context}] {$rel}",
                            "Uses raw `{$raw}` — unauthorized outside core-ui",
                            "Replace with <x-{$canonical} />"
                        );
                    }
                    if (str_starts_with($raw, 'window.') && str_contains($content, $raw)) {
                        $items[] = $this->driftItem(
                            'unauthorized_components',
                            'HIGH',
                            "[{$context}] {$rel}",
                            "Uses `{$raw}` — violates canonical modal/alert policy",
                            "Replace with <x-{$canonical} />"
                        );
                    }
                }
            }
        }

        return $items;
    }

    private function detectFrontendDivergence(string $saasRoot, string $customerRoot): array
    {
        $items = [];

        // Both must import design-tokens.css
        $sassCss = $saasRoot . '/resources/css/app.css';
        $csCss   = $customerRoot . '/resources/css/app.css';

        if (file_exists($sassCss) && !str_contains(file_get_contents($sassCss), 'design-tokens')) {
            $items[] = $this->driftItem('frontend_divergence', 'CRITICAL',
                'saas-platform resources/css/app.css',
                'Missing canonical design-tokens.css import',
                "Add: @import '...core-utils/resources/css/design-tokens.css';"
            );
        }

        if (!file_exists($customerRoot)) {
            $items[] = $this->driftItem('frontend_divergence', 'HIGH',
                'customer-site',
                'customer-site repository not found at expected path',
                'Ensure customer-site is located at ../customer-site relative to saas-platform'
            );
        } elseif (file_exists($csCss) && !str_contains(file_get_contents($csCss), 'design-tokens')) {
            $items[] = $this->driftItem('frontend_divergence', 'CRITICAL',
                'customer-site resources/css/app.css',
                'Missing canonical design-tokens.css import',
                "Add: @import '...core-utils/resources/css/design-tokens.css';"
            );
        }

        // Check customer-site governance workflow exists
        $csWorkflow = $customerRoot . '/.github/workflows/frontend-governance.yml';
        if (is_dir($customerRoot) && !file_exists($csWorkflow)) {
            $items[] = $this->driftItem('frontend_divergence', 'CRITICAL',
                'customer-site/.github/workflows/',
                'customer-site lacks frontend governance CI workflow',
                'Create .github/workflows/frontend-governance.yml mirroring saas-platform pipeline'
            );
        }

        return $items;
    }

    private function detectModuleLocalStyles(string $root): array
    {
        $items   = [];
        $modules = glob($root . '/packages/saas-platform/modules/*/resources/views', GLOB_ONLYDIR) ?: [];
        $modules = array_merge(
            $modules,
            glob($root . '/packages/saas-platform/modules/*/Resources/views', GLOB_ONLYDIR) ?: []
        );

        foreach ($modules as $modulePath) {
            preg_match('/modules\/([^\/]+)\//', $modulePath, $m);
            $moduleName = $m[1] ?? 'unknown';

            // Check for module-local CSS files (except allowed dist files)
            $cssFiles = glob($modulePath . '/../../resources/css/*.css') ?: [];
            foreach ($cssFiles as $cssFile) {
                $basename = basename($cssFile);
                if ($basename === 'design-tokens.css') continue;
                $items[] = $this->driftItem('module_local_styles', 'HIGH',
                    "modules/{$moduleName}/resources/css/{$basename}",
                    "Module-local CSS file detected — styles must use canonical design tokens",
                    'Move any custom tokens to design-tokens.css; use @apply with token-mapped utilities'
                );
            }

            // Check for inline <style> blocks in module views
            $bladeFiles = $this->getAllBladeFiles($modulePath);
            foreach ($bladeFiles as $file) {
                $content = @file_get_contents($file) ?: '';
                if (preg_match('/<style[\s>]/i', $content) && !preg_match('/{{--.*<style/s', $content)) {
                    $rel = $this->relativePath($file, $root);
                    $items[] = $this->driftItem('module_local_styles', 'HIGH',
                        $rel,
                        'Inline <style> block in module view',
                        'Extract to design-tokens.css or use canonical Tailwind utilities with var(--ui-*)'
                    );
                }
            }
        }

        return $items;
    }

    private function detectDuplicateUiSystems(string $root, string $context = 'saas-platform'): array
    {
        $items    = [];
        $viewBase = $context === 'customer-site'
            ? $root . '/resources/views'
            : $root . '/packages/saas-platform/modules';

        if (!is_dir($viewBase)) return $items;

        // Look for custom component directories that shadow canonical ones
        $componentDirs = glob($viewBase . '/*/components', GLOB_ONLYDIR) ?: [];
        $componentDirs = array_merge(
            $componentDirs,
            glob($viewBase . '/*/resources/views/components', GLOB_ONLYDIR) ?: []
        );

        foreach ($componentDirs as $dir) {
            $files = glob($dir . '/*.blade.php') ?: [];
            foreach ($files as $file) {
                $name = basename($file, '.blade.php');
                // If same name as a canonical component
                $canonical = ['button', 'input', 'select', 'textarea', 'modal', 'alert', 'badge', 'card'];
                if (in_array($name, $canonical, true)) {
                    $rel = $this->relativePath($file, $root);
                    $items[] = $this->driftItem('duplicate_ui_systems', 'HIGH',
                        "[{$context}] {$rel}",
                        "Duplicate UI primitive '{$name}' shadows canonical x-core-ui::{$name}",
                        "Delete local {$name}.blade.php; use x-core-ui::{$name} directly"
                    );
                }
            }
        }

        return $items;
    }

    private function detectRuntimeDrift(string $root): array
    {
        $items = [];
        $files = $this->getAllBladeFiles($root . '/packages/saas-platform/modules');

        foreach ($files as $file) {
            $content = @file_get_contents($file) ?: '';
            $rel     = $this->relativePath($file, $root);

            // Dynamic style injection
            if (preg_match('/document\.head.*style/i', $content) ||
                preg_match('/\$el\.style\.[a-z]+\s*=\s*["\']#/i', $content)) {
                $items[] = $this->driftItem('runtime_drift', 'HIGH',
                    $rel,
                    'Runtime style injection detected',
                    'Use CSS custom property toggling via class names; never inject raw style strings'
                );
            }

            // Hardcoded color values set via JavaScript
            if (preg_match('/\.style\.(color|background|borderColor)\s*=\s*["\']#[0-9a-fA-F]/i', $content)) {
                $items[] = $this->driftItem('runtime_drift', 'CRITICAL',
                    $rel,
                    'Runtime hardcoded hex color assignment via JS',
                    'Use: el.style.setProperty("--ui-*", value) or toggle CSS classes'
                );
            }
        }

        return $items;
    }

    private function groupBySeverity(array $items): array
    {
        $groups = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        foreach ($items as $item) {
            $groups[$item['severity']] = ($groups[$item['severity']] ?? 0) + 1;
        }
        return $groups;
    }

    private function groupByCategory(array $items): array
    {
        $groups = array_fill_keys(self::DRIFT_CATEGORIES, 0);
        foreach ($items as $item) {
            $groups[$item['category']] = ($groups[$item['category']] ?? 0) + 1;
        }
        arsort($groups);
        return $groups;
    }

    private function computeLockdownScore(array $items): int
    {
        $deductions = 0;
        foreach ($items as $item) {
            $deductions += match($item['severity']) {
                'CRITICAL' => 5,
                'HIGH'     => 3,
                'MEDIUM'   => 1,
                default    => 0,
            };
        }
        return max(0, 100 - $deductions);
    }

    private function buildReplacementMap(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            if (!empty($item['fix'])) {
                $map[$item['location']] = $item['fix'];
            }
        }
        return $map;
    }

    private function buildSummary(int $score, int $total, bool $passed): string
    {
        $status = $passed ? 'LOCKED DOWN' : 'DRIFT DETECTED';
        return "[{$status}] Lockdown score: {$score}/100 | Total drift items: {$total}";
    }

    private function getAllBladeFiles(string $path): array
    {
        $files = [];
        if (!is_dir($path)) return $files;

        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                $path,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ));
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Throwable) {}

        return $files;
    }

    private function driftItem(string $category, string $severity, string $location, string $issue, string $fix): array
    {
        return compact('category', 'severity', 'location', 'issue', 'fix');
    }

    private function relativePath(string $path, string $root): string
    {
        return str_replace([$root . DIRECTORY_SEPARATOR, $root . '/'], '', $path);
    }
}
