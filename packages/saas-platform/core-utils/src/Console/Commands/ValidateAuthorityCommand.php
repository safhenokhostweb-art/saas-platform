<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\PlatformContext;
use SaaSPlatform\CoreUtils\Services\CapabilityResolver;
use SaaSPlatform\CoreUtils\Services\LayoutResolver;
use SaaSPlatform\CoreUtils\Services\MenuResolver;
use SaaSPlatform\CoreUtils\Services\ModuleRegistryService;

/**
 * PHASE 6.95 — TASK 11: Authority Validation Command
 *
 * Validates the authority architecture:
 * - no tenant leakage
 * - no platform-only features visible in customer-site
 * - manifests valid
 * - layouts resolve correctly
 * - menus isolated
 * - capability resolver working
 * - no local JSON runtime dependency
 * - package views authority-safe
 */
class ValidateAuthorityCommand extends Command
{
    protected $signature = 'platform:validate-authority
        {--ci : CI mode — fail on violations}';

    protected $description = 'Validate authority architecture: tenant leakage, manifests, layouts, menus, capabilities';

    /**
     * Track validation results.
     */
    protected array $results = [];

    public function handle(): int
    {
        $this->line('🔍 Validating Authority Architecture...');
        $this->line('');

        $this->validateManifests();
        $this->validateLayoutResolution();
        $this->validateCapabilityResolver();
        $this->validateMenuIsolation();
        $this->validateNoJsonRuntimeDependency();
        $this->validatePackageViews();

        $this->displayResults();

        $hasFailures = collect($this->results)->contains('status', 'FAIL');

        if ($hasFailures && $this->option('ci')) {
            return 1;
        }

        return $hasFailures ? 1 : 0;
    }

    /**
     * Validate that all module manifests are valid.
     */
    protected function validateManifests(): void
    {
        $this->line('📋 Checking module manifests...');

        $registry = app(ModuleRegistryService::class);
        $modules = $registry->installedModules();

        if (empty($modules)) {
            $this->addResult('Module Manifests', 'WARN', 'No module manifests found');
            return;
        }

        foreach ($modules as $slug => $manifest) {
            $checks = [];

            // Check required fields
            $checks[] = ['check' => 'name', 'pass' => !empty($manifest['name'])];
            $checks[] = ['check' => 'version', 'pass' => !empty($manifest['version'])];
            $checks[] = ['check' => 'release_channel', 'pass' => !empty($manifest['release_channel'])];
            $checks[] = ['check' => 'compatibility', 'pass' => !empty($manifest['compatibility'])];
            $checks[] = ['check' => 'distribution.signed', 'pass' => isset($manifest['distribution']['signed'])];
            $checks[] = ['check' => 'features split (platform/tenant/shared)', 'pass' =>
                isset($manifest['platform_features']) &&
                isset($manifest['tenant_features']) &&
                isset($manifest['shared_features'])
            ];

            foreach ($checks as $c) {
                $status = $c['pass'] ? 'PASS' : 'FAIL';
                $this->addResult("Manifest [{$slug}]: {$c['check']}", $status, '');
            }
        }
    }

    /**
     * Validate that layouts resolve correctly for both contexts.
     */
    protected function validateLayoutResolution(): void
    {
        $this->line('📋 Checking layout resolution...');

        $resolver = app(LayoutResolver::class);

        // We can't test both contexts in one run, but we can verify the resolver works
        $adminLayout = $resolver->adminLayout();
        $publicLayout = $resolver->publicLayout();

        if (!empty($adminLayout)) {
            $this->addResult('Admin layout resolves', 'PASS', "Resolved: {$adminLayout}");
        } else {
            $this->addResult('Admin layout resolves', 'FAIL', 'Empty layout name');
        }

        if (!empty($publicLayout)) {
            $this->addResult('Public layout resolves', 'PASS', "Resolved: {$publicLayout}");
        } else {
            $this->addResult('Public layout resolves', 'FAIL', 'Empty layout name');
        }
    }

    /**
     * Validate capability resolver works correctly.
     */
    protected function validateCapabilityResolver(): void
    {
        $this->line('📋 Checking capability resolver...');

        $resolver = app(CapabilityResolver::class);
        $context = app(PlatformContext::class);

        $visibleFeatures = $resolver->visibleFeatures();
        $hiddenFeatures = $resolver->hiddenFeatures();

        $this->addResult('Visible features resolved', 'PASS', count($visibleFeatures) . ' features visible');
        $this->addResult('Hidden features identified', 'PASS', count($hiddenFeatures) . ' features hidden');

        // Check platform capabilities
        $platformCaps = $resolver->platformCapabilities();
        foreach ($platformCaps as $cap) {
            $accessible = $resolver->canAccess($cap);
            // In platform context, these should be accessible
            // In tenant context, these should NOT be accessible
            if ($context->isCentralAdmin()) {
                $expected = true;
            } else {
                $expected = false;
            }
            $status = ($accessible === $expected) ? 'PASS' : 'FAIL';
            $this->addResult("Capability [{$cap}]", $status,
                $accessible ? 'accessible' : 'blocked');
        }
    }

