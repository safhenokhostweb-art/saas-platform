<?php

namespace SaaSPlatform\CoreUtils\Services;

/**
 * PHASE 7.25 — TASK 3: Customer Package Runtime Validator
 *
 * Validates that the tenant consumer runtime is correctly configured:
 * every service, route, middleware, and namespace that customer-site
 * depends on must be resolvable from saas-platform's runtime.
 *
 * Invoked by: php artisan tenant:validate-runtime-authority
 *
 * Checks:
 *  C1.  Package view namespaces resolve (forms::, tickets::, core-ui::)
 *  C2.  No local admin module views shadow package namespaces
 *  C3.  TicketServiceInterface is bound in container
 *  C4.  ModuleNavigationService is bound in container
 *  C5.  PlatformContext is bound in container
 *  C6.  AuthenticateTenantApi middleware is registered in the kernel
 *  C7.  Canonical /api/tenant/tickets routes are registered
 *  C8.  FormsServiceProvider view namespace is active
 *  C9.  TicketsServiceProvider view namespace is active
 *  C10. Module manifests are accessible (module-manifest-forms, module-manifest-tickets)
 */
class CustomerPackageRuntimeValidator
{
    protected array $results = [];

    public function validate(): array
    {
        $this->results = [];

        $this->checkC1_packageNamespacesResolve();
        $this->checkC2_noLocalShadowViews();
        $this->checkC3_ticketServiceBound();
        $this->checkC4_navigationServiceBound();
        $this->checkC5_platformContextBound();
        $this->checkC6_tenantApiMiddlewareRegistered();
        $this->checkC7_tenantTicketRoutesRegistered();
        $this->checkC8_formsNamespaceActive();
        $this->checkC9_ticketsNamespaceActive();
        $this->checkC10_moduleManifestsAccessible();

        $failed = count(array_filter($this->results, fn($r) => !$r['passed']));

        return [
            'passed' => $failed === 0,
            'total'  => count($this->results),
            'failed' => $failed,
            'checks' => $this->results,
        ];
    }

    // ─── C1: Package namespaces resolve ──────────────────────────────────────

    protected function checkC1_packageNamespacesResolve(): void
    {
        $probes = [
            'forms::site.forms.index',
            'tickets::site.tickets.index',
            'core-ui::components.module-card',
        ];

        $missing = array_values(array_filter($probes, fn($v) => !view()->exists($v)));

        $this->add('C1', 'Package view namespaces resolve (forms::, tickets::, core-ui::)', empty($missing),
            $missing ? 'Missing namespace probes: ' . implode(', ', $missing) : null
        );
    }

    // ─── C2: No local shadow views ───────────────────────────────────────────

    protected function checkC2_noLocalShadowViews(): void
    {
        $shadowPaths = [
            resource_path('views/admin/forms'),
            resource_path('views/admin/tickets'),
            resource_path('views/admin/support-tickets'),
        ];

        $found = [];
        foreach ($shadowPaths as $path) {
            if (is_dir($path)) {
                $found[] = $path;
            }
        }

        $this->add('C2', 'No local admin module views shadow package namespaces', empty($found),
            $found ? 'Shadow paths active: ' . implode(', ', $found) : null
        );
    }

    // ─── C3: TicketServiceInterface bound ────────────────────────────────────

    protected function checkC3_ticketServiceBound(): void
    {
        $interface = \SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface::class;
        $bound = app()->bound($interface);

        $this->add('C3', 'TicketServiceInterface is bound in container', $bound,
            $bound ? null : 'TicketServiceInterface not bound — TicketsServiceProvider may not be registered.'
        );
    }

    // ─── C4: ModuleNavigationService bound ───────────────────────────────────

    protected function checkC4_navigationServiceBound(): void
    {
        $bound = app()->bound(ModuleNavigationService::class);

        $this->add('C4', 'ModuleNavigationService is bound in container', $bound,
            $bound ? null : 'ModuleNavigationService not bound — CoreUtilsServiceProvider may not be registered.'
        );
    }

    // ─── C5: PlatformContext bound ────────────────────────────────────────────

    protected function checkC5_platformContextBound(): void
    {
        $bound = app()->bound(PlatformContext::class);

        $this->add('C5', 'PlatformContext is bound in container', $bound,
            $bound ? null : 'PlatformContext not bound — CoreUtilsServiceProvider may not be registered.'
        );
    }

    // ─── C6: AuthenticateTenantApi middleware registered ─────────────────────

    protected function checkC6_tenantApiMiddlewareRegistered(): void
    {
        $router = app('router');
        $middleware = $router->getMiddleware();
        $registered = array_key_exists('tenant.api', $middleware);

        $this->add('C6', 'tenant.api middleware is registered in kernel', $registered,
            $registered ? null : '"tenant.api" middleware alias not registered. Add to Kernel::$routeMiddleware.'
        );
    }

    // ─── C7: Canonical tenant ticket routes registered ───────────────────────

    protected function checkC7_tenantTicketRoutesRegistered(): void
    {
        $requiredNames = [
            'api.tenant-tickets.index',
            'api.tenant-tickets.store',
            'api.tenant-tickets.show',
            'api.tenant-tickets.reply',
        ];

        $routes = app('router')->getRoutes();
        $missing = [];
        foreach ($requiredNames as $name) {
            if ($routes->getByName($name) === null) {
                $missing[] = $name;
            }
        }

        $this->add('C7', 'Canonical /api/tenant/tickets routes are registered', empty($missing),
            $missing ? 'Missing route names: ' . implode(', ', $missing) : null
        );
    }

    // ─── C8: Forms view namespace active ─────────────────────────────────────

    protected function checkC8_formsNamespaceActive(): void
    {
        $views = ['forms::admin.forms.index', 'forms::site.forms.index'];
        $missing = array_values(array_filter($views, fn($v) => !view()->exists($v)));

        $this->add('C8', 'Forms view namespace active (forms::admin.*, forms::site.*)', empty($missing),
            $missing ? 'Missing forms:: views: ' . implode(', ', $missing) : null
        );
    }

    // ─── C9: Tickets view namespace active ───────────────────────────────────

    protected function checkC9_ticketsNamespaceActive(): void
    {
        $views = ['tickets::admin.tickets.index', 'tickets::site.tickets.index'];
        $missing = array_values(array_filter($views, fn($v) => !view()->exists($v)));

        $this->add('C9', 'Tickets view namespace active (tickets::admin.*, tickets::site.*)', empty($missing),
            $missing ? 'Missing tickets:: views: ' . implode(', ', $missing) : null
        );
    }

    // ─── C10: Module manifests accessible ────────────────────────────────────

    protected function checkC10_moduleManifestsAccessible(): void
    {
        $manifests = ['module-manifest-forms', 'module-manifest-tickets'];
        $missing = array_values(array_filter($manifests, fn($key) => config($key) === null));

        $this->add('C10', 'Module manifests accessible (module-manifest-forms, module-manifest-tickets)', empty($missing),
            $missing ? 'Manifests not loaded: ' . implode(', ', $missing) : null
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
