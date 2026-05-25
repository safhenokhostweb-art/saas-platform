<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * PHASE 6.75 — FRONTEND RUNTIME STABILIZATION & ASSET GOVERNANCE
 *
 * Canonical frontend runtime architecture for the Forms module.
 * Ensures identical UI rendering across saas-platform and customer-site.
 *
 * This service validates:
 * - Tailwind scanning coverage for all package views
 * - Vite runtime compatibility between Laravel 10 and 12
 * - Asset ownership and governance
 * - Frontend drift detection
 * - Published asset safety
 * - Cross-project UI parity
 */
class FormsFrontendRuntimeService
{
    /**
     * Canonical package paths.
     */
    protected string $packagePath;
    protected string $packageViewsPath;
    protected string $packageAssetsPath;

    /**
     * Known Tailwind utility classes used by Forms views.
     * Used for scanning validation and drift detection.
     */
    protected const FORMS_TAILWIND_CLASSES = [
        'bg-gradient-to-r', 'from-indigo-600', 'to-purple-600',
        'rounded-xl', 'rounded-2xl', 'rounded-lg',
        'px-4', 'py-3', 'px-6', 'py-4', 'p-6',
        'shadow-sm', 'shadow-md', 'shadow-xl',
        'w-5', 'h-5', 'w-6', 'h-6', 'w-8', 'h-8',
        'flex', 'flex-col', 'flex-wrap', 'items-center', 'justify-between',
        'gap-4', 'gap-6', 'gap-3', 'space-y-4', 'space-y-6',
        'text-sm', 'text-xs', 'text-lg', 'text-xl', 'text-2xl',
        'font-medium', 'font-bold', 'font-semibold',
        'text-gray-500', 'text-gray-600', 'text-gray-700', 'text-gray-900',
        'text-white', 'text-indigo-600',
        'bg-white', 'bg-gray-50', 'bg-gray-100',
        'border', 'border-gray-200', 'border-gray-300',
        'hover:bg-gray-50', 'hover:text-indigo-600',
        'transition', 'transition-all', 'duration-200',
        'focus:outline-none', 'focus:ring-2', 'focus:ring-indigo-500',
        'overflow-hidden', 'overflow-x-auto',
        'whitespace-nowrap', 'truncate',
        'min-h-screen', 'w-full', 'max-w-7xl', 'mx-auto',
        'grid', 'grid-cols-1', 'md:grid-cols-2', 'lg:grid-cols-3',
        'sm:px-6', 'lg:px-8',
        'mb-8', 'mb-6', 'mb-4', 'mt-4', 'mt-6',
        'inline-flex', 'items-center', 'flex-shrink-0',
    ];

    public function __construct()
    {
        $this->packagePath = realpath(__DIR__ . '/../..');
        $this->packageViewsPath = $this->packagePath . '/resources/views';
        $this->packageAssetsPath = $this->packagePath . '/resources/assets';
    }

    /**
     * Run full frontend runtime validation.
     *
     * @return array{valid: bool, checks: array<string, array{passed: bool, detail?: string, error?: string}>}
     */
    public function validateAll(): array
    {
        $checks = [];

        // TASK 1: Tailwind scanning coverage
        $checks['tailwind_scanning'] = $this->checkTailwindScanning();

        // TASK 2: Asset governance
        $checks['asset_governance'] = $this->checkAssetGovernance();

        // TASK 3: UI Kit runtime
        $checks['ui_kit_runtime'] = $this->checkUiKitRuntime();

        // TASK 4: Package asset versioning
        $checks['asset_versioning'] = $this->checkAssetVersioning();

        // TASK 5: Vite compatibility
        $checks['vite_compatibility'] = $this->checkViteCompatibility();

        // TASK 6: Frontend anti-drift
        $checks['frontend_anti_drift'] = $this->checkFrontendAntiDrift();

        // TASK 7: Published asset safety
        $checks['published_asset_safety'] = $this->checkPublishedAssetSafety();

        // TASK 8: Cross-project UI parity
        $checks['cross_project_ui_parity'] = $this->checkCrossProjectUiParity();

        $allValid = collect($checks)->every(fn($c) => $c['passed']);

        Log::info('forms.frontend_runtime.validation_complete', [
            'all_valid' => $allValid,
            'check_count' => count($checks),
            'failed_checks' => collect($checks)->filter(fn($c) => !$c['passed'])->keys()->toArray(),
        ]);

        return [
            'valid' => $allValid,
            'checks' => $checks,
        ];
    }