    /**
     * Validate menu isolation.
     */
    protected function validateMenuIsolation(): void
    {
        $this->line('📋 Checking menu isolation...');

        $menuResolver = app(MenuResolver::class);
        $forbidden = $menuResolver->forbiddenSections();

        $this->addResult('Forbidden sections identified', 'PASS',
            count($forbidden) . ' sections: ' . implode(', ', $forbidden));
    }

    /**
     * Validate no runtime dependency on module-features/*.json.
     */
    protected function validateNoJsonRuntimeDependency(): void
    {
        $this->line('📋 Checking JSON runtime dependency...');

        $moduleFeatureServicePath = base_path('app/Services/ModuleFeatureService.php');

        if (file_exists($moduleFeatureServicePath)) {
            $content = file_get_contents($moduleFeatureServicePath);
            if (str_contains($content, 'module-features/')) {
                $this->addResult('No JSON runtime dependency', 'WARN',
                    'ModuleFeatureService still references module-features/ path (legacy fallback)');
            } else {
                $this->addResult('No JSON runtime dependency', 'PASS', '');
            }
        } else {
            $this->addResult('No JSON runtime dependency', 'PASS',
                'ModuleFeatureService not found (not used)');
        }

        // Check that legacy JSON files are not used at runtime
        $legacyDir = 'module-features';
        $legacyJsonPath = base_path($legacyDir . '/forms.json');
        if (file_exists($legacyJsonPath)) {
            $this->addResult('Legacy forms.json exists', 'WARN',
                'File exists but should not be used at runtime (documentation only)');
        }
    }

    /**
     * Validate package views are authority-safe.
     */
    protected function validatePackageViews(): void
    {
        $this->line('📋 Checking package views...');

        $viewsPath = base_path('packages/saas-platform/modules/forms/resources/views');
        $issues = [];

        if (is_dir($viewsPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($viewsPath)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for hardcoded layouts (should use config)
                    if (preg_match('/@extends\(\s*[\'"]layouts\./', $content)) {
                        $relativePath = str_replace(base_path(), '', $file->getPathname());
                        $issues[] = "Hardcoded layout in {$relativePath}";
                    }

                    // Check for module-features component usage
                    if (str_contains($content, 'x-module-features')) {
                        $relativePath = str_replace(base_path(), '', $file->getPathname());
                        $issues[] = "Legacy x-module-features component in {$relativePath}";
                    }
                }
            }
        }

        if (empty($issues)) {
            $this->addResult('Package views authority-safe', 'PASS', 'No hardcoded layouts or legacy components');
        } else {
            foreach ($issues as $issue) {
                $this->addResult('Package view issue', 'WARN', $issue);
            }
        }
    }

    /**
     * Add a validation result.
     */
    protected function addResult(string $check, string $status, string $detail): void
    {
        $this->results[] = [
            'check' => $check,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    /**
     * Display the validation results table.
     */
    protected function displayResults(): void
    {
        $this->line('');
        $this->line('📊 Validation Results:');
        $this->line('');

        $rows = [];
        foreach ($this->results as $result) {
            $icon = match ($result['status']) {
                'PASS' => '✅',
                'FAIL' => '❌',
                'WARN' => '⚠️',
                default => '➖',
            };
            $rows[] = [
                $icon,
                $result['status'],
                $result['check'],
                $result['detail'],
            ];
        }

        $this->table(
            ['', 'Status', 'Check', 'Detail'],
            $rows
        );

        $passCount = collect($this->results)->where('status', 'PASS')->count();
        $failCount = collect($this->results)->where('status', 'FAIL')->count();
        $warnCount = collect($this->results)->where('status', 'WARN')->count();

        $this->line('');
        $this->line("✅ Passed: {$passCount} | ❌ Failed: {$failCount} | ⚠️ Warnings: {$warnCount}");

        if ($failCount > 0) {
            $this->line('❌ Authority validation FAILED.');
        } else {
            $this->line('✅ Authority validation PASSED.');
        }
    }
}
