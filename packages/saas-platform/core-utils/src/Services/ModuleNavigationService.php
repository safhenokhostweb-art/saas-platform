<?php

namespace SaaSPlatform\CoreUtils\Services;

/**
 * PHASE 7.15 — TASK 5: Canonical Module Navigation Service
 *
 * Data-driven sidebar navigation for ALL modules across both projects.
 * Reads module manifests (config('module-manifest-*')) and applies
 * context-aware filtering so:
 *   - platform context → sees platform + shared items
 *   - tenant context   → sees tenant + shared items
 *
 * Both saas-platform and customer-site use the SAME service to build
 * sidebar module sections. No duplicated Blade navigation fragments.
 *
 * Integration:
 *   $nav = app(ModuleNavigationService::class);
 *   $items = $nav->getSidebarItems();
 *   // Blade: foreach($items as $item) { render sidebar-link }
 */
class ModuleNavigationService
{
    /**
     * Registered module manifest config keys.
     * Populated at boot time from ServiceProviders.
     */
    protected array $registeredModules = [];

    public function __construct(
        protected PlatformContext $context,
        protected CapabilityResolver $capabilityResolver,
    ) {}

    /**
     * Register a module's manifest config key.
     * Called from each module's ServiceProvider during boot.
     *
     * Example: $navService->registerModule('module-manifest-forms');
     */
    public function registerModule(string $manifestConfigKey): void
    {
        if (! in_array($manifestConfigKey, $this->registeredModules, true)) {
            $this->registeredModules[] = $manifestConfigKey;
        }
    }

    /**
     * Auto-discover registered module manifests from config keys.
     * Scans for all config('module-manifest-*') registered via mergeConfigFrom.
     */
    public function discoverModules(): void
    {
        $allConfig = config()->all();
        foreach (array_keys($allConfig) as $key) {
            if (str_starts_with($key, 'module-manifest-')) {
                $this->registerModule($key);
            }
        }
    }

    /**
     * Get all sidebar navigation items for the current context.
     * Returns items sorted by module, filtered by context visibility.
     *
     * @return array<int, array{key: string, title: string, route: string, icon: string, context: string, module: string}>
     */
    public function getSidebarItems(): array
    {
        $this->discoverModules();

        $items   = [];
        $context = $this->resolveContext();

        foreach ($this->registeredModules as $manifestKey) {
            $manifest = config($manifestKey, []);
            $slug     = $manifest['module_slug'] ?? '';
            $menu     = $manifest['menu'] ?? [];

            foreach ($menu as $item) {
                $itemContext = $item['context'] ?? 'shared';

                if (! $this->isVisibleInContext($itemContext, $context)) {
                    continue;
                }

                if (! $this->isRouteAccessible($item['route'] ?? '')) {
                    continue;
                }

                $items[] = [
                    'key'     => $item['key'] ?? $slug,
                    'title'   => $item['title'] ?? '',
                    'route'   => $item['route'] ?? '',
                    'icon'    => $item['icon'] ?? 'squares-2x2',
                    'context' => $itemContext,
                    'module'  => $slug,
                    'badge'   => $item['badge'] ?? null,
                ];
            }
        }

        return $items;
    }

    /**
     * Get all installed and visible modules for the current context.
     *
     * @return array<int, array{slug: string, name: string, version: string, context: string}>
     */
    public function getInstalledModules(): array
    {
        $this->discoverModules();

        $modules = [];

        foreach ($this->registeredModules as $manifestKey) {
            $manifest = config($manifestKey, []);

            if (empty($manifest)) {
                continue;
            }

            $modules[] = [
                'slug'    => $manifest['module_slug'] ?? '',
                'name'    => $manifest['module_name'] ?? $manifest['name'] ?? '',
                'version' => $manifest['version'] ?? '0.0.0',
                'context' => $this->resolveModuleContext($manifest),
            ];
        }

        return $modules;
    }

    /**
     * Get navigation items grouped by context for sidebar rendering.
     *
     * @return array{platform: array, tenant: array, shared: array}
     */
    public function getSidebarItemsByGroup(): array
    {
        $all = $this->getSidebarItems();

        return [
            'platform' => array_filter($all, fn($i) => $i['context'] === 'platform'),
            'tenant'   => array_filter($all, fn($i) => $i['context'] === 'tenant'),
            'shared'   => array_filter($all, fn($i) => $i['context'] === 'shared'),
        ];
    }

    /**
     * Check if a specific module is visible in the current context.
     */
    public function isModuleVisible(string $slug): bool
    {
        $this->discoverModules();

        foreach ($this->registeredModules as $manifestKey) {
            $manifest = config($manifestKey, []);
            if (($manifest['module_slug'] ?? '') === $slug) {
                $context = $this->resolveModuleContext($manifest);
                return $this->isVisibleInContext($context, $this->resolveContext());
            }
        }

        return false;
    }

    /**
     * Determine whether a menu item's context is visible in the runtime context.
     */
    protected function isVisibleInContext(string $itemContext, string $runtimeContext): bool
    {
        return match ($itemContext) {
            'shared'   => true,
            'platform' => $runtimeContext === 'platform',
            'tenant'   => $runtimeContext === 'tenant',
            default    => false,
        };
    }

    /**
     * Determine the primary context in which a module operates.
     */
    protected function resolveModuleContext(array $manifest): string
    {
        $hasPlatform = ! empty($manifest['platform_features']);
        $hasTenant   = ! empty($manifest['tenant_features']);
        $hasShared   = ! empty($manifest['shared_features']);

        if ($hasPlatform && $hasTenant) {
            return 'shared';
        }
        if ($hasPlatform) {
            return 'platform';
        }
        if ($hasTenant) {
            return 'tenant';
        }

        return 'shared';
    }

    /**
     * Map runtime context to simple platform|tenant string.
     */
    protected function resolveContext(): string
    {
        return $this->context->isCentralAdmin() ? 'platform' : 'tenant';
    }

    /**
     * Check if a named route exists and is accessible.
     */
    protected function isRouteAccessible(?string $routeName): bool
    {
        if (! $routeName) {
            return false;
        }

        try {
            return app('router')->has($routeName);
        } catch (\Throwable) {
            return false;
        }
    }
}
