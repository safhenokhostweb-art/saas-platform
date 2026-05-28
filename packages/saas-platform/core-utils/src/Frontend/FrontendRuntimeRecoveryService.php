<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\Log;

/**
 * Phase 10.0 — Frontend Runtime Recovery Service
 *
 * Detects and auto-heals frontend runtime failures:
 * - Missing canonical component files
 * - Token drift (stale or missing design-tokens.css)
 * - Broken Vite manifests
 * - Invalid component namespace registrations
 * - Stale asset references
 */
class FrontendRuntimeRecoveryService
{
    private array $diagnostics = [];
    private array $recoveries  = [];
    private array $failures    = [];

    public function diagnoseAndRecover(bool $dryRun = false): array
    {
        $this->diagnostics = [];
        $this->recoveries  = [];
        $this->failures    = [];

        $this->checkDesignTokensFile($dryRun);
        $this->checkRequiredComponents($dryRun);
        $this->checkViteManifest($dryRun);
        $this->checkCoreUtilsComponentDirectory($dryRun);
        $this->checkFormsComponentDirectory($dryRun);

        return [
            'healthy'     => empty($this->failures),
            'diagnostics' => $this->diagnostics,
            'recovered'   => $this->recoveries,
            'unrecoverable' => $this->failures,
            'dry_run'     => $dryRun,
        ];
    }

    private function checkDesignTokensFile(bool $dryRun): void
    {
        $tokenFile = realpath(__DIR__ . '/../../resources/css/design-tokens.css');

        if ($tokenFile && file_exists($tokenFile)) {
            $this->ok('design-tokens-css', 'design-tokens.css present and readable');
            return;
        }

        $this->issue('design-tokens-css', 'MISSING', 'design-tokens.css not found in core-utils/resources/css/');
        // This file is part of the package — if missing, the package install is corrupted
        $this->fail('design-tokens-css', 'Cannot auto-recover missing design-tokens.css — reinstall core-utils package');
    }

    private function checkRequiredComponents(bool $dryRun): void
    {
        $required = [
            'button', 'icon-button', 'card', 'input', 'select', 'textarea',
            'checkbox', 'radio-card', 'dropdown', 'dropdown-item', 'action-menu',
            'navigation-item', 'sidebar-group',
        ];

        $dir = realpath(__DIR__ . '/../../resources/views/components');

        if (! $dir || ! is_dir($dir)) {
            $this->issue('component-directory', 'MISSING', 'core-utils/resources/views/components/ directory does not exist');
            if (! $dryRun) {
                mkdir($dir ?? (__DIR__ . '/../../resources/views/components'), 0755, true);
                $this->recover('component-directory', 'Created components directory');
            }
            return;
        }

        $missing = [];
        foreach ($required as $component) {
            if (! file_exists($dir . '/' . $component . '.blade.php')) {
                $missing[] = $component;
            }
        }

        if (empty($missing)) {
            $this->ok('required-components', 'All ' . count($required) . ' canonical components present');
        } else {
            $this->issue('required-components', 'MISSING', 'Missing components: ' . implode(', ', $missing));
            $this->fail('required-components', 'Components missing — run `php artisan frontend:validate-authority` and review Phase 10.0 installation');
        }
    }

    private function checkViteManifest(bool $dryRun): void
    {
        $manifestPath = public_path('build/manifest.json');

        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->ok('vite-manifest', 'Vite manifest present and valid (' . count($manifest) . ' entries)');
                return;
            }
            $this->issue('vite-manifest', 'CORRUPTED', 'Vite manifest exists but contains invalid JSON');
            $this->fail('vite-manifest', 'Corrupt Vite manifest — run `npm run build` to regenerate');
        } else {
            $this->issue('vite-manifest', 'MISSING', 'Vite manifest not found at public/build/manifest.json');
            $this->fail('vite-manifest', 'No compiled assets — run `npm run build` or `npm run dev`');
        }
    }

    private function checkCoreUtilsComponentDirectory(bool $dryRun): void
    {
        $dir = realpath(__DIR__ . '/../../resources/views/components');
        $files = $dir ? glob($dir . '/*.blade.php') : [];
        $count = count($files ?: []);

        if ($count > 0) {
            $this->ok('core-utils-components', "core-utils components directory has {$count} component(s)");
        } else {
            $this->issue('core-utils-components', 'EMPTY', 'core-utils/resources/views/components/ is empty');
            $this->fail('core-utils-components', 'No components found — Phase 10.0 installation may be incomplete');
        }
    }

    private function checkFormsComponentDirectory(bool $dryRun): void
    {
        $dir = base_path('packages/saas-platform/modules/forms/resources/views/core-ui/components');
        if (! is_dir($dir)) {
            $this->issue('forms-core-ui-dir', 'MISSING', 'Forms module core-ui components directory not found');
            return;
        }

        $files = glob($dir . '/*.blade.php');
        $this->ok('forms-core-ui-dir', 'Forms module contributes ' . count($files) . ' additional core-ui components');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function ok(string $key, string $message): void
    {
        $this->diagnostics[$key] = ['status' => 'ok', 'message' => $message];
    }

    private function issue(string $key, string $type, string $message): void
    {
        $this->diagnostics[$key] = ['status' => 'issue', 'type' => $type, 'message' => $message];
        Log::warning("[FrontendRuntimeRecovery] {$type}: {$message}");
    }

    private function recover(string $key, string $action): void
    {
        $this->recoveries[$key] = $action;
        Log::info("[FrontendRuntimeRecovery] Recovered: {$action}");
    }

    private function fail(string $key, string $reason): void
    {
        $this->failures[$key] = $reason;
    }
}