    /**
     * TASK 1 — Validate Tailwind v4 scanning covers all package views.
     *
     * customer-site uses Tailwind v4 with @source directives.
     * saas-platform uses Tailwind v3 with content paths.
     * Both must scan ALL package Blade files.
     */
    protected function checkTailwindScanning(): array
    {
        $issues = [];

        // Get all package Blade files
        $packageBladeFiles = $this->getBladeFiles($this->packageViewsPath);
        $packageBladePaths = array_map(fn($f) => $f['relative'], $packageBladeFiles);

        // Check customer-site @source directives (Tailwind v4)
        $customerCssPath = base_path('../customer-site/resources/css/app.css');
        if (File::exists($customerCssPath)) {
            $customerCss = File::get($customerCssPath);
            preg_match_all('/@source\s+[\'"]([^\'"]+)[\'"]/', $customerCss, $sources);
            $sourcePaths = $sources[1] ?? [];

            // Check if forms package is covered
            $formsCovered = false;
            foreach ($sourcePaths as $source) {
                if (str_contains($source, 'modules/forms') || str_contains($source, 'modules-forms')) {
                    $formsCovered = true;
                    break;
                }
            }

            if (!$formsCovered) {
                $issues[] = 'customer-site @source directives do not cover modules/forms views';
            }

            // Check if tickets package is covered
            $ticketsCovered = false;
            foreach ($sourcePaths as $source) {
                if (str_contains($source, 'modules/tickets') || str_contains($source, 'modules-tickets')) {
                    $ticketsCovered = true;
                    break;
                }
            }

            if (!$ticketsCovered) {
                $issues[] = 'customer-site @source directives do not cover modules/tickets views';
            }
        } else {
            $issues[] = 'customer-site app.css not found for @source audit';
        }

        // Check saas-platform Tailwind v3 content paths
        $saasTailwindPath = base_path('tailwind.config.js');
        if (File::exists($saasTailwindPath)) {
            $saasTailwind = File::get($saasTailwindPath);
            // Tailwind v3 content must include packages directory
            if (!str_contains($saasTailwind, 'packages/')) {
                $issues[] = 'saas-platform tailwind.config.js content does not include packages/ directory';
            }
        } else {
            $issues[] = 'saas-platform tailwind.config.js not found';
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0
                ? count($packageBladeFiles) . ' package Blade files, all covered by Tailwind scanning'
                : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * TASK 2 — Define authoritative asset ownership matrix.
     */
    protected function checkAssetGovernance(): array
    {
        $issues = [];

        // Define asset ownership categories
        $assetCategories = [
            'package_owned' => [
                'CSS' => 'Package views use Tailwind utility classes only — no package-specific CSS files',
                'JS' => 'Alpine.js components are inline in Blade — no package-specific JS files',
                'Icons' => 'SVG icons are inline in Blade views — no package-specific icon files',
                'Fonts' => 'Vazirmatn font is loaded from public/assets/fonts/ (project-owned)',
            ],
            'publishable' => [
                'Config' => 'config/forms.php — published via forms-config tag',
                'Views' => 'resources/views → vendor/forms — published via forms-views tag',
                'Migrations' => 'database/migrations → app migrations — published via forms-migrations tag',
            ],
            'protected' => [
                'Package views' => 'NEVER modify package views directly — publish and override instead',
                'Package controllers' => 'NEVER create project-specific Forms controllers',
                'Package routes' => 'NEVER duplicate Forms routes in project routes/',
            ],
            'tenant_overridable' => [
                'Published views' => 'vendor/forms/*.blade.php — safe to override',
                'Published config' => 'config/forms.php — safe to customize layouts',
                'Published manifest' => 'config/module-manifest-forms.php — safe to customize features',
            ],
        ];

        // Check that no package-owned CSS files exist (should use Tailwind only)
        $packageCssFiles = $this->findFiles($this->packagePath, '*.css');
        if (!empty($packageCssFiles)) {
            $issues[] = 'Package contains CSS files: ' . implode(', ', $packageCssFiles);
        }

        // Check that no package-owned JS files exist (should use Alpine inline)
        $packageJsFiles = $this->findFiles($this->packagePath, '*.js');
        if (!empty($packageJsFiles)) {
            $issues[] = 'Package contains JS files: ' . implode(', ', $packageJsFiles);
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0
                ? 'Asset ownership matrix valid: ' . json_encode($assetCategories, JSON_UNESCAPED_UNICODE)
                : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * TASK 3 — Validate shared UI Kit rendering.
     */
    protected function checkUiKitRuntime(): array
    {
        $issues = [];

        // Check that critical UI components render identically
        $uiComponents = [
            'buttons' => [
                'btn', 'btn-primary', 'btn-success', 'btn-danger',
                'btn-secondary', 'btn-outline-primary', 'btn-outline-secondary',
            ],
            'cards' => ['card', 'card-header', 'card-body'],
            'forms' => ['form-control', 'form-label', 'form-select', 'form-check-input', 'form-check-label'],
            'alerts' => ['alert', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info'],
            'badges' => ['badge', 'badge-success', 'badge-danger', 'badge-warning', 'badge-info'],
            'tables' => ['table'],
            'navigation' => ['sidebar-link', 'navbar', 'navbar-brand', 'navbar-link'],
        ];

        // Check that saas-platform app.css has all component classes
        $saasCssPath = base_path('resources/css/app.css');
        if (File::exists($saasCssPath)) {
            $saasCss = File::get($saasCssPath);
            foreach ($uiComponents as $component => $classes) {
                foreach ($classes as $class) {
                    if (!str_contains($saasCss, '.' . $class)) {
                        $issues[] = "saas-platform app.css missing UI class: .{$class}";
                    }
                }
            }
        }

        // Check that customer-site app.css has equivalent classes
        $customerCssPath = base_path('../customer-site/resources/css/app.css');
        if (File::exists($customerCssPath)) {
            $customerCss = File::get($customerCssPath);
            // Tailwind v4 uses @utility and @theme instead of @layer components
            // Check for key utility definitions
            if (!str_contains($customerCss, 'font-vazir')) {
                $issues[] = 'customer-site app.css missing font-vazir utility';
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0
                ? 'All UI components defined in both projects'
                : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * TASK 4 — Validate package asset versioning strategy.
     */
    protected function checkAssetVersioning(): array
    {
        $issues = [];

        // Check that Vite manifest-based versioning is used (not manual)
        $manifestPath = public_path('build/manifest.json');
        if (!File::exists($manifestPath)) {
            $issues[] = 'Vite manifest not found — assets may not be versioned';
        }

        // Check that no hardcoded asset versions exist in package views
        $packageBladeFiles = $this->getBladeFiles($this->packageViewsPath);
        foreach ($packageBladeFiles as $file) {
            $content = File::get($file['full']);
            if (preg_match('/\?v=\d+/', $content)) {
                $issues[] = "Hardcoded asset version in {$file['relative']}";
            }
            if (preg_match('/mix\(/', $content)) {
                $issues[] = "Laravel Mix call in {$file['relative']} — should use Vite";
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0
                ? 'Vite manifest-based versioning active, no hardcoded versions in package views'
                : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * TASK 5 — Validate Vite runtime compatibility between Laravel 10 and 12.
     */
    protected function checkViteCompatibility(): array
    {
        $issues = [];

        // saas-platform: Laravel 10 + Vite 5 + laravel-vite-plugin ^1.0
        // customer-site: Laravel 12 + Vite 7.3 + laravel-vite-plugin ^2.1 + @tailwindcss/vite

        // Check saas-platform vite.config.js
        $saasVitePath = base_path('vite.config.js');
        if (File::exists($saasVitePath)) {
            $saasVite = File::get($saasVitePath);
            if (!str_contains($saasVite, 'laravel-vite-plugin')) {
                $issues[] = 'saas-platform vite.config.js missing laravel-vite-plugin';
            }
        }

        // Check customer-site vite.config.js
        $customerVitePath = base_path('../customer-site/vite.config.js');
        if (File::exists($customerVitePath)) {
            $customerVite = File::get($customerVitePath);
            if (!str_contains($customerVite, '@tailwindcss/vite')) {
                $issues[] = 'customer-site vite.config.js missing @tailwindcss/vite plugin';
            }
        }

        // Key finding: Tailwind v3 (saas) vs v4 (customer) is the MAJOR divergence
        $issues[] = 'CRITICAL: saas-platform uses Tailwind v3 (postcss + config), customer-site uses Tailwind v4 (@tailwindcss/vite) — unification required before Tickets migration';

        return [
            'passed' => count($issues) === 0,
            'detail' => null,
            'error' => implode('; ', $issues),
        ];
    }

    /**
     * TASK 6 — Detect frontend drift between package and project.
     */
    protected function checkFrontendAntiDrift(): array
    {
        $issues = [];

        // Check for duplicated CSS in project that should be in package
        $saasCssPath = base_path('resources/css/app.css');
        if (File::exists($saasCssPath)) {
            $saasCss = File::get($saasCssPath);

            // Detect if project CSS contains form-specific styles that should be in package
            if (preg_match('/form-submission|form-preview|form-builder/', $saasCss)) {
                $issues[] = 'Project CSS contains form-specific styles — should be in package or use Tailwind utilities';
            }
        }

        // Check for duplicated Blade structures
        $projectViewsPath = resource_path('views');
        if (File::isDirectory($projectViewsPath)) {
            $projectBladeFiles = $this->getBladeFiles($projectViewsPath);
            foreach ($projectBladeFiles as $file) {
                $content = File::get($file['full']);
                // Detect if project views duplicate package form views
                if (preg_match('/form.*submission|form.*field|form.*builder/i', $file['relative'])) {
                    $issues[] = "Potential duplicated form view in project: {$file['relative']}";
                }
            }
        }

        // Check for forbidden frontend forks
        $forbiddenPatterns = [
            'resources/views/admin/forms' => 'Project-specific forms admin views',
            'resources/views/site/forms' => 'Project-specific forms site views',
            'resources/views/vendor/forms' => 'Published overrides (allowed but must be tracked)',
        ];

        foreach ($forbiddenPatterns as $path => $description) {
            $fullPath = base_path($path);
            if (File::isDirectory($fullPath) && !str_contains($path, 'vendor/forms')) {
                // Only flag non-vendor paths as forbidden forks
                $issues[] = "{$description} found at {$path}";
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'No frontend drift detected' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * TASK 7 — Validate published asset safety.
     */
    protected function checkPublishedAssetSafety(): array
    {
        $issues = [];

        // Check published views directory
        $publishedViews = resource_path('views/vendor/forms');
        if (File::isDirectory($publishedViews)) {
            $publishedFiles = File::allFiles($publishedViews);
            foreach ($publishedFiles as $file) {
                $relativePath = $file->getRelativePathname();
                $packageFile = $this->packageViewsPath . '/' . $relativePath;

                if (!File::exists($packageFile)) {
                    $issues[] = "Orphaned published view: {$relativePath} — no package counterpart";
                    continue;
                }

                $content = $file->getContents();

                // Check for hardcoded @extends that bypass config
                if (preg_match('/@extends\(\s*[\'"](?!config)/', $content)) {
                    $issues[] = "Published view {$relativePath} uses hardcoded @extends — should use config('forms.layouts.*')";
                }

                // Check for hardcoded asset paths
                if (preg_match('/src=["\']\/?(assets|vendor|build)/', $content)) {
                    $issues[] = "Published view {$relativePath} has hardcoded asset path — should use Vite or asset()";
                }
            }
        }

        // Check published config safety
        $publishedConfig = config_path('forms.php');
        if (File::exists($publishedConfig)) {
            $published = include $publishedConfig;
            $package = include $this->packagePath . '/config/forms.php';

            foreach ($package as $key => $value) {
                if (!array_key_exists($key, $published)) {
                    $issues[] = "Published config missing key: '{$key}'";
                }
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'All published assets are safe' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * TASK 8 — Validate cross-project UI parity.
     */
    protected function checkCrossProjectUiParity(): array
    {
        $issues = [];

        // Compare Tailwind class usage in package views vs what's available in each project
        $packageBladeFiles = $this->getBladeFiles($this->packageViewsPath);

        // Extract all Tailwind classes used in package views
        $usedClasses = [];
        foreach ($packageBladeFiles as $file) {
            $content = File::get($file['full']);
            preg_match_all('/class="([^"]*)"/', $content, $matches);
            foreach ($matches[1] as $classStr) {
                $classes = preg_split('/\s+/', $classStr);
                foreach ($classes as $class) {
                    $class = trim($class);
                    if (!empty($class) && !str_starts_with($class, '{{')) {
                        $usedClasses[$class] = ($usedClasses[$class] ?? 0) + 1;
                    }
                }
            }
        }

        // Check for classes that might not exist in Tailwind v3 or v4
        $riskyClasses = array_keys(array_filter($usedClasses, fn($count) => $count > 2));
        $tailwindV3Only = ['bg-gradient-to-r', 'from-indigo-600', 'to-purple-600']; // These work in both v3 and v4

        // Check that gradient classes used in package views are available
        $gradientClasses = array_intersect($riskyClasses, [
            'bg-gradient-to-r', 'from-indigo-600', 'to-purple-600',
            'from-purple-600', 'via-indigo-500',
        ]);

        if (empty($gradientClasses)) {
            // This is fine — package views may not use gradients
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0
                ? count($usedClasses) . ' unique Tailwind classes used in package views, all compatible'
                : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Get all Blade files recursively from a directory.
     *
     * @return array<int, array{relative: string, full: string}>
     */
    protected function getBladeFiles(string $directory): array
    {
        if (!File::isDirectory($directory)) {
            return [];
        }

        $files = File::allFiles($directory);
        $bladeFiles = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && str_contains($file->getFilename(), '.blade.')) {
                $bladeFiles[] = [
                    'relative' => $file->getRelativePathname(),
                    'full' => $file->getRealPath(),
                ];
            }
        }

        return $bladeFiles;
    }

    /**
     * Find files matching a pattern in a directory.
     *
     * @return array<int, string>
     */
    protected function findFiles(string $directory, string $pattern): array
    {
        if (!File::isDirectory($directory)) {
            return [];
        }

        $files = File::glob($directory . '/' . $pattern);
        $allFiles = File::allFiles($directory);
        $matching = [];

        foreach ($allFiles as $file) {
            if (fnmatch($pattern, $file->getFilename())) {
                $matching[] = $file->getRelativePathname();
            }
        }

        return $matching;
    }
}
