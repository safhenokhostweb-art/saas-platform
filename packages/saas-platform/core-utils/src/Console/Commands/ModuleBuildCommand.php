<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Support\ModuleManifestSchema;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;

/**
 * PHASE 6.98 — TASK 2: module:build
 *
 * Validates a module's structure against the canonical architecture
 * before it can be released or distributed. Uses Forms module as
 * the canonical reference (PHASE 6.97).
 *
 * Checks:
 *  - module.json / composer.json completeness
 *  - Provider registration
 *  - Contracts directory
 *  - Views structure
 *  - Migrations
 *  - Publish tags in ServiceProvider
 *  - Authority rules (no hardcoded layouts)
 *  - Tenant segregation (no platform leakage in tenant views)
 */
class ModuleBuildCommand extends Command
{
    protected $signature = 'module:build
        {module : The module slug to validate}
        {--ci : Fail with exit code 1 on any violation}';

    protected $description = 'Validate a module\'s structure before release';

    protected array $results = [];

    public function handle(): int
    {
        $module   = $this->argument('module');
        $registry = app(ModuleVersionRegistry::class);
        $data     = $registry->get($module);

        if (!$data) {
            $this->error("Module [{$module}] not found.");
            return self::FAILURE;
        }

        $path = $data['_path'];
        $this->line('');
        $this->line("🔨 Building module: <info>{$module}</info> v{$data['version']}");
        $this->line("   Path: {$path}");
        $this->line('');

        $this->checkModuleJson($data, $path);
        $this->checkComposerJson($path);
        $this->checkProviderRegistration($module, $path, $data);
        $this->checkContracts($path);
        $this->checkViews($path);
        $this->checkMigrations($path);
        $this->checkPublishTags($path, $module);
        $this->checkAuthorityRules($path);
        $this->checkTenantSegregation($path);

        $this->displayResults();

        $failures = collect($this->results)->where('status', 'FAIL')->count();
        $warnings = collect($this->results)->where('status', 'WARN')->count();

        if ($failures > 0) {
            $this->line('');
            $this->error("Build FAILED — {$failures} violation(s) must be resolved before release.");
            return self::FAILURE;
        }

        $this->line('');
        $this->info("Build PASSED" . ($warnings > 0 ? " with {$warnings} warning(s)." : '.'));
        return self::SUCCESS;
    }

    // ─── Checks ─────────────────────────────────────────────────────────────

    /**
     * Phase 17-G — Part 5: delegates the required/recommended field lists
     * to ModuleManifestSchema (Phase 17-F) instead of a second, separately
     * maintained copy — the two commands check different things (this one
     * also does authority/tenant-segregation/provider-registration
     * governance checks ModuleManifestSchema knows nothing about) but must
     * never disagree about what module.json itself requires.
     */
    protected function checkModuleJson(array $data, string $path): void
    {
        $schemaResult = (new ModuleManifestSchema())->validate($data);

        foreach (ModuleManifestSchema::REQUIRED_FIELDS as $field) {
            $pass = !empty($data[$field]);
            $this->addResult("module.json → {$field}", $pass ? 'PASS' : 'FAIL', $pass ? '' : 'Field missing or empty');
        }

        foreach (ModuleManifestSchema::RECOMMENDED_FIELDS as $field) {
            $pass = !empty($data[$field]);
            $this->addResult("module.json → {$field}", $pass ? 'PASS' : 'WARN', $pass ? '' : 'Recommended field missing');
        }

        foreach ($schemaResult['errors'] as $error) {
            $this->addResult('module.json → schema', 'FAIL', $error);
        }
    }

    protected function checkComposerJson(string $path): void
    {
        $composerPath = $path . '/composer.json';
        if (!file_exists($composerPath)) {
            $this->addResult('composer.json exists', 'FAIL', 'File not found');
            return;
        }

        $json     = json_decode(file_get_contents($composerPath), true) ?? [];
        $required = ['name', 'type', 'require', 'autoload'];
        foreach ($required as $field) {
            $pass = !empty($json[$field]);
            $this->addResult("composer.json → {$field}", $pass ? 'PASS' : 'FAIL', $pass ? '' : 'Missing');
        }

        // Must require core-utils
        $requiresCoreUtils = isset($json['require']['saas-platform/core-utils']);
        $this->addResult('composer.json → requires core-utils', $requiresCoreUtils ? 'PASS' : 'WARN',
            $requiresCoreUtils ? '' : 'Dependency on core-utils not declared');
    }

    protected function checkProviderRegistration(string $module, string $path, array $data): void
    {
        $providerClass = $data['provider'] ?? '';
        if (empty($providerClass)) {
            $this->addResult('ServiceProvider declared', 'FAIL', 'provider field missing in module.json');
            return;
        }

        // Derive expected file path from namespace
        $parts        = explode('\\', $providerClass);
        $providerFile = $path . '/src/Providers/' . end($parts) . '.php';
        $exists       = file_exists($providerFile);
        $this->addResult('ServiceProvider file exists', $exists ? 'PASS' : 'FAIL',
            $exists ? '' : "Expected: {$providerFile}");

        if ($exists) {
            $content = file_get_contents($providerFile);
            $this->addResult('ServiceProvider → boot() defined',
                str_contains($content, 'function boot') ? 'PASS' : 'FAIL', '');
            $this->addResult('ServiceProvider → register() defined',
                str_contains($content, 'function register') ? 'PASS' : 'FAIL', '');
        }
    }

