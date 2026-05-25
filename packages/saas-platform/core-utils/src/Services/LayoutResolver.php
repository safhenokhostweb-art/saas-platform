<?php

namespace SaaSPlatform\CoreUtils\Services;

/**
 * PHASE 6.95 — TASK 7: Context-Aware Layout Resolver
 *
 * Resolves the correct Blade layout based on runtime context.
 * Views MUST NOT hardcode layout paths. Use this resolver instead.
 *
 * Behavior:
 * - Platform (saas-platform): uses platform admin/public layouts
 * - Tenant (customer-site): uses tenant admin/public layouts
 */
class LayoutResolver
{
    public function __construct(
        protected PlatformContext $platformContext
    ) {}

    /**
     * Resolve the admin layout for the current context.
     */
    public function adminLayout(): string
    {
        if ($this->platformContext->isCentralAdmin()) {
            return config('forms.layouts.admin', 'layouts.admin');
        }

        // Tenant context
        return config('forms.layouts.admin_tenant', 'admin.layout');
    }

    /**
     * Resolve the public/site layout for the current context.
     */
    public function publicLayout(): string
    {
        return config('forms.layouts.site', 'themes.default.layout');
    }

    /**
     * Resolve the auth layout for the current context.
     */
    public function authLayout(): string
    {
        if ($this->platformContext->isCentralAdmin()) {
            return 'layouts.auth';
        }

        return 'auth.layout';
    }

    /**
     * Resolve any layout by key with context awareness.
     */
    public function resolve(string $layoutKey, string $default = 'layouts.app'): string
    {
        $contextSuffix = $this->platformContext->isCentralAdmin() ? '_platform' : '_tenant';
        $contextKey = $layoutKey . $contextSuffix;

        $resolved = config("forms.layouts.{$contextKey}", config("forms.layouts.{$layoutKey}", $default));

        return $resolved;
    }
}
