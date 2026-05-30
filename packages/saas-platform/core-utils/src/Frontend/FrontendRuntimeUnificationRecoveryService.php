<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Facades\File;

/**
 * Phase 10.2.8 — Frontend Runtime Unification Recovery Service
 *
 * Detects and auto-repairs Tailwind v4 runtime inconsistencies between
 * saas-platform (canonical) and customer-site (tenant runtime).
 * Supports --dry-run, --json, --cross-project flags.
 */
class FrontendRuntimeUnificationRecoveryService
{
    private const SAAS_ROOT   = __DIR__ . '/../../../../../../..';
    private const TENANT_ROOT = __DIR__ . '/../../../../../../../../../customer-site';

    private array $detectedIssues = [];
    private array $repairedItems  = [];
    private array $skippedItems   = [];

    public function detect(): array
    {
        $this->detectedIssues = [];

        $this->detectLegacyDirectives();
        $this->detectMissingVitePlugin();
        $this->detectVersionDrift();
        $this->detectTokenDrift();
        $this->detectBuildConfigDrift();
        $this->detectContentScanningDrift();
        $this->detectLegacyConfigFiles();

        return [
            'detected' => count($this->detectedIssues),
            'issues'   => $this->detectedIssues,
        ];
    }

    public function recover(bool $dryRun = false, bool $crossProject = false): array
    {
        $this->repairedItems = [];
        $this->skippedItems  = [];

        $detection = $this->detect();

        if (empty($this->detectedIssues)) {
            return [
                'status'   => 'healthy',
                'repaired' => 0,
                'skipped'  => 0,
                'message'  => 'No Tailwind runtime inconsistencies detected — system is healthy',
            ];
        }

        foreach ($this->detectedIssues as $issue) {
            $this->attemptRepair($issue, $dryRun);
        }

        return [
            'status'   => empty($this->repairedItems) ? 'manual-action-required' : 'repaired',
            'dry_run'  => $dryRun,
            'detected' => count($this->detectedIssues),
            'repaired' => count($this->repairedItems),
            'skipped'  => count($this->skippedItems),
            'issues'   => $this->detectedIssues,
            'repaired_items' => $this->repairedItems,
            'skipped_items'  => $this->skippedItems,
        ];
    }

    private function detectLegacyDirectives(): void
    {
        $saasAppCss = self::SAAS_ROOT . '/resources/css/app.css';
        $patterns   = ['@tailwind base', '@tailwind components', '@tailwind utilities'];

        foreach ($patterns as $pattern) {
            if ($this->fileContains($saasAppCss, $pattern)) {
                $this->detectedIssues[] = [
                    'type'        => 'legacy_directive',
                    'severity'    => 'critical',
                    'description' => "Legacy v3 directive '{$pattern}' found in saas-platform/resources/css/app.css",
                    'file'        => $saasAppCss,
                    'pattern'     => $pattern,
                    'repairable'  => true,
                    'repair_strategy' => 'replace_with_v4_import',
                ];
            }
        }
    }

    private function detectMissingVitePlugin(): void
    {
        $saasVite = self::SAAS_ROOT . '/vite.config.js';

        if (!$this->fileContains($saasVite, '@tailwindcss/vite')) {
            $this->detectedIssues[] = [
                'type'        => 'missing_vite_plugin',
                'severity'    => 'critical',
                'description' => '@tailwindcss/vite plugin missing from saas-platform/vite.config.js',
                'file'        => $saasVite,
                'repairable'  => false,
                'repair_strategy' => 'manual',
                'action'      => "Add: import tailwindcss from '@tailwindcss/vite' and tailwindcss() to plugins[]",
            ];
        }
    }

    private function detectVersionDrift(): void
    {
        $saasVer   = $this->getInstalledVersion(self::SAAS_ROOT . '/node_modules/tailwindcss/package.json');
        $tenantVer = $this->getInstalledVersion(self::TENANT_ROOT . '/node_modules/tailwindcss/package.json');

        $saasMajor   = $this->majorVersion($saasVer);
        $tenantMajor = $this->majorVersion($tenantVer);

        if ($saasMajor !== 4) {
            $this->detectedIssues[] = [
                'type'        => 'version_drift',
                'severity'    => 'critical',
                'description' => "saas-platform runs Tailwind v{$saasVer} (must be v4.x)",
                'installed'   => $saasVer,
                'required'    => '^4.x',
                'repairable'  => false,
                'repair_strategy' => 'manual',
                'action'      => 'Run: npm install tailwindcss@^4.2.0 @tailwindcss/vite@^4.2.0',
            ];
        }

        if ($saasMajor === 4 && $tenantMajor === 4 && $saasVer !== $tenantVer) {
            $this->detectedIssues[] = [
                'type'        => 'minor_version_drift',
                'severity'    => 'warning',
                'description' => "Minor version drift: saas={$saasVer} tenant={$tenantVer}",
                'saas_ver'    => $saasVer,
                'tenant_ver'  => $tenantVer,
                'repairable'  => false,
                'repair_strategy' => 'manual',
                'action'      => "Run npm install in customer-site to align to {$saasVer}",
            ];
        }
    }

