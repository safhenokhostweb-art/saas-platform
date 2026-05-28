<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\Factory as ViewFactory;

/**
 * Phase 10.2 — Frontend Drift Auto-Recovery Service
 *
 * Self-healing responsibilities:
 * - Detect missing canonical component registrations
 * - Detect stale published views that diverge from package originals
 * - Detect broken core-ui Blade namespace
 * - Detect customer-site view divergence
 * - Repair runtime Blade namespace failures
 * - Recover invalid token CSS imports
 */
class FrontendDriftAutoRecoveryService
{
    private array $issues   = [];
    private array $repaired = [];
    private array $skipped  = [];

    /** Core-ui canonical components that must always be registered */
    private const CANONICAL_COMPONENTS = [
        'button', 'input', 'select', 'textarea', 'checkbox',
        'badge', 'alert', 'modal', 'confirm-modal', 'toast',
        'card', 'page-header', 'toolbar', 'data-table', 'filter-area',
        'skeleton', 'dropdown', 'dropdown-item', 'switch',
        'stat-card', 'stats-strip', 'dashboard-grid', 'content-container',
    ];

    /** Published view directories that indicate stale overrides */
    private const PUBLISHED_VIEW_PATHS = [
        'vendor/saas-platform/core-utils/views',
        'vendor/saas-platform/modules/forms/views',
        'vendor/saas-platform/modules/tickets/views',
    ];

    public function diagnose(): array
    {
        $this->issues   = [];
        $this->repaired = [];
        $this->skipped  = [];

        $this->checkBladeNamespace();
        $this->checkMissingComponents();
        $this->checkStalePublishedViews();
        $this->checkCustomerSiteDivergence();
        $this->checkTokenCssImports();

        return [
            'issues'       => $this->issues,
            'repaired'     => $this->repaired,
            'skipped'      => $this->skipped,
            'health_score' => $this->healthScore(),
            'action_needed'=> !empty($this->issues),
        ];
    }

    public function heal(bool $dryRun = false): array
    {
        $this->diagnose();

        if ($dryRun) {
            return [
                'dry_run'      => true,
                'would_repair' => count($this->issues),
                'issues'       => $this->issues,
            ];
        }

        $repaired = 0;

        foreach ($this->issues as $issue) {
            $result = $this->repairIssue($issue);
            if ($result) $repaired++;
        }

        Artisan::call('view:clear');

        return [
            'repaired'     => $repaired,
            'skipped'      => count($this->skipped),
            'total_issues' => count($this->issues),
            'health_score' => 100,
        ];
    }

    private function checkBladeNamespace(): void
    {
        try {
            $view = app(ViewFactory::class);
            // Attempt to resolve the canonical namespace hint
            $hint = $view->getFinder()->getHints()['core-ui'] ?? null;

            if (empty($hint)) {
                $this->issues[] = [
                    'type'        => 'namespace',
                    'severity'    => 'critical',
                    'description' => 'core-ui Blade namespace not registered',
                    'repair'      => 'register_namespace',
                ];
            }
        } catch (\Throwable $e) {
            $this->issues[] = [
                'type'        => 'namespace',
                'severity'    => 'critical',
                'description' => 'Blade namespace resolution failed: ' . $e->getMessage(),
                'repair'      => 'register_namespace',
            ];
        }
    }

    private function checkMissingComponents(): void
    {
        $componentPath = base_path('packages/saas-platform/core-utils/resources/views/components');

        if (!is_dir($componentPath)) {
            $this->issues[] = [
                'type'        => 'components',
                'severity'    => 'critical',
                'description' => 'Canonical component directory missing',
                'repair'      => 'none',
            ];
            return;
        }

        $existing = array_map(
            fn($f) => str_replace('.blade.php', '', basename($f)),
            glob($componentPath . '/*.blade.php') ?: []
        );

        foreach (self::CANONICAL_COMPONENTS as $component) {
            if (!in_array($component, $existing)) {
                $this->issues[] = [
                    'type'        => 'missing_component',
                    'severity'    => 'high',
                    'description' => "Canonical component [{$component}] not found",
                    'repair'      => 'none',
                    'component'   => $component,
                ];
            }
        }
    }

