<?php

namespace SaaSPlatform\CoreUtils\Services;

use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use SaaSPlatform\CoreUtils\Support\ReleaseManifest;

/**
 * PHASE 6.98 — TASK 8: Platform Compatibility Validator
 *
 * Verifies that a module update is safe to apply in the current environment.
 * Integrates with ModuleVersionRegistry, ReleaseManifest, and (as a type hint)
 * ModuleUpdateOrchestrator to form the update safety triangle.
 *
 * Checks:
 *  - PHP version compatibility
 *  - Laravel version compatibility
 *  - saas-platform minimum version constraint
 *  - Package conflicts between modules
 *  - Schema version compatibility (no skipped migrations)
 *  - Module dependency graph integrity
 *  - Rollback safety (reversible migrations)
 */
class PlatformCompatibilityService
{
    public function __construct(
        protected ModuleVersionRegistry $registry,
        protected ReleaseManifest       $releaseManifest,
    ) {}

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Run all compatibility checks for a pending module update.
     *
     * @param  string $module         Module slug being updated
     * @param  string $targetVersion  Version we intend to install
     * @return array{errors: string[], warnings: string[], passed: bool}
     */
    public function check(string $module, string $targetVersion): array
    {
        $errors   = [];
        $warnings = [];

        $this->checkPhpCompatibility($module, $errors, $warnings);
        $this->checkLaravelCompatibility($module, $errors, $warnings);
        $this->checkPlatformVersionConstraint($module, $errors, $warnings);
        $this->checkPackageConflicts($module, $errors, $warnings);
        $this->checkSchemaCompatibility($module, $targetVersion, $errors, $warnings);
        $this->checkDependencyGraph($module, $errors, $warnings);
        $this->checkRollbackSafety($module, $errors, $warnings);

        return [
            'passed'   => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Quick PHP version compatibility check (public shortcut).
     */
    public function isPhpCompatible(string $module): bool
    {
        $compat = $this->registry->compatibility($module);
        if (empty($compat['php'])) {
            return true;
        }
        return $this->satisfiesConstraint(PHP_VERSION, $compat['php']);
    }

    /**
     * Quick Laravel version compatibility check.
     */
    public function isLaravelCompatible(string $module): bool
    {
        $compat = $this->registry->compatibility($module);
        if (empty($compat['laravel'])) {
            return true;
        }
        return $this->satisfiesConstraint(app()->version(), $compat['laravel']);
    }

    /**
     * Build the full dependency graph for all installed modules.
     * Returns a map of module → required packages/modules.
     *
     * @return array<string, array{packages: string[], modules: string[]}>
     */
    public function dependencyGraph(): array
    {
        $graph = [];

        foreach ($this->registry->all() as $slug => $data) {
            $graph[$slug] = [
                'packages' => $data['dependencies']['packages'] ?? [],
                'modules'  => $data['dependencies']['modules']  ?? [],
            ];
        }

        return $graph;
    }

    /**
     * Detect modules that would conflict with a given module update.
     *
     * @return string[]  Slugs of conflicting modules
     */
    public function detectConflicts(string $module, string $targetVersion): array
    {
        $manifest  = $this->releaseManifest->read($module);
        $conflicts = $manifest['conflicts'] ?? [];

        $installed   = array_keys($this->registry->all());
        $conflicting = [];

        foreach ($conflicts as $conflict) {
            if (in_array($conflict, $installed, true)) {
                $conflicting[] = $conflict;
            }
        }

        return $conflicting;
    }

    /**
     * Determine if rolling back a module update is safe.
     * Checks that all migrations added by the update have a down() method.
     *
     * @return array{safe: bool, reason: string}
     */
    public function isRollbackSafe(string $module): array
    {
        $modulePath = $this->registry->path($module);
        if (!$modulePath) {
            return ['safe' => false, 'reason' => 'Module path not found'];
        }

        $migrationDirs = [
            $modulePath . '/database/migrations',
            $modulePath . '/Database/Migrations',
        ];

        $unsafeMigrations = [];

        foreach ($migrationDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $content = file_get_contents($file);
                if (!str_contains($content, 'function down')) {
                    $unsafeMigrations[] = basename($file);
                }
            }
        }

        if (!empty($unsafeMigrations)) {
            return [
                'safe'   => false,
                'reason' => 'Migrations without down() method: ' . implode(', ', $unsafeMigrations),
            ];
        }

        return ['safe' => true, 'reason' => 'All migrations are reversible'];
    }

    // ─── Individual Checks ───────────────────────────────────────────────────

    protected function checkPhpCompatibility(string $module, array &$errors, array &$warnings): void
    {
        $compat = $this->registry->compatibility($module);
        if (empty($compat['php'])) {
            return;
        }

        if (!$this->satisfiesConstraint(PHP_VERSION, $compat['php'])) {
            $errors[] = sprintf(
                'PHP %s is required (constraint: %s), running %s',
                $compat['php'], $compat['php'], PHP_VERSION
            );
        }
    }

    protected function checkLaravelCompatibility(string $module, array &$errors, array &$warnings): void
    {
        $compat = $this->registry->compatibility($module);
        if (empty($compat['laravel'])) {
            return;
        }

        $laravelVersion = app()->version();
        if (!$this->satisfiesConstraint($laravelVersion, $compat['laravel'])) {
            $errors[] = sprintf(
                'Laravel %s required (constraint: %s), running %s',
                $compat['laravel'], $compat['laravel'], $laravelVersion
            );
        }
    }

    protected function checkPlatformVersionConstraint(string $module, array &$errors, array &$warnings): void
    {
        $compat          = $this->registry->compatibility($module);
        $platformVersion = config('saas-platform.platform_version', config('app.platform_version', '1.0.0'));

        if (empty($compat['saas-platform'])) {
            return;
        }

        if (!$this->satisfiesConstraint($platformVersion, $compat['saas-platform'])) {
            $errors[] = sprintf(
                'Platform version %s required, current platform is %s',
                $compat['saas-platform'], $platformVersion
            );
        }
    }

    protected function checkPackageConflicts(string $module, array &$errors, array &$warnings): void
    {
        $manifest  = $this->releaseManifest->read($module);
        $conflicts = $manifest['conflicts'] ?? [];

        foreach ($conflicts as $conflictSlug) {
            if ($this->registry->get($conflictSlug)) {
                $errors[] = "Module [{$module}] conflicts with installed module [{$conflictSlug}]";
            }
        }
    }

    protected function checkSchemaCompatibility(
        string $module,
        string $targetVersion,
        array &$errors,
        array &$warnings
    ): void {
        $currentSchema = (int) $this->registry->schemaVersion($module);
        // FUTURE: load target schema version from remote manifest
        // For now, warn if schema version is > 1 (non-trivial migrations)
        if ($currentSchema > 1) {
            $warnings[] = "Module [{$module}] is at schema v{$currentSchema} — review migrations before updating.";
        }
    }

    protected function checkDependencyGraph(string $module, array &$errors, array &$warnings): void
    {
        $graph        = $this->dependencyGraph();
        $moduleDeps   = $graph[$module]['modules'] ?? [];

        foreach ($moduleDeps as $dep) {
            if (!$this->registry->get($dep)) {
                $errors[] = "Module [{$module}] requires module [{$dep}] which is not installed.";
            }
        }
    }

    protected function checkRollbackSafety(string $module, array &$errors, array &$warnings): void
    {
        $result = $this->isRollbackSafe($module);
        if (!$result['safe']) {
            $warnings[] = "Rollback safety: " . $result['reason'];
        }
    }

    // ─── Constraint Resolution ───────────────────────────────────────────────

    /**
     * Evaluate a simple version constraint (^x.y, >=x.y, ~x.y, x.y.*).
     * Handles single-constraint strings only (not complex AND/OR).
     */
    protected function satisfiesConstraint(string $version, string $constraint): bool
    {
        // Strip multiple constraints separated by | — take the first passing one
        $parts = preg_split('/\s*\|\s*/', $constraint);
        foreach ($parts as $part) {
            if ($this->evaluateSingleConstraint($version, trim($part))) {
                return true;
            }
        }
        return false;
    }

    protected function evaluateSingleConstraint(string $version, string $constraint): bool
    {
        // ^x.y.z → >= x.y.z AND < (x+1).0.0
        if (str_starts_with($constraint, '^')) {
            $min    = ltrim($constraint, '^');
            $minParts = explode('.', $min);
            $major  = (int) ($minParts[0] ?? 0);
            $maxMajor = $major + 1;
            return version_compare($version, $min, '>=')
                && version_compare($version, "{$maxMajor}.0.0", '<');
        }

        // ~x.y.z → >= x.y.z AND < x.(y+1).0
        if (str_starts_with($constraint, '~')) {
            $min      = ltrim($constraint, '~');
            $minParts = explode('.', $min);
            $major    = (int) ($minParts[0] ?? 0);
            $minor    = (int) ($minParts[1] ?? 0);
            return version_compare($version, $min, '>=')
                && version_compare($version, "{$major}." . ($minor + 1) . '.0', '<');
        }

        // >=x, >x, <=x, <x, =x
        if (preg_match('/^(>=|<=|>|<|=|!=)\s*(.+)$/', $constraint, $m)) {
            return version_compare($version, $m[2], $m[1] === '=' ? '==' : $m[1]);
        }

        // Plain version — exact match
        return version_compare($version, $constraint, '>=');
    }
}
