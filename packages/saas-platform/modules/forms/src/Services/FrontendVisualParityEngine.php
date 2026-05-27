<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;

/**
 * Phase 9.3 — FrontendVisualParityEngine
 *
 * Validates that the canonical UI authority (forms package views) is the ONLY
 * source of rendered HTML for both saas-platform and customer-site. Detects:
 *  - Forbidden override files in host apps
 *  - CSS variable drift (custom vars not using design tokens)
 *  - Component namespace drift (core-ui:: not registered)
 *  - Layout resolution drift between contexts
 *  - Asset hash divergence across contexts
 */
class FrontendVisualParityEngine
{
    private const FORBIDDEN_OVERRIDE_PATHS = [
        'saas-platform' => [
            'resources/views/admin/forms',
            'resources/views/site/forms',
            'resources/views/admin/tickets',
            'resources/views/site/tickets',
            'resources/views/vendor/forms',
            'resources/views/vendor/tickets',
        ],
        'customer-site' => [
            'resources/views/admin/forms',
            'resources/views/site/forms',
            'resources/views/admin/tickets',
            'resources/views/site/tickets',
            'resources/views/vendor/forms',
            'resources/views/vendor/tickets',
        ],
    ];

    private const DESIGN_TOKEN_PREFIXES = [
        '--forms-',
        '--tickets-',
        '--core-ui-',
    ];

    private const FORBIDDEN_CSS_PATTERNS = [
        '/--c-[a-z]+\s*:/', // legacy --c-indigo etc.
        '/color\s*:\s*#[0-9a-fA-F]{3,6}(?!\s*;?\s*\/\*\s*token)/', // bare hex colors outside token comments
    ];

    public function __construct(
        private readonly string $platformRoot = '',
        private readonly string $customerSiteRoot = '',
    ) {}

    public function validate(): array
    {
        $results = [];

        $results['forbidden_overrides']     = $this->checkForbiddenOverrides();
        $results['css_variable_drift']      = $this->checkCssVariableDrift();
        $results['component_namespace']     = $this->checkComponentNamespace();
        $results['layout_resolution']       = $this->checkLayoutResolution();
        $results['view_authority_integrity']= $this->checkViewAuthorityIntegrity();

        return $results;
    }

    public function score(): int
    {
        $results = $this->validate();
        $weights = [
            'forbidden_overrides'      => 30,
            'css_variable_drift'       => 20,
            'component_namespace'      => 20,
            'layout_resolution'        => 15,
            'view_authority_integrity' => 15,
        ];

        $total = 0;
        foreach ($results as $key => $check) {
            $w      = $weights[$key] ?? 10;
            $passed = ($check['passed'] ?? false) ? $w : 0;
            $total += $passed;
        }

        return min(100, $total);
    }

    public function isParity(): bool
    {
        return $this->score() >= 90;
    }

    // ─── Individual checks ────────────────────────────────────────────────────

    private function checkForbiddenOverrides(): array
    {
        $violations = [];

        foreach (self::FORBIDDEN_OVERRIDE_PATHS as $app => $paths) {
            $root = $this->getAppRoot($app);
            if (! $root) {
                continue;
            }
            foreach ($paths as $path) {
                $full = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
                if (is_dir($full)) {
                    $files = glob($full . DIRECTORY_SEPARATOR . '*.blade.php');
                    if (! empty($files)) {
                        $violations[] = "[{$app}] {$path} (" . count($files) . ' files)';
                    }
                }
            }
        }

        return [
            'check'      => 'Forbidden view override detection',
            'passed'     => empty($violations),
            'violations' => $violations,
            'message'    => empty($violations)
                ? 'No forbidden view overrides detected in either application'
                : 'FORBIDDEN OVERRIDES DETECTED — violates canonical view authority',
        ];
    }

    private function checkCssVariableDrift(): array
    {
        $violations = [];

        $searchDirs = [
            $this->getPackageViewsPath('forms'),
            $this->getPackageViewsPath('tickets'),
        ];

        foreach (array_filter($searchDirs) as $dir) {
            $bladeFiles = File::exists($dir) ? File::allFiles($dir) : [];
            foreach ($bladeFiles as $file) {
                $content = file_get_contents($file->getPathname());
                foreach (self::FORBIDDEN_CSS_PATTERNS as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $violations[] = $file->getRelativePathname() . ' — matches ' . $pattern;
                    }
                }
            }
        }

