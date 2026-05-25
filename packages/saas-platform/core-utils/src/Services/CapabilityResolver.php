<?php

namespace SaaSPlatform\CoreUtils\Services;

/**
 * PHASE 6.95 — TASK 3: Capability Resolver
 *
 * Resolves visible features, menu items, permissions, and module capabilities
 * based on the current runtime context.
 *
 * Capability source: canonical module manifests (config/module-manifest.php)
 * NOT local JSON files (module-features/*.json is deprecated).
 */
class CapabilityResolver
{
    public function __construct(
        protected PlatformContext $platformContext
    ) {}

    /**
     * Check if a specific feature/capability is accessible in the current context.
     */
    public function canAccess(string $feature): bool
    {
        $visible = $this->visibleFeatures();
        return in_array($feature, $visible, true);
    }

    /**
     * Get all visible features for the current context.
     * Reads from canonical module manifests.
     *
     * @return array<int, array>
     */
    public function visibleFeatures(): array
    {
        $features = [];

        // Collect from all registered module manifests
        $moduleManifests = $this->getRegisteredModuleManifests();

        foreach ($moduleManifests as $manifest) {
            $moduleFeatures = $this->resolveModuleFeatures($manifest);
            $features = array_merge($features, $moduleFeatures);
        }

        return $features;
    }

    /**
     * Get visible menu items for the current context.
     *
     * @return array<int, array>
     */
    public function visibleMenus(): array
    {
        $menus = [];

        $moduleManifests = $this->getRegisteredModuleManifests();

        foreach ($moduleManifests as $manifest) {
            if (isset($manifest['menu']) && is_array($manifest['menu'])) {
                foreach ($manifest['menu'] as $menuItem) {
                    if ($this->menuItemVisible($menuItem)) {
                        $menus[] = $menuItem;
                    }
                }
            }
        }

        return $menus;
    }

    /**
     * Get features that are hidden in the current context.
     *
     * @return array<int, string>
     */
    public function hiddenFeatures(): array
    {
        $allFeatures = [];
        $moduleManifests = $this->getRegisteredModuleManifests();

        foreach ($moduleManifests as $manifest) {
            $allFeatures = array_merge(
                $allFeatures,
                $manifest['platform_features'] ?? [],
                $manifest['tenant_features'] ?? [],
                $manifest['shared_features'] ?? [],
                $manifest['features'] ?? []
            );
        }

        $visibleIds = array_column($this->visibleFeatures(), 'id');
        $hidden = [];

        foreach ($allFeatures as $feature) {
            if (!in_array($feature['id'] ?? '', $visibleIds, true)) {
                $hidden[] = $feature['id'] ?? '';
            }
        }

        return array_values(array_filter($hidden));
    }

    /**
     * Get capabilities specific to tenant context.
     *
     * @return array<int, string>
     */
    public function tenantCapabilities(): array
    {
        return [
            'create_forms',
            'view_submissions',
            'export_reports',
            'form_preview',
            'basic_analytics',
        ];
    }

    /**
     * Get capabilities specific to platform context.
     *
     * @return array<int, string>
     */
    public function platformCapabilities(): array
    {
        return [
            'manage_sites',
            'manage_licensing',
            'feature_toggles',
            'sla_configuration',
            'lifecycle_management',
            'module_distribution',
            'cross_site_reports',
        ];
    }

    /**
     * Resolve module features based on current context.
     */
    protected function resolveModuleFeatures(array $manifest): array
    {
        $features = $manifest['shared_features'] ?? [];

        if ($this->platformContext->isCentralAdmin()) {
            $platformFeatures = $manifest['platform_features'] ?? [];
            $features = array_merge($features, $platformFeatures);
        } else {
            $tenantFeatures = $manifest['tenant_features'] ?? [];
            $features = array_merge($features, $tenantFeatures);
        }

        return $features;
    }

    /**
     * Check if a menu item is visible in the current context.
     */
    protected function menuItemVisible(array $menuItem): bool
    {
        $context = $menuItem['context'] ?? 'shared';

        if ($context === 'shared') {
            return true;
        }

        if ($context === 'platform' && $this->platformContext->isCentralAdmin()) {
            return true;
        }

        if ($context === 'tenant' && $this->platformContext->isTenant()) {
            return true;
        }

        return false;
    }

    /**
     * Get all registered module manifests from config.
     *
     * @return array<int, array>
     */
    protected function getRegisteredModuleManifests(): array
    {
        $manifests = [];

        // Scan config keys matching module-manifest-*
        $configKeys = ['module-manifest-forms'];

        foreach ($configKeys as $key) {
            $manifest = config($key);
            if ($manifest && is_array($manifest)) {
                $manifests[] = $manifest;
            }
        }

        return $manifests;
    }
}
