<?php

namespace SaaSPlatform\CoreUtils\Services;

/**
 * PHASE 7.25 — TASK 6: Canonical UI Parity Validator
 *
 * Validates that the UI rendered for the tenant consumer is consistent
 * with the canonical package views — no duplicate primitives,
 * no divergent layouts, no platform features leaking into tenant views.
 *
 * Invoked by: php artisan tenant:validate-runtime-authority
 *
 * Checks:
 *  U1.  Forms admin views resolve from package namespace only
 *  U2.  Tickets admin views resolve from package namespace only
 *  U3.  Shared core-ui components available (module-card, data-table, etc.)
 *  U4.  No stale published vendor views override package namespaces
 *  U5.  Admin layout resolves consistently (not shadowed by legacy layout)
 *  U6.  Tenant context gate functional (PlatformContext::isTenant())
 *  U7.  ModuleNavigationService is the canonical sidebar source
 *  U8.  No forbidden platform-only views accessible under site/ namespaces
 *  U9.  OverrideAuthorityValidator reports no violations
 *  U10. Tenant API route namespace canonical (/api/tenant/tickets not /api/support-tickets)
 */
class CanonicalUiParityValidator
{
    protected array $results = [];

    public function __construct(
        protected OverrideAuthorityValidator $overrideValidator,
        protected PlatformContext            $platformContext,
        protected ModuleNavigationService    $navigationService,
    ) {}

    public function validate(): array
    {
        $this->results = [];

        $this->checkU1_formsAdminViewsParity();
        $this->checkU2_ticketsAdminViewsParity();
        $this->checkU3_sharedComponentsAvailable();
        $this->checkU4_noStalePublishedViews();
        $this->checkU5_adminLayoutConsistent();
        $this->checkU6_tenantContextGate();
        $this->checkU7_navigationServiceCanonical();
        $this->checkU8_noForbiddenSiteViews();
        $this->checkU9_overrideAuthorityClean();
        $this->checkU10_tenantApiRouteCanonical();

        $failed = count(array_filter($this->results, fn($r) => !$r['passed']));

        return [
            'passed' => $failed === 0,
            'total'  => count($this->results),
            'failed' => $failed,
            'checks' => $this->results,
        ];
    }

    // ─── U1: Forms admin views from package ───────────────────────────────────

    protected function checkU1_formsAdminViewsParity(): void
    {
        $canonical = [
            'forms::admin.forms.index',
            'forms::admin.forms.create',
            'forms::admin.forms.edit',
        ];
        $missing = array_values(array_filter($canonical, fn($v) => !view()->exists($v)));

        $localOverride = is_dir(resource_path('views/admin/forms')) &&
            !is_dir(resource_path('views/admin/forms/_legacy'));

        $passed = empty($missing) && !$localOverride;

        $this->add('U1', 'Forms admin views resolve from package namespace (no local shadow)', $passed,
            !$passed ? ($localOverride ? 'Local forms admin views shadow package namespace.' : 'Missing forms:: views: ' . implode(', ', $missing)) : null
        );
    }

    // ─── U2: Tickets admin views from package ─────────────────────────────────

    protected function checkU2_ticketsAdminViewsParity(): void
    {
        $canonical = [
            'tickets::admin.tickets.index',
            'tickets::admin.tickets.show',
        ];
        $missing = array_values(array_filter($canonical, fn($v) => !view()->exists($v)));

        $localActive = is_dir(resource_path('views/admin/support-tickets')) &&
            !is_dir(resource_path('views/admin/support-tickets/_legacy'));

        $passed = empty($missing) && !$localActive;

        $this->add('U2', 'Tickets admin views resolve from package namespace (no active local shadow)', $passed,
            !$passed ? ($localActive ? 'Legacy support-ticket views still active (not in _legacy/).' : 'Missing tickets:: views: ' . implode(', ', $missing)) : null
        );
    }

    // ─── U3: Shared components available ─────────────────────────────────────

    protected function checkU3_sharedComponentsAvailable(): void
    {
        $components = [
            'core-ui::components.module-card',
            'core-ui::components.stats-card',
            'core-ui::components.data-table',
            'core-ui::components.filter-bar',
            'core-ui::components.empty-state',
            'core-ui::components.table-pagination',
            'core-ui::components.module-actions',
        ];

        $missing = array_values(array_filter($components, fn($v) => !view()->exists($v)));

        $this->add('U3', 'Shared core-ui components available', empty($missing),
            $missing ? 'Missing: ' . implode(', ', $missing) : null
        );
    }

    // ─── U4: No stale published views ─────────────────────────────────────────

