<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ModuleFeatureService
{
    private const CACHE_TTL = 3600; // 60 minutes

    /**
     * Get module features from canonical config with caching.
     *
     * PHASE 6.95: Features are filtered by context (platform/tenant/shared).
     *   - saas-platform (platform): only platform_features + shared_features
     *   - customer-site (tenant): only tenant_features + shared_features
     *
     * NOTE: Legacy JSON files are no longer supported.
     * All module metadata must be defined in config/module-manifest-{slug}.php.
     */
    public function getModuleFeatures(string $moduleSlug): ?array
    {
        $cacheKey = "module_features_{$moduleSlug}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($moduleSlug) {
            // Read from canonical config only
            $configKey = "module-manifest-{$moduleSlug}";
            $canonicalConfig = config($configKey);

            if (!$canonicalConfig || !is_array($canonicalConfig)) {
                return null;
            }

            return $this->filterFeaturesByContext($canonicalConfig);
        });
    }

    /**
     * Filter features based on runtime context.
     *
     * The canonical manifest now has three sections:
     *   - platform_features:  only for saas-platform
     *   - tenant_features:    only for customer-site
     *   - shared_features:    for both
     *
     * This method merges the appropriate sections based on context.
     */
    protected function filterFeaturesByContext(array $manifest): array
    {
        // Determine context: check if PlatformContextService exists
        $isPlatform = false;
        if (class_exists(\SaaSPlatform\Modules\Forms\Services\PlatformContextService::class)) {
            try {
                $contextService = app(\SaaSPlatform\Modules\Forms\Services\PlatformContextService::class);
                $isPlatform = $contextService->isCentralAdmin();
            } catch (\Throwable $e) {
                // Service not registered, fall back to tenant context
                $isPlatform = false;
            }
        }

        // Merge appropriate features
        $features = $manifest['shared_features'] ?? [];

        if ($isPlatform) {
            // saas-platform: platform_features + shared_features
            $platformFeatures = $manifest['platform_features'] ?? [];
            $features = array_merge($features, $platformFeatures);
        } else {
            // customer-site: tenant_features + shared_features
            $tenantFeatures = $manifest['tenant_features'] ?? [];
            $features = array_merge($features, $tenantFeatures);
        }

        // Replace the flat 'features' list with context-filtered list
        $manifest['features'] = $features;

        return $manifest;
    }

    /**
     * Get all module slugs from canonical config.
     */
    public function getAllModuleSlugs(): array
    {
        // PHASE 6.95: Read from canonical config keys matching module-manifest-*
        // Legacy JSON files are no longer supported.
        $slugs = [];

        // Known module slugs
        $knownModules = ['forms'];

        foreach ($knownModules as $slug) {
            $configKey = "module-manifest-{$slug}";
            if (config($configKey)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * Clear cache for a specific module.
     */
    public function clearCache(string $moduleSlug): void
    {
        Cache::forget("module_features_{$moduleSlug}");
    }

    /**
     * Clear all module features caches.
     */
    public function clearAllCache(): void
    {
        foreach ($this->getAllModuleSlugs() as $slug) {
            $this->clearCache($slug);
        }
    }
}