    private function detectTokenDrift(): void
    {
        $tokenFile    = self::SAAS_ROOT . '/packages/saas-platform/core-utils/resources/css/design-tokens.css';
        $saasAppCss   = self::SAAS_ROOT . '/resources/css/app.css';
        $tenantAppCss = self::TENANT_ROOT . '/resources/css/app.css';

        if (!file_exists($tokenFile)) {
            $this->detectedIssues[] = [
                'type'        => 'token_drift',
                'severity'    => 'critical',
                'description' => 'Canonical design-tokens.css not found',
                'file'        => $tokenFile,
                'repairable'  => false,
                'repair_strategy' => 'manual',
            ];
            return;
        }

        if (!$this->fileContains($saasAppCss, 'design-tokens.css')) {
            $this->detectedIssues[] = [
                'type'        => 'token_import_missing',
                'severity'    => 'high',
                'description' => 'design-tokens.css not imported in saas-platform/resources/css/app.css',
                'file'        => $saasAppCss,
                'repairable'  => true,
                'repair_strategy' => 'add_token_import',
            ];
        }
    }

    private function detectBuildConfigDrift(): void
    {
        $saasTailwindConf = self::SAAS_ROOT . '/tailwind.config.js';
        $saasAppCss       = self::SAAS_ROOT . '/resources/css/app.css';

        if (file_exists($saasTailwindConf)) {
            $this->detectedIssues[] = [
                'type'        => 'legacy_config_file',
                'severity'    => 'high',
                'description' => 'tailwind.config.js present (v3 config format — should not exist in v4)',
                'file'        => $saasTailwindConf,
                'repairable'  => true,
                'repair_strategy' => 'delete_legacy_config',
            ];
        }

        if (!$this->fileContains($saasAppCss, '@theme')) {
            $this->detectedIssues[] = [
                'type'        => 'missing_theme_block',
                'severity'    => 'high',
                'description' => '@theme CSS configuration block missing from saas-platform/resources/css/app.css',
                'file'        => $saasAppCss,
                'repairable'  => false,
                'repair_strategy' => 'manual',
                'action'      => 'Add @theme { --font-sans: ...; --color-primary-*: ...; } block to app.css',
            ];
        }
    }

    private function detectContentScanningDrift(): void
    {
        $saasAppCss = self::SAAS_ROOT . '/resources/css/app.css';

        if (!$this->fileContains($saasAppCss, '@source')) {
            $this->detectedIssues[] = [
                'type'        => 'missing_source_directives',
                'severity'    => 'high',
                'description' => '@source content scanning directives missing from saas-platform/resources/css/app.css',
                'file'        => $saasAppCss,
                'repairable'  => false,
                'repair_strategy' => 'manual',
                'action'      => "Add @source paths replacing tailwind.config.js content[] array",
            ];
        }
    }

    private function detectLegacyConfigFiles(): void
    {
        $legacyFiles = [
            self::SAAS_ROOT . '/tailwind.config.js' => 'tailwind.config.js (v3 config — v4 uses CSS @theme)',
        ];

        foreach ($legacyFiles as $path => $description) {
            if (file_exists($path)) {
                $this->detectedIssues[] = [
                    'type'        => 'legacy_file',
                    'severity'    => 'medium',
                    'description' => "Legacy file present: {$description}",
                    'file'        => $path,
                    'repairable'  => true,
                    'repair_strategy' => 'delete_legacy_config',
                ];
            }
        }
    }

    private function attemptRepair(array $issue, bool $dryRun): void
    {
        $strategy = $issue['repair_strategy'] ?? 'manual';

        if ($strategy === 'manual') {
            $this->skippedItems[] = [
                'issue'  => $issue['description'],
                'reason' => 'Requires manual intervention',
                'action' => $issue['action'] ?? 'See issue description',
            ];
            return;
        }

        if ($strategy === 'delete_legacy_config') {
            $file = $issue['file'];
            if (!$dryRun && file_exists($file)) {
                File::delete($file);
                $this->repairedItems[] = [
                    'action' => "Deleted legacy config file: {$file}",
                    'issue'  => $issue['description'],
                ];
            } else {
                $this->repairedItems[] = [
                    'action'  => "[DRY RUN] Would delete: {$file}",
                    'issue'   => $issue['description'],
                    'dry_run' => true,
                ];
            }
            return;
        }

        if ($strategy === 'replace_with_v4_import') {
            $file    = $issue['file'];
            $pattern = $issue['pattern'];
            if (!$dryRun && file_exists($file)) {
                $content = file_get_contents($file);
                $content = str_replace($pattern . ';', '', $content);
                file_put_contents($file, $content);
                $this->repairedItems[] = [
                    'action' => "Removed legacy directive '{$pattern}' from {$file}",
                    'issue'  => $issue['description'],
                ];
            } else {
                $this->repairedItems[] = [
                    'action'  => "[DRY RUN] Would remove '{$pattern}' from {$file}",
                    'dry_run' => true,
                ];
            }
            return;
        }

        $this->skippedItems[] = [
            'issue'  => $issue['description'],
            'reason' => "No repair handler for strategy: {$strategy}",
        ];
    }

    private function fileContains(string $path, string $needle): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        return str_contains(file_get_contents($path), $needle);
    }

    private function getInstalledVersion(string $pkgPath): string
    {
        if (!file_exists($pkgPath)) {
            return 'not-installed';
        }
        $pkg = json_decode(file_get_contents($pkgPath), true);
        return $pkg['version'] ?? 'unknown';
    }

    private function majorVersion(string $version): int
    {
        return (int) explode('.', ltrim($version, 'v'))[0];
    }
}
