<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\PlatformContext;

/**
 * PHASE 6.95 — TASK 12: Drift Protection for Authority Layer
 *
 * Detects:
 * - forbidden platform UI in tenant
 * - direct layout hardcoding
 * - legacy module-features usage
 * - uncategorized features
 * - non-authority-aware blade rendering
 */
class DriftAuthorityCommand extends Command
{
    protected $signature = 'drift:authority
        {--ci : CI mode — fail on violations}
        {--fix : Attempt to auto-fix detected issues}';

    protected $description = 'Detect authority layer drift: forbidden UI, hardcoded layouts, legacy dependencies';

    /**
     * Track detected violations.
     */
    protected array $violations = [];

    public function handle(): int
    {
        $this->line('🔍 Scanning for Authority Layer Drift...');
        $this->line('');

        $this->detectForbiddenPlatformUiInTenant();
        $this->detectHardcodedLayouts();
        $this->detectLegacyModuleFeaturesUsage();
        $this->detectUncategorizedFeatures();
        $this->detectNonAuthorityAwareBlades();

        $this->displayResults();

        if ($this->option('fix')) {
            $this->attemptAutoFix();
        }

        $hasViolations = !empty($this->violations);

        if ($hasViolations && $this->option('ci')) {
            return 1;
        }

        return $hasViolations ? 1 : 0;
    }