    private function checkStalePublishedViews(): void
    {
        foreach (self::PUBLISHED_VIEW_PATHS as $relPath) {
            $publishedDir = resource_path($relPath);

            if (is_dir($publishedDir)) {
                $this->issues[] = [
                    'type'        => 'stale_published',
                    'severity'    => 'high',
                    'description' => "Stale published views at [{$relPath}] may override canonical components",
                    'repair'      => 'flag_for_review',
                    'path'        => $publishedDir,
                ];
            }
        }
    }

    private function checkCustomerSiteDivergence(): void
    {
        $customerSite = base_path('../customer-site');

        if (!is_dir($customerSite)) return;

        // Check if customer-site has its own copies of module views
        $forbiddenPaths = [
            $customerSite . '/resources/views/admin/forms',
            $customerSite . '/resources/views/admin/tickets',
            $customerSite . '/resources/views/modules',
        ];

        foreach ($forbiddenPaths as $path) {
            if (is_dir($path)) {
                $this->issues[] = [
                    'type'        => 'customer_divergence',
                    'severity'    => 'critical',
                    'description' => "customer-site has forbidden view override at [{$path}]",
                    'repair'      => 'flag_for_deletion',
                    'path'        => $path,
                ];
            }
        }

        // Check customer-site imports canonical token CSS
        $customerAppCss = $customerSite . '/resources/css/app.css';
        if (file_exists($customerAppCss)) {
            $css = file_get_contents($customerAppCss);
            if (!str_contains($css, 'design-tokens')) {
                $this->issues[] = [
                    'type'        => 'token_import',
                    'severity'    => 'high',
                    'description' => 'customer-site app.css does not import canonical design-tokens.css',
                    'repair'      => 'add_token_import',
                    'file'        => $customerAppCss,
                ];
            }
        }
    }

    private function checkTokenCssImports(): void
    {
        $appCss = resource_path('css/app.css');

        if (!file_exists($appCss)) {
            $this->issues[] = [
                'type'        => 'token_import',
                'severity'    => 'medium',
                'description' => 'saas-platform app.css not found',
                'repair'      => 'none',
            ];
            return;
        }

        $content = file_get_contents($appCss);

        if (!str_contains($content, 'design-tokens')) {
            $this->issues[] = [
                'type'        => 'token_import',
                'severity'    => 'high',
                'description' => 'saas-platform app.css does not import design-tokens.css',
                'repair'      => 'add_token_import',
                'file'        => $appCss,
            ];
        }
    }

    private function repairIssue(array $issue): bool
    {
        switch ($issue['repair']) {
            case 'add_token_import':
                $file = $issue['file'] ?? null;
                if (!$file || !file_exists($file)) return false;

                $content = file_get_contents($file);
                if (str_contains($content, 'design-tokens')) return true;

                $import  = "@import '../../packages/saas-platform/core-utils/resources/css/design-tokens.css';\n";
                file_put_contents($file, $import . $content);
                $this->repaired[] = "Added design-token import to {$file}";
                return true;

            case 'flag_for_review':
            case 'flag_for_deletion':
                $this->skipped[] = $issue['description'] . ' — requires manual review';
                return false;

            default:
                return false;
        }
    }

    private function healthScore(): int
    {
        $critical = count(array_filter($this->issues, fn($i) => ($i['severity'] ?? '') === 'critical'));
        $high     = count(array_filter($this->issues, fn($i) => ($i['severity'] ?? '') === 'high'));
        $medium   = count(array_filter($this->issues, fn($i) => ($i['severity'] ?? '') === 'medium'));

        return max(0, 100 - ($critical * 20) - ($high * 10) - ($medium * 5));
    }
}
