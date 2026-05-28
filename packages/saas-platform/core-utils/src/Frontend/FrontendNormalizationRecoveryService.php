<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Phase 10.1 — Task 12
 * Auto-detects and recovers frontend normalization issues:
 * broken component mappings, missing token imports, drifted views,
 * stale view cache, component namespace issues.
 */
class FrontendNormalizationRecoveryService
{
    private array $recoveryLog  = [];
    private array $failures     = [];
    private bool  $dryRun       = false;

    public function recover(bool $dryRun = false): array
    {
        $this->dryRun    = $dryRun;
        $this->recoveryLog = [];
        $this->failures  = [];

        $this->recoverBladeViewCache();
        $this->recoverComponentNamespace();
        $this->recoverTokenImports();
        $this->recoverOrphanedCssVars();
        $this->recoverMissingComponentAliases();

        $score = max(0, 100 - (count($this->failures) * 10));

        return [
            'dry_run'      => $dryRun,
            'score'        => $score,
            'recovered'    => $this->recoveryLog,
            'failures'     => $this->failures,
            'summary'      => sprintf(
                'Frontend Normalization Recovery: %d actions %s | %d failures',
                count($this->recoveryLog),
                $dryRun ? '(dry-run)' : 'performed',
                count($this->failures)
            ),
        ];
    }

    private function recoverBladeViewCache(): void
    {
        $cachePath = storage_path('framework/views');

        if (! is_dir($cachePath)) {
            $this->log('view-cache', 'View cache directory not found — skipping', false);
            return;
        }

        $cachedFiles = glob($cachePath . '/*.php') ?: [];

        if (empty($cachedFiles)) {
            $this->log('view-cache', 'View cache is already empty', false);
            return;
        }

        if (! $this->dryRun) {
            try {
                Artisan::call('view:clear');
                $this->log('view-cache', sprintf('Cleared %d cached view files', count($cachedFiles)), true);
            } catch (\Throwable $e) {
                $this->addFailure('view-cache', 'Failed to clear view cache: ' . $e->getMessage());
            }
        } else {
            $this->log('view-cache', sprintf('[DRY-RUN] Would clear %d cached view files', count($cachedFiles)), false);
        }
    }

    private function recoverComponentNamespace(): void
    {
        $coreUtilsComponents = base_path('packages/saas-platform/core-utils/resources/views/components');

        if (! is_dir($coreUtilsComponents)) {
            $this->addFailure('component-namespace', 'core-utils components directory missing: ' . $coreUtilsComponents);
            return;
        }

        $components = glob($coreUtilsComponents . '/*.php') ?: [];
        $missing    = [];
        $canonical  = [
            'button', 'icon-button', 'card', 'input', 'select', 'textarea',
            'checkbox', 'radio-card', 'dropdown', 'dropdown-item', 'action-menu',
            'navigation-item', 'sidebar-group', 'switch', 'modal', 'confirm-modal',
            'badge', 'alert', 'toast', 'skeleton', 'data-table', 'page-header',
            'toolbar', 'content-container', 'stats-strip', 'stat-card', 'dashboard-grid',
            'filter-area',
        ];

        foreach ($canonical as $comp) {
            $path = $coreUtilsComponents . '/' . $comp . '.blade.php';
            if (! file_exists($path)) {
                $missing[] = $comp;
            }
        }

        if (empty($missing)) {
            $this->log('component-namespace', 'All ' . count($canonical) . ' canonical components present', false);
        } else {
            $this->log('component-namespace', 'Missing canonical components: ' . implode(', ', $missing), false);
        }
    }

    private function recoverTokenImports(): void
    {
        $tokenFile = base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css');
        $appCss    = resource_path('css/app.css');

        if (! file_exists($tokenFile)) {
            $this->addFailure('token-import', 'Canonical design-tokens.css not found at: ' . $tokenFile);
            return;
        }

        if (! file_exists($appCss)) {
            $this->addFailure('token-import', 'app.css not found — cannot verify token import');
            return;
        }

        $appCssContent = file_get_contents($appCss);
        if (! str_contains($appCssContent, 'design-tokens.css')) {
            if (! $this->dryRun) {
                $import = "\n/* Phase 10.1 — Canonical design token system */\n@import '../../packages/saas-platform/core-utils/resources/css/design-tokens.css';\n";
                $newContent = str_replace('@tailwind utilities;', '@tailwind utilities;' . $import, $appCssContent);
                file_put_contents($appCss, $newContent);
                $this->log('token-import', 'Added design-tokens.css import to app.css', true);
            } else {
                $this->log('token-import', '[DRY-RUN] Would add design-tokens.css import to app.css', false);
            }
        } else {
            $this->log('token-import', 'design-tokens.css already imported in app.css', false);
        }

        // Check customer-site
        $customerCss = base_path('../customer-site/resources/css/app.css');
        if (file_exists($customerCss)) {
            $customerContent = file_get_contents($customerCss);
            if (! str_contains($customerContent, 'design-tokens.css')) {
                $this->log('token-import-customer', 'WARNING: customer-site app.css missing design-tokens.css import', false);
            } else {
                $this->log('token-import-customer', 'customer-site correctly imports design-tokens.css', false);
            }
        }
    }

    private function recoverOrphanedCssVars(): void
    {
        $tokenFile = base_path('packages/saas-platform/core-utils/resources/css/design-tokens.css');
        if (! file_exists($tokenFile)) return;

        $content = file_get_contents($tokenFile);
        preg_match_all('/--(?!forms-|admin-|ui-)[\w-]+\s*:/', $content, $matches);

        if (! empty($matches[0])) {
            $this->log('orphaned-vars', 'Non-canonical CSS vars found (not --ui-* or backward-compat): ' . implode(', ', array_slice($matches[0], 0, 5)), false);
        } else {
            $this->log('orphaned-vars', 'All CSS custom properties follow --ui-* naming convention', false);
        }
    }

    private function recoverMissingComponentAliases(): void
    {
        $providerFile = base_path('packages/saas-platform/core-utils/src/Providers/CoreUtilsServiceProvider.php');
        if (! file_exists($providerFile)) {
            $this->addFailure('component-aliases', 'CoreUtilsServiceProvider.php not found');
            return;
        }

        $content = file_get_contents($providerFile);
        $checks  = [
            'core-ui namespace' => "anonymousComponentPath(__DIR__ . '/../../resources/views/components', 'core-ui')",
            'loadViewsFrom'     => "loadViewsFrom(__DIR__ . '/../../resources/views', 'core-ui')",
        ];

        foreach ($checks as $name => $needle) {
            if (str_contains($content, $needle)) {
                $this->log('component-aliases', "{$name}: registered correctly", false);
            } else {
                $this->log('component-aliases', "WARNING: {$name} not found in provider — namespace may be broken", false);
            }
        }
    }

    private function log(string $key, string $message, bool $wasAction): void
    {
        $this->recoveryLog[] = [
            'key'       => $key,
            'message'   => $message,
            'action'    => $wasAction,
        ];
    }

    private function addFailure(string $key, string $message): void
    {
        $this->failures[] = ['key' => $key, 'message' => $message];
    }
}