    protected function checkContracts(string $path): void
    {
        $contractsDir = $path . '/src/Contracts';
        $exists       = is_dir($contractsDir);
        $this->addResult('Contracts directory exists', $exists ? 'PASS' : 'WARN',
            $exists ? '' : 'No Contracts/ directory found');

        if ($exists) {
            $files = glob($contractsDir . '/**/*.php') ?: glob($contractsDir . '/*.php') ?: [];
            $this->addResult('Contracts defined', count($files) > 0 ? 'PASS' : 'WARN',
                count($files) . ' contract interface(s) found');
        }
    }

    protected function checkViews(string $path): void
    {
        $viewsDir = $path . '/resources/views';
        $exists   = is_dir($viewsDir);
        $this->addResult('Views directory exists', $exists ? 'PASS' : 'WARN',
            $exists ? '' : 'No resources/views/ directory');

        if ($exists) {
            $adminDir  = $viewsDir . '/admin';
            $tenantDir = $viewsDir . '/site';
            $this->addResult('Views → admin/ directory',  is_dir($adminDir)  ? 'PASS' : 'WARN', '');
            $this->addResult('Views → site/ directory',   is_dir($tenantDir) ? 'PASS' : 'WARN', '');
        }
    }

    protected function checkMigrations(string $path): void
    {
        // Accept lowercase or mixed-case Migrations directory
        $dirs = [
            $path . '/database/migrations',
            $path . '/Database/Migrations',
        ];

        $found = false;
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $found       = true;
                $migrations  = glob($dir . '/*.php') ?: [];
                $this->addResult('Migrations directory exists', 'PASS', count($migrations) . ' migration(s)');
                break;
            }
        }

        if (!$found) {
            $this->addResult('Migrations directory exists', 'WARN', 'No migrations directory (may be intentional)');
        }
    }

    protected function checkPublishTags(string $path, string $module): void
    {
        $providerFiles = glob($path . '/src/Providers/*.php') ?: [];
        if (empty($providerFiles)) {
            $this->addResult('Publish tags', 'WARN', 'No provider found to inspect tags');
            return;
        }

        $content = file_get_contents($providerFiles[0]);
        $hasPublishes = str_contains($content, '$this->publishes(');
        $this->addResult('Publish tags defined', $hasPublishes ? 'PASS' : 'WARN',
            $hasPublishes ? '' : 'No publishes() calls found in ServiceProvider');
    }

    protected function checkAuthorityRules(string $path): void
    {
        $viewsDir = $path . '/resources/views';
        if (!is_dir($viewsDir)) {
            return;
        }

        $issues  = [];
        $checked = 0;
        $iter    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));

        foreach ($iter as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $checked++;
            $content      = file_get_contents($file->getPathname());
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (preg_match('/@extends\(\s*[\'"]layouts\./', $content)) {
                $issues[] = "Hardcoded layout in {$relativePath}";
            }
            if (str_contains($content, 'x-module-features')) {
                $issues[] = "Legacy x-module-features in {$relativePath}";
            }
        }

        if (empty($issues)) {
            $this->addResult('Authority rules', 'PASS', "{$checked} views checked — no violations");
        } else {
            foreach ($issues as $issue) {
                $this->addResult('Authority rules violation', 'FAIL', $issue);
            }
        }
    }

    protected function checkTenantSegregation(string $path): void
    {
        $tenantViewsDir = $path . '/resources/views/site';
        if (!is_dir($tenantViewsDir)) {
            $this->addResult('Tenant segregation', 'PASS', 'No site/ views directory to check');
            return;
        }

        $violations = [];
        $iter       = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tenantViewsDir));

        foreach ($iter as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $content      = file_get_contents($file->getPathname());
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());

            // Platform-only patterns that must not appear in tenant views
            if (preg_match('/@(platform|central)\b/', $content)) {
                $violations[] = "@platform/@central directive in tenant view: {$relativePath}";
            }
        }

        if (empty($violations)) {
            $this->addResult('Tenant segregation', 'PASS', 'No platform leakage in site/ views');
        } else {
            foreach ($violations as $v) {
                $this->addResult('Tenant segregation violation', 'FAIL', $v);
            }
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    protected function addResult(string $check, string $status, string $detail): void
    {
        $this->results[] = compact('check', 'status', 'detail');
    }

    protected function displayResults(): void
    {
        $rows = array_map(function (array $r) {
            $icon = match ($r['status']) {
                'PASS' => '✅', 'FAIL' => '❌', 'WARN' => '⚠️', default => '➖',
            };
            return [$icon, $r['status'], $r['check'], $r['detail']];
        }, $this->results);

        $this->table(['', 'Status', 'Check', 'Detail'], $rows);

        $pass = collect($this->results)->where('status', 'PASS')->count();
        $fail = collect($this->results)->where('status', 'FAIL')->count();
        $warn = collect($this->results)->where('status', 'WARN')->count();
        $this->line("✅ {$pass} passed  ❌ {$fail} failed  ⚠️ {$warn} warnings");
    }
}
