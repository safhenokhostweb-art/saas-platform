<?php

namespace SaaSPlatform\Modules\Forms\Services;

use App\Models\Site;
use App\Services\TenantContext;
use Illuminate\Support\Facades\App;

/**
 * PHASE 6.95 — TASK 1: Platform Context Service
 *
 * Runtime context-aware UI segregation for the Forms module.
 * Determines whether the current request is:
 *   - saas-platform (central admin): full platform UI with Sites, licensing, assignments
 *   - customer-site (tenant): restricted tenant UI with forms, submissions, preview only
 *
 * This service is the SINGLE source of truth for all context decisions.
 * No view, controller, or directive should bypass this service.
 */
class PlatformContextService
{
    /**
     * Cache the context decision for the request lifecycle.
     */
    protected ?string $cachedContext = null;

    public const CONTEXT_PLATFORM = 'platform';
    public const CONTEXT_TENANT = 'tenant';

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Determine the current runtime context.
     *
     * Rules:
     * - If TenantContext resolves a Reseller → CONTEXT_PLATFORM (saas-platform central admin)
     * - If TenantContext resolves a Site → CONTEXT_TENANT (customer-site tenant)
     * - If no tenant → check if Site model exists in the app (saas-platform has Site, customer-site may not)
     * - Fallback: check if the app has the 'Site' model with multi-site capabilities
     *
     * NOTE: customer-site's TenantContext does NOT have isReseller()/isSite() methods.
     * We use method_exists() for defensive cross-project compatibility.
     */
    public function currentContext(): string
    {
        if ($this->cachedContext !== null) {
            return $this->cachedContext;
        }

        // Check TenantContext first
        if ($this->tenantContext->hasTenant()) {
            // saas-platform TenantContext has isReseller() (implements TenantContextContract)
            if (method_exists($this->tenantContext, 'isReseller') && $this->tenantContext->isReseller()) {
                $this->cachedContext = self::CONTEXT_PLATFORM;
                return $this->cachedContext;
            }
            // saas-platform TenantContext has isSite()
            if (method_exists($this->tenantContext, 'isSite') && $this->tenantContext->isSite()) {
                $this->cachedContext = self::CONTEXT_TENANT;
                return $this->cachedContext;
            }
            // customer-site TenantContext: hasTenant() returns true → always tenant
            $this->cachedContext = self::CONTEXT_TENANT;
            return $this->cachedContext;
        }

        // Check if we're on saas-platform by looking for multi-site capabilities
        if ($this->platformHasMultiSite()) {
            $this->cachedContext = self::CONTEXT_PLATFORM;
            return $this->cachedContext;
        }

        // Default to tenant context (customer-site)
        $this->cachedContext = self::CONTEXT_TENANT;
        return $this->cachedContext;
    }


    /**
     * Is the current context the central platform (saas-platform)?
     */
    public function isCentralAdmin(): bool
    {
        return $this->currentContext() === self::CONTEXT_PLATFORM;
    }

    /**
     * Is the current context a tenant site (customer-site)?
     */
    public function isTenantAdmin(): bool
    {
        return $this->currentContext() === self::CONTEXT_TENANT;
    }

    /**
     * Get the current tenant ID (Site ID) if in tenant context.
     * NOTE: customer-site TenantContext does not have id() method.
     * We use method_exists() for defensive cross-project compatibility.
     */
    public function tenantId(): ?int
    {
        if (!$this->isTenantAdmin()) {
            return null;
        }
        if (method_exists($this->tenantContext, 'id')) {
            return $this->tenantContext->id();
        }
        return null;
    }

    /**
     * Get the current tenant Site model if in tenant context.
     * NOTE: customer-site TenantContext does not have site() method.
     * We use method_exists() for defensive cross-project compatibility.
     */
    public function tenantSite(): ?Site
    {
        if (!$this->isTenantAdmin()) {
            return null;
        }
        if (method_exists($this->tenantContext, 'site')) {
            return $this->tenantContext->site();
        }
        return null;
    }


    /**
     * Does the platform support multi-site management?
     * saas-platform has the Site model with multi-site capabilities.
     * customer-site is a single tenant and does not manage other sites.
     */
    public function platformHasMultiSite(): bool
    {
        // Check if the Site model exists and has multi-site routes
        if (class_exists(\App\Models\Site::class)) {
            try {
                // If we can count sites, we're on the platform
                $siteCount = \App\Models\Site::count();
                return $siteCount > 0;
            } catch (\Throwable $e) {
                // Table might not exist
                return false;
            }
        }
        return false;
    }

    /**
     * Check if a specific capability is available in the current context.
     *
     * Platform capabilities (saas-platform only):
     *   - manage_sites: assign forms to sites
     *   - manage_licensing: license management
     *   - feature_toggles: enable/disable features
     *   - sla_configuration: SLA settings
     *   - lifecycle_management: module lifecycle
     *
     * Tenant capabilities (customer-site):
     *   - create_forms: create and manage forms
     *   - view_submissions: view form submissions
     *   - export_reports: export submission data
     *   - form_preview: preview forms
     *   - basic_analytics: basic analytics
     *
     * Shared capabilities (both):
     *   - form_builder: drag & drop form builder
     *   - field_editor: edit form fields
     *   - validation_rules: set validation rules
     */
    public function hasCapability(string $capability): bool
    {
        $platformCapabilities = [
            'manage_sites',
            'manage_licensing',
            'feature_toggles',
            'sla_configuration',
            'lifecycle_management',
        ];

        $tenantCapabilities = [
            'create_forms',
            'view_submissions',
            'export_reports',
            'form_preview',
            'basic_analytics',
        ];

        $sharedCapabilities = [
            'form_builder',
            'field_editor',
            'validation_rules',
        ];

        if (in_array($capability, $sharedCapabilities, true)) {
            return true;
        }

        if (in_array($capability, $platformCapabilities, true)) {
            return $this->isCentralAdmin();
        }

        if (in_array($capability, $tenantCapabilities, true)) {
            return $this->isTenantAdmin();
        }

        return false;
    }

    /**
     * Get all capabilities available in the current context.
     *
     * @return array<int, string>
     */
    public function getCapabilities(): array
    {
        $capabilities = [
            'form_builder',
            'field_editor',
            'validation_rules',
        ];

        if ($this->isCentralAdmin()) {
            $capabilities = array_merge($capabilities, [
                'manage_sites',
                'manage_licensing',
                'feature_toggles',
                'sla_configuration',
                'lifecycle_management',
            ]);
        }

        if ($this->isTenantAdmin()) {
            $capabilities = array_merge($capabilities, [
                'create_forms',
                'view_submissions',
                'export_reports',
                'form_preview',
                'basic_analytics',
            ]);
        }

        return $capabilities;
    }

    /**
     * Clear the cached context decision (for testing or request reuse).
     */
    public function clearCache(): void
    {
        $this->cachedContext = null;
    }
}