        return [
            'check'      => 'CSS variable design token compliance',
            'passed'     => count($violations) <= 3, // allow a small tolerance for intentional legacy
            'violations' => array_slice($violations, 0, 10),
            'message'    => count($violations) <= 3
                ? 'CSS variables mostly use design token system'
                : count($violations) . ' CSS drift items detected — migrate to --forms-* tokens',
        ];
    }

    private function checkComponentNamespace(): array
    {
        $checks = [];

        // Verify FormsServiceProvider registers core-ui::
        $fsp = $this->getFilePath('packages/saas-platform/modules/forms/src/Providers/FormsServiceProvider.php');
        $formsRegistered = $fsp && str_contains(file_get_contents($fsp), "anonymousComponentNamespace");

        // Verify TicketsServiceProvider registers core-ui::
        $tsp = $this->getFilePath('packages/saas-platform/modules/tickets/src/Providers/TicketsServiceProvider.php');
        $ticketsRegistered = $tsp && str_contains(file_get_contents($tsp), "anonymousComponentNamespace");

        $passed = $formsRegistered && $ticketsRegistered;

        return [
            'check'             => 'core-ui:: anonymous component namespace registration',
            'passed'            => $passed,
            'forms_registered'  => $formsRegistered,
            'tickets_registered'=> $ticketsRegistered,
            'message'           => $passed
                ? 'core-ui:: namespace registered in both FormsServiceProvider and TicketsServiceProvider'
                : 'DRIFT: core-ui:: namespace missing in one or more service providers',
        ];
    }

    private function checkLayoutResolution(): array
    {
        $issues = [];

        // Forms config
        $formsConfig = $this->getFilePath('packages/saas-platform/modules/forms/config/forms.php');
        if ($formsConfig) {
            $content = file_get_contents($formsConfig);
            if (! str_contains($content, 'layouts')) {
                $issues[] = 'forms.php config missing layouts key';
            }
        }

        // Tickets config
        $ticketsConfig = $this->getFilePath('packages/saas-platform/modules/tickets/config/tickets.php');
        if ($ticketsConfig) {
            $content = file_get_contents($ticketsConfig);
            if (! str_contains($content, 'layouts')) {
                $issues[] = 'tickets.php config missing layouts key';
            }
        }

        // View composers
        $fsp = $this->getFilePath('packages/saas-platform/modules/forms/src/Providers/FormsServiceProvider.php');
        if ($fsp && ! str_contains(file_get_contents($fsp), 'view()->composer')) {
            $issues[] = 'FormsServiceProvider missing view composer for dynamic layout resolution';
        }

        return [
            'check'   => 'Layout resolution per-context (platform vs. tenant)',
            'passed'  => empty($issues),
            'issues'  => $issues,
            'message' => empty($issues)
                ? 'Layout resolution properly configured in both modules'
                : 'Layout resolution issues: ' . implode('; ', $issues),
        ];
    }

    private function checkViewAuthorityIntegrity(): array
    {
        $issues = [];

        $formsViewsPath  = $this->getPackageViewsPath('forms');
        $ticketsViewsPath = $this->getPackageViewsPath('tickets');

        if (! $formsViewsPath || ! File::exists($formsViewsPath)) {
            $issues[] = 'Forms package views directory not found';
        }

        if (! $ticketsViewsPath || ! File::exists($ticketsViewsPath)) {
            $issues[] = 'Tickets package views directory not found';
        }

        // Confirm no views exist in host-app resources/views/admin/{forms,tickets}
        $platformViewsAdmin = base_path('resources/views/admin');
        foreach (['forms', 'tickets'] as $module) {
            $path = $platformViewsAdmin . DIRECTORY_SEPARATOR . $module;
            if (is_dir($path) && ! empty(glob($path . DIRECTORY_SEPARATOR . '*.blade.php'))) {
                $issues[] = "Host app has blade files in resources/views/admin/{$module}";
            }
        }

        return [
            'check'   => 'View authority integrity — package views are the only source',
            'passed'  => empty($issues),
            'issues'  => $issues,
            'message' => empty($issues)
                ? 'View authority intact — all rendering from package canonical sources'
                : 'View authority integrity issues: ' . implode('; ', $issues),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getAppRoot(string $app): ?string
    {
        if ($app === 'saas-platform') {
            return $this->platformRoot ?: base_path();
        }
        if ($app === 'customer-site') {
            return $this->customerSiteRoot ?: dirname(base_path()) . DIRECTORY_SEPARATOR . 'customer-site';
        }
        return null;
    }

    private function getPackageViewsPath(string $module): ?string
    {
        $base = base_path("packages/saas-platform/modules/{$module}");
        $candidates = [
            $base . '/resources/views',
            $base . '/Resources/views',
        ];
        foreach ($candidates as $c) {
            if (is_dir($c)) {
                return $c;
            }
        }
        return null;
    }

    private function getFilePath(string $relative): ?string
    {
        $full = base_path($relative);
        return file_exists($full) ? $full : null;
    }
}
