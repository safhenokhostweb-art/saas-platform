<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * Phase 10.0 — Frontend Authority Validation Service
 *
 * Validates that the frontend runtime is canonical:
 * - No forbidden Tailwind color utilities
 * - No inline style= attributes in Blade views
 * - Core-ui namespace is registered
 * - Design token CSS is imported
 * - No duplicate component implementations
 * - No customer-site-only visual overrides
 */
class FrontendAuthorityValidationService
{
    private array $checks   = [];
    private array $warnings = [];
    private array $errors   = [];

    /** Forbidden raw Tailwind color utilities that must be replaced with token-driven classes */
    private const FORBIDDEN_COLORS = [
        'bg-blue-500', 'bg-blue-600', 'bg-blue-700',
        'bg-red-500', 'bg-red-600', 'bg-red-700',
        'bg-green-500', 'bg-green-600', 'bg-green-700',
        'bg-yellow-500', 'bg-yellow-400',
        'text-blue-500', 'text-blue-600', 'text-red-500', 'text-red-600',
        'text-green-500', 'text-green-600', 'border-blue-500', 'border-red-500',
    ];

    /** Canonical core-ui components that must exist */
    private const REQUIRED_COMPONENTS = [
        'button', 'icon-button', 'card', 'input', 'select', 'textarea',
        'checkbox', 'radio-card', 'dropdown', 'dropdown-item', 'action-menu',
        'navigation-item', 'sidebar-group',
    ];

    /** Blade view directories to audit */
    private function viewDirectories(): array
    {
        $dirs = [];

        // Core-utils canonical components
        $coreUtilsComponents = realpath(__DIR__ . '/../../resources/views/components');
        if ($coreUtilsComponents) {
            $dirs['core-utils'] = $coreUtilsComponents;
        }

        // Forms module views
        $formsBase = base_path('packages/saas-platform/modules/forms/resources/views');
        if (is_dir($formsBase)) {
            $dirs['forms'] = $formsBase;
        }

        // Tickets module views
        $ticketsBase = base_path('packages/saas-platform/modules/tickets/Resources/views');
        if (is_dir($ticketsBase)) {
            $dirs['tickets'] = $ticketsBase;
        }

        // Platform app views
        $platformViews = resource_path('views');
        if (is_dir($platformViews)) {
            $dirs['platform'] = $platformViews;
        }

        return $dirs;
    }

    public function validate(): array
    {
        $this->checks   = [];
        $this->warnings = [];
        $this->errors   = [];

        $this->checkDesignTokenFile();
        $this->checkTokenImport();
        $this->checkCoreUiNamespaceRegistered();
        $this->checkRequiredComponents();
        $this->checkForbiddenInlineStyles();
        $this->checkForbiddenTailwindColors();
        $this->checkDuplicateModalImplementations();
        $this->checkDuplicateButtonSystems();
        $this->checkCustomerSiteOverrides();
        $this->checkFormModuleCanonical();

        $score   = $this->computeScore();
        $passed  = count($this->checks);
        $failed  = count($this->errors);
        $warned  = count($this->warnings);

        return [
            'score'    => $score,
            'grade'    => $this->grade($score),
            'passed'   => $passed,
            'warnings' => $warned,
            'errors'   => $failed,
            'checks'   => $this->checks,
            'warnings_list' => $this->warnings,
            'errors_list'   => $this->errors,
        ];
    }

    // ─── Individual Checks ──────────────────────────────────────────────────

    private function checkDesignTokenFile(): void
    {
        $path = realpath(__DIR__ . '/../../resources/css/design-tokens.css');
        if ($path && file_exists($path)) {
            $this->pass('design-token-file', 'Canonical design-tokens.css exists in core-utils');
        } else {
            $this->fail('design-token-file', 'design-tokens.css missing from core-utils/resources/css/');
        }
    }

    private function checkTokenImport(): void
    {
        $appCss = resource_path('css/app.css');
        if (file_exists($appCss) && str_contains(file_get_contents($appCss), 'design-tokens.css')) {
            $this->pass('token-import', 'app.css imports canonical design-tokens.css');
        } else {
            $this->warn('token-import', 'app.css does not import design-tokens.css — tokens may not be available');
        }
    }

    private function checkCoreUiNamespaceRegistered(): void
    {
        // Verify core-utils components directory exists (registration happens in CoreUtilsServiceProvider)
        $dir = realpath(__DIR__ . '/../../resources/views/components');
        if ($dir && is_dir($dir)) {
            $files = glob($dir . '/*.blade.php');
            $this->pass('core-ui-namespace', 'core-ui namespace directory exists with ' . count($files) . ' components');
        } else {
            $this->fail('core-ui-namespace', 'core-utils/resources/views/components/ directory missing — core-ui namespace broken');
        }
    }

    private function checkRequiredComponents(): void
    {
        $dir = realpath(__DIR__ . '/../../resources/views/components');
        $missing = [];

        foreach (self::REQUIRED_COMPONENTS as $component) {
            $file = $dir . '/' . $component . '.blade.php';
            if (! file_exists($file)) {
                $missing[] = 'x-core-ui::' . $component;
            }
        }

        if (empty($missing)) {
            $this->pass('required-components', 'All ' . count(self::REQUIRED_COMPONENTS) . ' required core-ui components exist');
        } else {
            $this->fail('required-components', 'Missing canonical components: ' . implode(', ', $missing));
        }
    }

