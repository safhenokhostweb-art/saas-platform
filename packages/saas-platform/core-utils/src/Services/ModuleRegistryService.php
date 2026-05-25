<?php

namespace SaaSPlatform\CoreUtils\Services;

/**
 * PHASE 6.95 — TASK 9: Central Module Registry Architecture
 *
 * This becomes the FUTURE DISTRIBUTION AUTHORITY.
 *
 * Responsibilities:
 * - installed modules
 * - module versions
 * - module metadata
 * - release channels
 * - entitlements
 * - checksums
 * - compatibility
 * - update eligibility
 *
 * NOTE: This phase creates the authority layer ONLY.
 * Remote API distribution will be implemented in a future phase.
 */
class ModuleRegistryService
{
    /**
     * Cache of loaded module manifests.
     */
    protected array $manifestCache = [];

    /**
     * Get all installed modules with their metadata.
     *
     * @return array<string, array>
     */
    public function installedModules(): array
    {
        $modules = [];

        // Scan registered module manifest config keys
        $configKeys = ['module-manifest-forms'];

        foreach ($configKeys as $key) {
            $manifest = config($key);
            if ($manifest && is_array($manifest)) {
                $slug = $manifest['module_slug'] ?? str_replace('module-manifest-', '', $key);
                $modules[$slug] = $this->normalizeManifest($manifest);
            }
        }

        return $modules;
    }

    /**
     * Get the version of a specific module.
     */
    public function moduleVersion(string $module): ?string
    {
        $manifest = $this->moduleManifest($module);
        return $manifest['version'] ?? null;
    }

    /**
     * Get the available (latest) version for a module.
     * Currently returns installed version. Future: checks remote registry.
     */
    public function availableVersion(string $module): ?string
    {
        // FUTURE: Check remote distribution API
        return $this->moduleVersion($module);
    }

    /**
     * Get the full manifest for a module.
     */
    public function moduleManifest(string $module): ?array
    {
        if (isset($this->manifestCache[$module])) {
            return $this->manifestCache[$module];
        }

        $configKey = "module-manifest-{$module}";
        $manifest = config($configKey);

        if ($manifest && is_array($manifest)) {
            $this->manifestCache[$module] = $this->normalizeManifest($manifest);
            return $this->manifestCache[$module];
        }

        return null;
    }

    /**
     * Get the checksum for a module manifest.
     */
    public function moduleChecksum(string $module): ?string
    {
        $manifest = $this->moduleManifest($module);
        if (!$manifest) {
            return null;
        }

        return $manifest['distribution']['checksum'] ?? null;
    }

    /**
     * Get tenant entitlements for a specific module.
     *
     * @return array<string, mixed>
     */
    public function tenantEntitlements(string $tenant): array
    {
        // FUTURE: Check licensing service for tenant-specific entitlements
        return [
            'licensed' => true,
            'max_forms' => null, // Unlimited
            'features' => [],
            'expires_at' => null,
        ];
    }

    /**
     * Get the update channel for a tenant.
     */
    public function updateChannel(string $tenant): string
    {
        // FUTURE: Check tenant subscription for channel
        return 'stable';
    }

    /**
     * Check if a module is compatible with the current platform version.
     */
    public function isCompatible(string $module): bool
    {
        $manifest = $this->moduleManifest($module);
        if (!$manifest || empty($manifest['compatibility'])) {
            return true; // Assume compatible if no constraints
        }

        $compat = $manifest['compatibility'];

        // Check PHP version
        if (!empty($compat['php'])) {
            $phpVersion = PHP_VERSION;
            if (!version_compare($phpVersion, ltrim($compat['php'], '^~>='), '>=')) {
                return false;
            }
        }

        // Check Laravel version
        if (!empty($compat['laravel'])) {
            $laravelVersion = app()->version();
            if (!version_compare($laravelVersion, ltrim($compat['laravel'], '^~>='), '>=')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a module has an update available.
     */
    public function hasUpdate(string $module): bool
    {
        $current = $this->moduleVersion($module);
        $available = $this->availableVersion($module);

        if (!$current || !$available) {
            return false;
        }

        return version_compare($available, $current, '>');
    }

    /**
     * Normalize a module manifest to ensure all required fields exist.
     */
    protected function normalizeManifest(array $manifest): array
    {
        return array_merge([
            'name' => '',
            'version' => '0.0.0',
            'release_channel' => 'stable',
            'compatibility' => [
                'php' => '^8.1',
                'laravel' => '^10.0',
                'platform' => '^1.0',
            ],
            'distribution' => [
                'signed' => false,
                'checksum' => null,
            ],
            'features' => [],
            'platform_features' => [],
            'tenant_features' => [],
            'shared_features' => [],
        ], $manifest);
    }
}