    protected function checkU4_noStalePublishedViews(): void
    {
        $stale = $this->overrideValidator->detectStalePublishedViews();

        $this->add('U4', 'No stale published vendor views override package namespaces', empty($stale),
            $stale ? count($stale) . ' stale published view(s) detected.' : null,
            $stale
        );
    }

    // ─── U5: Admin layout consistent ─────────────────────────────────────────

    protected function checkU5_adminLayoutConsistent(): void
    {
        $layouts = ['admin.layout', 'layouts.admin'];
        $found = array_values(array_filter($layouts, fn($l) => view()->exists($l)));

        $this->add('U5', 'Admin layout resolves consistently', !empty($found),
            empty($found) ? 'Neither admin.layout nor layouts.admin resolves.' : null,
            ['resolved' => $found]
        );
    }

    // ─── U6: Tenant context gate functional ──────────────────────────────────

    protected function checkU6_tenantContextGate(): void
    {
        try {
            $context = app(PlatformContext::class);
            $runtime = $context->runtime();
            $valid = in_array($runtime, [
                PlatformContext::CONTEXT_CENTRAL_ADMIN,
                PlatformContext::CONTEXT_TENANT_ADMIN,
                PlatformContext::CONTEXT_TENANT_PUBLIC,
                PlatformContext::CONTEXT_API,
                PlatformContext::CONTEXT_CONSOLE,
            ], true);

            $this->add('U6', 'Tenant context gate functional (PlatformContext resolves)', $valid,
                $valid ? null : "PlatformContext returned unknown context: {$runtime}"
            );
        } catch (\Throwable $e) {
            $this->add('U6', 'Tenant context gate functional', false, $e->getMessage());
        }
    }

    // ─── U7: ModuleNavigationService canonical sidebar ───────────────────────

    protected function checkU7_navigationServiceCanonical(): void
    {
        $bound = app()->bound(ModuleNavigationService::class);
        $manifestForms = config('module-manifest-forms') !== null;
        $manifestTickets = config('module-manifest-tickets') !== null;

        $passed = $bound && $manifestForms && $manifestTickets;

        $detail = [];
        if (!$bound) $detail[] = 'ModuleNavigationService not bound';
        if (!$manifestForms) $detail[] = 'module-manifest-forms config not loaded';
        if (!$manifestTickets) $detail[] = 'module-manifest-tickets config not loaded';

        $this->add('U7', 'ModuleNavigationService is canonical sidebar source (manifests loaded)', $passed,
            !$passed ? implode('; ', $detail) : null
        );
    }

    // ─── U8: No forbidden platform views in site/ namespaces ─────────────────

    protected function checkU8_noForbiddenSiteViews(): void
    {
        $forbidden = [
            'tickets::site.tickets.assign',
            'tickets::site.tickets.bulk-close',
            'forms::site.forms.delete',
        ];

        $exposed = array_values(array_filter($forbidden, fn($v) => view()->exists($v)));

        $this->add('U8', 'No platform-only views accessible in site/ namespaces', empty($exposed),
            $exposed ? 'Platform-only views exposed: ' . implode(', ', $exposed) : null
        );
    }

    // ─── U9: Override authority clean ────────────────────────────────────────

    protected function checkU9_overrideAuthorityClean(): void
    {
        $result = $this->overrideValidator->validate();
        $violations = $result['violations'] ?? [];

        $this->add('U9', 'No illegal view authority violations (OverrideAuthorityValidator)', empty($violations),
            $violations ? count($violations) . ' override violation(s) detected.' : null,
            $violations
        );
    }

    // ─── U10: Tenant API route canonical ─────────────────────────────────────

    protected function checkU10_tenantApiRouteCanonical(): void
    {
        $routes = app('router')->getRoutes();
        $canonicalExists = $routes->getByName('api.tenant-tickets.index') !== null;
        $legacyExists = $routes->getByName('api.support-tickets.index') !== null;

        $passed = $canonicalExists;

        $notes = [];
        if (!$canonicalExists) $notes[] = 'api.tenant-tickets.index route not registered';
        if ($legacyExists) $notes[] = 'legacy api.support-tickets.index still active (pending removal)';

        $this->add('U10', 'Tenant API route canonical (/api/tenant/tickets registered)', $passed,
            !$passed ? implode('; ', $notes) : null,
            $legacyExists ? ['warning' => 'Legacy /api/support-tickets still active — remove after SupportApiClient migration'] : []
        );
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    protected function add(string $id, string $label, bool $passed, ?string $error = null, array $details = []): void
    {
        $this->results[] = [
            'check'   => $id,
            'label'   => $label,
            'passed'  => $passed,
            'error'   => $error,
            'details' => $details,
        ];
    }
}