    private function checkForbiddenInlineStyles(): void
    {
        $violations = [];

        foreach ($this->viewDirectories() as $key => $dir) {
            $blades = $this->findBladeFiles($dir);
            foreach ($blades as $file) {
                $content = file_get_contents($file);
                // Match style= attributes (not inside <style> blocks or PHP strings)
                if (preg_match('/\sstyle\s*=\s*["\'][^"\']+["\']/i', $content)) {
                    $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
                    $violations[] = $relative;
                }
            }
        }

        if (empty($violations)) {
            $this->pass('no-inline-styles', 'No inline style= attributes found in Blade views');
        } else {
            $count = count($violations);
            $this->warn('no-inline-styles', "{$count} file(s) contain inline style= attributes: " . implode(', ', array_slice($violations, 0, 5)) . ($count > 5 ? '...' : ''));
        }
    }

    private function checkForbiddenTailwindColors(): void
    {
        $violations = [];

        foreach ($this->viewDirectories() as $key => $dir) {
            $blades = $this->findBladeFiles($dir);
            foreach ($blades as $file) {
                $content = file_get_contents($file);
                foreach (self::FORBIDDEN_COLORS as $cls) {
                    if (str_contains($content, $cls)) {
                        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
                        $violations[$relative][] = $cls;
                        break;
                    }
                }
            }
        }

        if (empty($violations)) {
            $this->pass('no-forbidden-colors', 'No forbidden Tailwind color utilities found');
        } else {
            $this->warn('no-forbidden-colors', count($violations) . ' file(s) use forbidden raw Tailwind color classes');
        }
    }

    private function checkDuplicateModalImplementations(): void
    {
        $localModals = [];
        $platformViews = resource_path('views');

        if (is_dir($platformViews)) {
            foreach ($this->findBladeFiles($platformViews) as $file) {
                $content = file_get_contents($file);
                // Detect custom modal logic NOT using x-core-ui::modal
                if (
                    (str_contains($content, 'x-show') && str_contains($content, 'inset-0') && str_contains($content, 'z-'))
                    && ! str_contains($content, 'x-core-ui::modal')
                    && ! str_contains($content, 'x-core-ui::confirm-modal')
                ) {
                    $localModals[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
                }
            }
        }

        if (empty($localModals)) {
            $this->pass('no-duplicate-modals', 'No non-canonical modal implementations detected');
        } else {
            $this->warn('no-duplicate-modals', count($localModals) . ' view(s) implement modal logic outside core-ui: ' . implode(', ', array_slice($localModals, 0, 3)));
        }
    }

    private function checkDuplicateButtonSystems(): void
    {
        $appCss = resource_path('css/app.css');
        $hasLegacyBtnSystem = false;

        if (file_exists($appCss)) {
            $content = file_get_contents($appCss);
            // Check for @layer components with .btn- declarations
            if (preg_match('/\.btn-primary\s*\{[^}]+background/', $content)) {
                $hasLegacyBtnSystem = true;
            }
        }

        if (! $hasLegacyBtnSystem) {
            $this->pass('no-duplicate-buttons', 'No legacy .btn-* CSS button system detected');
        } else {
            $this->warn('no-duplicate-buttons', 'Legacy .btn-* CSS button system exists in app.css alongside x-core-ui::button — remove after migration');
        }
    }

    private function checkCustomerSiteOverrides(): void
    {
        $customerFormsOverride  = base_path('../customer-site/resources/views/admin/forms');
        $customerTicketsOverride = base_path('../customer-site/resources/views/admin/tickets');

        $hasOverride = is_dir($customerFormsOverride) || is_dir($customerTicketsOverride);

        if (! $hasOverride) {
            $this->pass('no-customer-site-overrides', 'customer-site has no admin/forms or admin/tickets view overrides');
        } else {
            $this->fail('no-customer-site-overrides', 'customer-site contains module view overrides — these create visual drift');
        }
    }

    private function checkFormModuleCanonical(): void
    {
        $formsProvider = base_path('packages/saas-platform/modules/forms/src/Providers/FormsServiceProvider.php');
        if (! file_exists($formsProvider)) {
            $this->warn('forms-module-canonical', 'FormsServiceProvider not found — forms module may not be installed');
            return;
        }

        $content = file_get_contents($formsProvider);
        if (str_contains($content, "anonymousComponentPath") && str_contains($content, "'core-ui'")) {
            $this->pass('forms-module-canonical', 'FormsServiceProvider registers core-ui components via anonymousComponentPath');
        } else {
            $this->warn('forms-module-canonical', 'FormsServiceProvider does not register core-ui component path');
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function findBladeFiles(string $dir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && Str::endsWith($file->getFilename(), '.blade.php')) {
                $files[] = $file->getRealPath();
            }
        }
        return $files;
    }

    private function pass(string $key, string $message): void
    {
        $this->checks[$key] = ['status' => 'pass', 'message' => $message];
    }

    private function warn(string $key, string $message): void
    {
        $this->warnings[$key] = ['status' => 'warning', 'message' => $message];
        $this->checks[$key]   = ['status' => 'warning', 'message' => $message];
    }

    private function fail(string $key, string $message): void
    {
        $this->errors[$key] = ['status' => 'fail', 'message' => $message];
        $this->checks[$key] = ['status' => 'fail', 'message' => $message];
    }

    private function computeScore(): int
    {
        $total  = count($this->checks);
        if ($total === 0) return 0;

        $passes   = count(array_filter($this->checks, fn($c) => $c['status'] === 'pass'));
        $warnings = count(array_filter($this->checks, fn($c) => $c['status'] === 'warning'));
        $weight   = $passes + ($warnings * 0.5);

        return (int) round(($weight / $total) * 100);
    }

    private function grade(int $score): string
    {
        return match (true) {
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
}
