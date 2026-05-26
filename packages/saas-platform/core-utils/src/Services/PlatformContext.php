<?php

namespace SaaSPlatform\CoreUtils\Services;

use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use Illuminate\Support\Facades\App;

/**
 * PHASE 6.95 — TASK 1: Canonical Runtime Context Service
 *
 * Detects the current runtime environment and provides context-aware
 * methods for UI segregation between platform (saas-platform) and
 * tenant (customer-site) contexts.
 *
 * Detection methods (in priority order):
 * 1. config('app.role') — explicit role configuration
 * 2. TenantContextContract — runtime tenant resolution
 * 3. Middleware-injected context flags
 * 4. App environment fallback
 *
 * This service is the SINGLE source of truth for all context decisions.
 * No view, controller, or directive should bypass this service.
 */
class PlatformContext
{
    public const CONTEXT_CENTRAL_ADMIN = 'central_admin';
    public const CONTEXT_TENANT_ADMIN = 'tenant_admin';
    public const CONTEXT_TENANT_PUBLIC = 'tenant_public';
    public const CONTEXT_API = 'api';
    public const CONTEXT_CONSOLE = 'console';

    /**
     * Cache the context decision for the request lifecycle.
     */
    protected ?string $cachedContext = null;

    /**
     * Middleware-injected context flags.
     */
    protected array $injectedContext = [];

    public function __construct(
        protected ?TenantContextContract $tenantContext = null
    ) {}

    /**
     * Inject runtime context flags from middleware.
     */
    public function injectContext(array $context): void
    {
        $this->injectedContext = array_merge($this->injectedContext, $context);
        $this->cachedContext = null; // Reset cache
    }

    /**
     * Determine the current runtime context.
     *
     * Priority:
     * 1. Middleware-injected context
     * 2. config('app.role')
     * 3. TenantContextContract
     * 4. Running in console
     * 5. Default fallback
     */
    public function runtime(): string
    {
        if ($this->cachedContext !== null) {
            return $this->cachedContext;
        }

        // Priority 1: Middleware-injected context
        if (!empty($this->injectedContext['runtime'])) {
            $this->cachedContext = $this->injectedContext['runtime'];
            return $this->cachedContext;
        }

        // Priority 2: package-scoped role, with fallback to host app config
        $role = config('saas-platform.role', config('app.role'));
        if ($role && in_array($role, [
            self::CONTEXT_CENTRAL_ADMIN,
            self::CONTEXT_TENANT_ADMIN,
            self::CONTEXT_TENANT_PUBLIC,
            self::CONTEXT_API,
        ], true)) {
            $this->cachedContext = $role;
            return $this->cachedContext;
        }

        // Priority 3: TenantContextContract
        if ($this->tenantContext && $this->tenantContext->hasTenant()) {
            if ($this->tenantContext->isReseller()) {
                $this->cachedContext = self::CONTEXT_CENTRAL_ADMIN;
                return $this->cachedContext;
            }
            if ($this->tenantContext->isSite()) {
                $this->cachedContext = self::CONTEXT_TENANT_ADMIN;
                return $this->cachedContext;
            }
            // Default tenant context
            $this->cachedContext = self::CONTEXT_TENANT_ADMIN;
            return $this->cachedContext;
        }

        // Priority 4: Running in console
        if (App::runningInConsole()) {
            $this->cachedContext = self::CONTEXT_CONSOLE;
            return $this->cachedContext;
        }

        // Priority 5: Check if platform has multi-site capabilities
        if ($this->platformHasMultiSite()) {
            $this->cachedContext = self::CONTEXT_CENTRAL_ADMIN;
            return $this->cachedContext;
        }

        // Default: tenant public
        $this->cachedContext = self::CONTEXT_TENANT_PUBLIC;
        return $this->cachedContext;
    }

    /**
     * Is the current context central admin (saas-platform)?
     */
    public function isCentralAdmin(): bool
    {
        return $this->runtime() === self::CONTEXT_CENTRAL_ADMIN;
    }

    /**
     * Is the current context tenant admin (customer-site admin panel)?
     */
    public function isTenantAdmin(): bool
    {
        return $this->runtime() === self::CONTEXT_TENANT_ADMIN;
    }

    /**
     * Is the current context a tenant site (any tenant context)?
     */
    public function isTenant(): bool
    {
        return in_array($this->runtime(), [
            self::CONTEXT_TENANT_ADMIN,
            self::CONTEXT_TENANT_PUBLIC,
        ], true);
    }

    /**
     * Is the current context a public site (tenant or platform public)?
     */
    public function isPublicSite(): bool
    {
        return $this->runtime() === self::CONTEXT_TENANT_PUBLIC;
    }

    /**
     * Is the current context an API request?
     */
    public function isApi(): bool
    {
        return $this->runtime() === self::CONTEXT_API;
    }

    /**
     * Is the current context running in console?
     */
    public function isConsole(): bool
    {
        return $this->runtime() === self::CONTEXT_CONSOLE;
    }

    /**
     * Get the current tenant ID if in tenant context.
     */
    public function currentTenantId(): ?int
    {
        if (!$this->isTenant()) {
            return null;
        }

        // Check injected context first
        if (!empty($this->injectedContext['tenant_id'])) {
            return (int) $this->injectedContext['tenant_id'];
        }

        // Check TenantContextContract
        if ($this->tenantContext && method_exists($this->tenantContext, 'id')) {
            return $this->tenantContext->id();
        }

        return null;
    }

    /**
     * Get the current site ID if in tenant context.
     */
    public function currentSiteId(): ?int
    {
        return $this->currentTenantId();
    }

    /**
     * Does the platform support multi-site management?
     */
    public function platformHasMultiSite(): bool
    {
        if (class_exists(\App\Models\Site::class)) {
            try {
                $siteCount = \App\Models\Site::count();
                return $siteCount > 0;
            } catch (\Throwable $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Clear the cached context decision.
     */
    public function clearCache(): void
    {
        $this->cachedContext = null;
    }
}
