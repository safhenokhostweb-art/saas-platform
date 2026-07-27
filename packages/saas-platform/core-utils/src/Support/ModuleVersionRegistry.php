<?php

namespace SaaSPlatform\CoreUtils\Support;

/**
 * PHASE 6.98 — TASK 1: Central Module Version Registry
 *
 * Dynamically discovers installed modules from filesystem.
 * Supports both local development (path repositories) and
 * production (vendor/) mode without hardcoded module lists.
 *
 * Discovery priority:
 *   1. packages/saas-platform/modules/*  (local dev, symlink mode)
 *   2. vendor/saas-platform/modules-*    (production, Satis mode)
 */
class ModuleVersionRegistry
{
    protected array $cache = [];

    /**
     * Discover and return all installed modules keyed by module_slug.
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        if (!empty($this->cache)) {
            return $this->cache;
        }

        $modules = [];

        // Local dev: packages/saas-platform/modules/*/module.json
        foreach (glob(base_path('packages/saas-platform/modules/*/module.json')) ?: [] as $path) {
            $data = $this->readJson($path);
            if ($data && isset($data['module_slug'])) {
                $data['_source'] = 'local';
                $data['_path']   = dirname($path);
                $modules[$data['module_slug']] = $data;
            }
        }

        // Production: vendor/saas-platform/modules-*/module.json
        // Local takes precedence — only add if not already discovered
        foreach (glob(base_path('vendor/saas-platform/modules-*/module.json')) ?: [] as $path) {
            $data = $this->readJson($path);
            if ($data && isset($data['module_slug']) && !isset($modules[$data['module_slug']])) {
                $data['_source'] = 'vendor';
                $data['_path']   = dirname($path);
                $modules[$data['module_slug']] = $data;
            }
        }

        $this->cache = $modules;
        return $this->cache;
    }

    /**
     * Get a specific module's full data.
     */
    public function get(string $module): ?array
    {
        return $this->all()[$module] ?? null;
    }

    /**
     * Get the installed version of a module.
     */
    public function version(string $module): ?string
    {
        return $this->get($module)['version'] ?? null;
    }

    /**
     * Get the schema version of a module.
     * Falls back to "1" if not declared (backwards compat).
     */
    public function schemaVersion(string $module): ?string
    {
        return $this->get($module)['schema_version'] ?? '1';
    }

    /**
     * Get compatibility constraints for a module.
     *
     * @return array{php?: string, laravel?: string, saas-platform?: string}
     */
    public function compatibility(string $module): array
    {
        return $this->get($module)['compatibility'] ?? [];
    }

    /**
     * Phase 17-F — Part 5: declared dependencies for a module, straight
     * from its module.json. Entries in `modules` may be a bare slug
     * ("core-users") or a slug with a version constraint
     * ("core-users>=1.0") — see App\Services\Modules\
     * ModuleDeploymentDependencyResolver, the only current consumer.
     *
     * @return array{packages: string[], modules: string[]}
     */
    public function dependencies(string $module): array
    {
        $deps = $this->get($module)['dependencies'] ?? [];

        return [
            'packages' => $deps['packages'] ?? [],
            'modules' => $deps['modules'] ?? [],
        ];
    }

    /**
     * Phase 17-F — Part 6: declared uninstall strategy, if any. Contract
     * only — see ModuleUninstallStrategyInterface; nothing reads this for
     * a real uninstall yet.
     *
     * @return array{type?: string, cleanup?: string[]}|null
     */
    public function uninstallStrategy(string $module): ?array
    {
        return $this->get($module)['uninstall'] ?? null;
    }

    /**
     * Check if a module is compatible with a given platform version.
     */
    public function isCompatible(string $module, string $platformVersion): bool
    {
        $compat = $this->compatibility($module);

        if (!empty($compat['php'])) {
            $minPhp = ltrim($compat['php'], '^~>=<');
            if (!version_compare(PHP_VERSION, $minPhp, '>=')) {
                return false;
            }
        }

        if (!empty($compat['laravel'])) {
            $minLaravel = ltrim($compat['laravel'], '^~>=<');
            if (!version_compare(app()->version(), $minLaravel, '>=')) {
                return false;
            }
        }

        if (!empty($compat['saas-platform'])) {
            $minPlatform = ltrim($compat['saas-platform'], '^~>=<');
            if (!version_compare($platformVersion, $minPlatform, '>=')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the module name (human-readable).
     */
    public function name(string $module): ?string
    {
        return $this->get($module)['name'] ?? null;
    }

    /**
     * Get the module type (core, addon, etc.).
     */
    public function type(string $module): ?string
    {
        return $this->get($module)['type'] ?? null;
    }

    /**
     * Get the source of a module (local or vendor).
     */
    public function source(string $module): ?string
    {
        return $this->get($module)['_source'] ?? null;
    }

    /**
     * Get the filesystem path of a module.
     */
    public function path(string $module): ?string
    {
        return $this->get($module)['_path'] ?? null;
    }

    /**
     * Clear the internal cache.
     * Call this after version bumps or module installs.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * Read and decode a JSON file.
     */
    protected function readJson(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }
}
