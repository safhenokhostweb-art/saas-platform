<?php

namespace SaaSPlatform\CoreUtils\Services;

/**
 * PHASE 6.95 — TASK 8: Menu Isolation
 *
 * Builds sidebar menus by authority context.
 * Hides forbidden sections from tenant sites.
 *
 * Customer-site MUST NOT see:
 * - multi-site management
 * - licensing
 * - platform administration
 * - lifecycle controls
 * - global settings
 */
class MenuResolver
{
    /**
     * Menu items that are platform-only (hidden from tenant).
     */
    protected const PLATFORM_ONLY_MENUS = [
        'sites',
        'multi-site',
        'licensing',
        'platform-admin',
        'lifecycle',
        'global-settings',
        'module-distribution',
        'cross-site-reports',
    ];

    /**
     * Menu items that are tenant-only (hidden from platform).
     */
    protected const TENANT_ONLY_MENUS = [
        'my-forms',
        'my-submissions',
        'my-reports',
    ];

    public function __construct(
        protected PlatformContext $platformContext,
        protected CapabilityResolver $capabilityResolver
    ) {}

    /**
     * Build the sidebar menu for the current context.
     *
     * @param array<int, array> $allMenus All available menu items
     * @return array<int, array> Filtered menu items
     */
    public function buildSidebar(array $allMenus): array
    {
        $filtered = [];

        foreach ($allMenus as $menu) {
            if ($this->isMenuVisible($menu)) {
                // Recursively filter children
                if (isset($menu['children']) && is_array($menu['children'])) {
                    $menu['children'] = $this->buildSidebar($menu['children']);
                }
                $filtered[] = $menu;
            }
        }

        return $filtered;
    }

    /**
     * Check if a menu item is visible in the current context.
     */
    public function isMenuVisible(array $menu): bool
    {
        $key = $menu['key'] ?? $menu['id'] ?? '';

        // Platform-only menus: hidden from tenant
        if (in_array($key, self::PLATFORM_ONLY_MENUS, true) && !$this->platformContext->isCentralAdmin()) {
            return false;
        }

        // Tenant-only menus: hidden from platform
        if (in_array($key, self::TENANT_ONLY_MENUS, true) && !$this->platformContext->isTenant()) {
            return false;
        }

        // Check capability requirement
        if (!empty($menu['capability'])) {
            return $this->capabilityResolver->canAccess($menu['capability']);
        }

        // Check context requirement
        if (!empty($menu['context'])) {
            switch ($menu['context']) {
                case 'platform':
                    return $this->platformContext->isCentralAdmin();
                case 'tenant':
                    return $this->platformContext->isTenant();
                case 'shared':
                    return true;
                default:
                    return true;
            }
        }

        return true;
    }

    /**
     * Check if a specific section is forbidden in the current context.
     */
    public function isSectionForbidden(string $sectionKey): bool
    {
        if (in_array($sectionKey, self::PLATFORM_ONLY_MENUS, true) && !$this->platformContext->isCentralAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Get forbidden sections for the current context.
     *
     * @return array<int, string>
     */
    public function forbiddenSections(): array
    {
        if ($this->platformContext->isCentralAdmin()) {
            return self::TENANT_ONLY_MENUS;
        }

        return self::PLATFORM_ONLY_MENUS;
    }
}
