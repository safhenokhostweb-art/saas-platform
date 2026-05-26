<?php

namespace SaaSPlatform\CoreUtils\Services;

/**
 * PHASE 7.15 — TASK 2: Module-Agnostic Shared Layout Resolver
 *
 * Resolves admin, site, and auth layouts for ANY module based on the
 * current runtime context. Replaces the Forms-specific LayoutResolver.
 *
 * Usage:
 *   $resolver->adminLayout('tickets')   → reads config('tickets.layouts.admin')
 *   $resolver->adminLayout('forms')     → reads config('forms.layouts.admin')
 *   $resolver->adminLayout()            → uses default fallback chain
 *
 * Fallback chain (admin):
 *   1. config('{module}.layouts.admin_tenant') — when in tenant context
 *   2. config('{module}.layouts.admin')
 *   3. Platform: 'layouts.admin' | Tenant: 'admin.layout'
 *
 * All package views MUST use this resolver or config('module.layouts.key')
 * directly. Hardcoded layout strings in package views are FORBIDDEN.
 */
class SharedLayoutResolver
{
    public function __construct(
        protected PlatformContext $context
    ) {}

    /**
     * Resolve the admin layout for a given module in the current context.
     */
    public function adminLayout(string $moduleSlug = ''): string
    {
        if ($moduleSlug) {
            if ($this->context->isTenantAdmin()) {
                $tenantKey = config("{$moduleSlug}.layouts.admin_tenant");
                if ($tenantKey) {
                    return $tenantKey;
                }
            }

            $moduleKey = config("{$moduleSlug}.layouts.admin");
            if ($moduleKey) {
                return $moduleKey;
            }
        }

        return $this->context->isCentralAdmin() ? 'layouts.admin' : 'admin.layout';
    }

    /**
     * Resolve the site/public layout for a given module.
     */
    public function siteLayout(string $moduleSlug = ''): string
    {
        if ($moduleSlug) {
            $moduleKey = config("{$moduleSlug}.layouts.site");
            if ($moduleKey) {
                return $moduleKey;
            }
        }

        return $this->context->isCentralAdmin() ? 'layouts.app' : 'themes.default.layout';
    }

    /**
     * Resolve the auth layout for the current context.
     */
    public function authLayout(): string
    {
        return $this->context->isCentralAdmin() ? 'layouts.auth' : 'auth.layout';
    }

    /**
     * Resolve any layout by its config key with context awareness.
     * Supports platform/tenant suffixed variants automatically.
     *
     * Example:
     *   resolve('tickets', 'admin') → checks tickets.layouts.admin_tenant|admin
     *   resolve('forms', 'site')    → checks forms.layouts.site
     */
    public function resolve(string $moduleSlug, string $layoutKey, string $fallback = 'layouts.app'): string
    {
        if ($this->context->isTenantAdmin()) {
            $tenantVariant = config("{$moduleSlug}.layouts.{$layoutKey}_tenant");
            if ($tenantVariant) {
                return $tenantVariant;
            }
        }

        $value = config("{$moduleSlug}.layouts.{$layoutKey}", $fallback);

        return $value;
    }

    /**
     * Check that a resolved layout view actually exists in the application.
     * Returns [layout => string, exists => bool].
     */
    public function resolveWithCheck(string $moduleSlug, string $layoutKey, string $fallback = 'layouts.app'): array
    {
        $layout = $this->resolve($moduleSlug, $layoutKey, $fallback);

        return [
            'layout' => $layout,
            'exists' => view()->exists($layout),
        ];
    }

    /**
     * Validate that all layout keys for a module resolve to existing views.
     * Returns array of failures: ['key' => string, 'resolved' => string, 'exists' => bool]
     */
    public function validateModuleLayouts(string $moduleSlug): array
    {
        $keys     = ['admin', 'admin_tenant', 'site'];
        $failures = [];

        foreach ($keys as $key) {
            $configured = config("{$moduleSlug}.layouts.{$key}");
            if (! $configured) {
                continue;
            }

            if (! view()->exists($configured)) {
                $failures[] = [
                    'key'      => $key,
                    'resolved' => $configured,
                    'exists'   => false,
                ];
            }
        }

        return $failures;
    }
}