    /**
     * Detect forbidden platform UI elements in tenant context.
     */
    protected function detectForbiddenPlatformUiInTenant(): void
    {
        $this->line('📋 Checking for forbidden platform UI in tenant...');

        $viewsPath = base_path('packages/saas-platform/modules/forms/resources/views');

        if (!is_dir($viewsPath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $relativePath = str_replace(base_path(), '', $file->getPathname());

                // Check for platform-only UI elements NOT wrapped in @platform/@endplatform
                $platformPatterns = [
                    'site.assignment' => '/سایت‌ها|اتصال به سایت|site.*assignment/i',
                    'licensing' => '/لایسنس|license|licensing/i',
                    'lifecycle' => '/lifecycle|چرخه عمر|ماژول/i',
                    'cross.site' => '/بین‌سایتی|cross.site/i',
                ];

                foreach ($platformPatterns as $key => $pattern) {
                    if (preg_match($pattern, $content)) {
                        // Check if it's properly wrapped in @platform
                        $lines = explode("\n", $content);
                        foreach ($lines as $i => $line) {
                            if (preg_match($pattern, $line)) {
                                // Look backwards for @platform
                                $isWrapped = false;
                                for ($j = $i; $j >= 0; $j--) {
                                    if (str_contains($lines[$j], '@endplatform')) {
                                        break;
                                    }
                                    if (str_contains($lines[$j], '@platform')) {
                                        $isWrapped = true;
                                        break;
                                    }
                                }
                                if (!$isWrapped) {
                                    $this->addViolation(
                                        'FORBIDDEN_PLATFORM_UI',
                                        "Potential platform UI not wrapped in @platform: {$relativePath}:line " . ($i + 1),
                                        $file->getPathname()
                                    );
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Detect hardcoded layout paths in views.
     */
    protected function detectHardcodedLayouts(): void
    {
        $this->line('📋 Checking for hardcoded layouts...');

        $viewsPath = base_path('packages/saas-platform/modules/forms/resources/views');

        if (!is_dir($viewsPath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $relativePath = str_replace(base_path(), '', $file->getPathname());

                // Check for @extends('layouts.*') or @extends('admin.*') hardcoded
                if (preg_match('/@extends\(\s*[\'"](layouts\.|admin\.|themes\.)/', $content)) {
                    $this->addViolation(
                        'HARDCODED_LAYOUT',
                        "Hardcoded layout path in {$relativePath}",
                        $file->getPathname()
                    );
                }
            }
        }
    }

    /**
     * Detect legacy module-features JSON runtime usage.
     */
    protected function detectLegacyModuleFeaturesUsage(): void
    {
        $this->line('📋 Checking for legacy module-features usage...');

        // Check ModuleFeatureService
        $servicePath = base_path('app/Services/ModuleFeatureService.php');
        if (file_exists($servicePath)) {
            $content = file_get_contents($servicePath);
            if (str_contains($content, 'module-features/')) {
                $this->addViolation(
                    'LEGACY_JSON_DEPENDENCY',
                    'ModuleFeatureService still references module-features/ path',
                    $servicePath
                );
            }
        }

        // Check all PHP files for direct module-features references
        $searchPaths = [
            base_path('app'),
            base_path('packages'),
        ];

        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($searchPath)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if (preg_match('/base_path\([\'"]module-features/', $content)) {
                        $relativePath = str_replace(base_path(), '', $file->getPathname());
                        $this->addViolation(
                            'LEGACY_JSON_DEPENDENCY',
                            "Direct module-features path reference in {$relativePath}",
                            $file->getPathname()
                        );
                    }
                }
            }
        }
    }

    /**
     * Detect features that are not categorized into platform/tenant/shared.
     */
    protected function detectUncategorizedFeatures(): void
    {
        $this->line('📋 Checking for uncategorized features...');

        $manifestPath = base_path('packages/saas-platform/modules/forms/config/module-manifest.php');

        if (file_exists($manifestPath)) {
            $manifest = require $manifestPath;

            if (isset($manifest['features']) && is_array($manifest['features'])) {
                // Check if flat 'features' list has items not in categorized sections
                $flatIds = array_column($manifest['features'], 'id');
                $platformIds = array_column($manifest['platform_features'] ?? [], 'id');
                $tenantIds = array_column($manifest['tenant_features'] ?? [], 'id');
                $sharedIds = array_column($manifest['shared_features'] ?? [], 'id');
                $categorizedIds = array_merge($platformIds, $tenantIds, $sharedIds);

                foreach ($flatIds as $id) {
                    if (!in_array($id, $categorizedIds, true)) {
                        $this->addViolation(
                            'UNCATEGORIZED_FEATURE',
                            "Feature [{$id}] exists in flat list but not in platform/tenant/shared sections",
                            $manifestPath
                        );
                    }
                }
            }
        }
    }

    /**
     * Detect Blade files that are not authority-aware.
     */
    protected function detectNonAuthorityAwareBlades(): void
    {
        $this->line('📋 Checking for non-authority-aware Blade rendering...');

        $viewsPath = base_path('packages/saas-platform/modules/forms/resources/views');

        if (!is_dir($viewsPath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $relativePath = str_replace(base_path(), '', $file->getPathname());

                // Check for x-module-features (legacy component)
                if (str_contains($content, 'x-module-features')) {
                    $this->addViolation(
                        'NON_AUTHORITY_AWARE',
                        "Legacy x-module-features component in {$relativePath}",
                        $file->getPathname()
                    );
                }
            }
        }
    }

    /**
     * Add a violation.
     */
    protected function addViolation(string $type, string $description, string $filePath): void
    {
        $this->violations[] = [
            'type' => $type,
            'description' => $description,
            'file' => $filePath,
        ];
    }

    /**
     * Display the drift scan results.
     */
    protected function displayResults(): void
    {
        if (empty($this->violations)) {
            $this->line('✅ No authority layer drift detected.');
            return;
        }

        $this->line('📊 Drift Scan Results:');
        $this->line('');

        $rows = [];
        foreach ($this->violations as $violation) {
            $rows[] = [
                '❌',
                $violation['type'],
                $violation['description'],
                str_replace(base_path(), '', $violation['file']),
            ];
        }

        $this->table(
            ['', 'Type', 'Description', 'File'],
            $rows
        );

        $this->line('');
        $this->line("❌ Found " . count($this->violations) . " authority drift violation(s).");
    }

    /**
     * Attempt to auto-fix detected issues.
     */
    protected function attemptAutoFix(): void
    {
        $this->line('');
        $this->line('🔧 Auto-fix mode enabled. Attempting fixes...');

        $fixed = 0;

        foreach ($this->violations as $violation) {
            switch ($violation['type']) {
                case 'LEGACY_JSON_DEPENDENCY':
                    // Can't auto-fix ModuleFeatureService logic
                    $this->warn("  Cannot auto-fix: {$violation['description']}");
                    break;

                case 'NON_AUTHORITY_AWARE':
                    // Replace x-module-features with authority-aware rendering
                    $content = file_get_contents($violation['file']);
                    if (str_contains($content, '<x-module-features')) {
                        $newContent = preg_replace(
                            '/<x-module-features\s+module="([^"]+)"\s*\/>/',
                            "@platform\n    {{-- Platform features rendered by CapabilityResolver --}}\n@endplatform\n@tenant\n    {{-- Tenant features rendered by CapabilityResolver --}}\n@endtenant",
                            $content
                        );
                        file_put_contents($violation['file'], $newContent);
                        $this->info("  Fixed: Replaced x-module-features in {$violation['file']}");
                        $fixed++;
                    }
                    break;

                default:
                    $this->warn("  Cannot auto-fix: {$violation['type']}");
                    break;
            }
        }

        $this->line('');
        $this->line("🔧 Auto-fixed {$fixed} violation(s).");
    }
}
