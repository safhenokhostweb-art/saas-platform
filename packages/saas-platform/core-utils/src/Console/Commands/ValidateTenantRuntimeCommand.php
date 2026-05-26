<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\CustomerPackageRuntimeValidator;
use SaaSPlatform\CoreUtils\Services\CanonicalUiParityValidator;
use SaaSPlatform\CoreUtils\Services\PlatformContext;
use SaaSPlatform\CoreUtils\Services\ModuleNavigationService;

/**
 * PHASE 7.25 — TASK 9: Tenant Runtime Authority Validation Command
 *
 * Runs 15 checks validating that saas-platform is correctly configured
 * to serve as a canonical runtime for tenant consumers (customer-site).
 *
 * Usage:
 *   php artisan tenant:validate-runtime-authority
 *   php artisan tenant:validate-runtime-authority --json
 *   php artisan tenant:validate-runtime-authority --fail-on-warning
 *
 * Checks:
 *  T01. No legacy module controllers active in routing
 *  T02. No local module views shadow package namespaces
 *  T03. Package namespaces resolve (forms::, tickets::, core-ui::)
 *  T04. Canonical routes active (admin + site + tenant-api)
 *  T05. Sidebar source bound (ModuleNavigationService + module manifests)
 *  T06. Tenant isolation enforced (TicketServiceInterface + assertTenantIsolation)
 *  T07. No platform-only actions visible in site/ namespaces
 *  T08. Canonical layouts resolve (admin.layout, layouts.admin)
 *  T09. No duplicate UI primitives (core-ui canonical, no local duplicates)
 *  T10. Forms runtime canonical (routes + views + service + namespace)
 *  T11. Tickets runtime canonical (routes + views + service + namespace)
 *  T12. No forbidden override paths (OverrideAuthorityValidator clean)
 *  T13. API runtime canonical (/api/tenant/tickets registered, middleware set)
 *  T14. Policy enforcement active (TicketPolicy bound via Gate)
 *  T15. Gate filtering active (PlatformContext resolves, tenant context gates work)
 */
class ValidateTenantRuntimeCommand extends Command
{
    protected $signature = 'tenant:validate-runtime-authority
                            {--json : Output results as JSON}
                            {--fail-on-warning : Exit with code 1 if any check fails}';

    protected $description = 'Validate that saas-platform is correctly configured as a canonical tenant runtime (15 checks)';

    protected array $checks = [];

    public function handle(
        CustomerPackageRuntimeValidator $packageValidator,
        CanonicalUiParityValidator $uiValidator
    ): int {
        $this->checks = [];

        // Run sub-validators to inform checks
        $packageResult = $packageValidator->validate();
        $uiResult      = $uiValidator->validate();

        $pkgMap = $this->indexByCheck($packageResult['checks']);
        $uiMap  = $this->indexByCheck($uiResult['checks']);

        // ── 15 checks ──────────────────────────────────────────────────────────

        // T01: No legacy module controllers active in routing
        $this->runT01_noLegacyControllers();

        // T02: No local module views shadow package namespaces
        $this->passThrough('T02', 'No local module views shadow package namespaces', $pkgMap['C2'] ?? null);

        // T03: Package namespaces resolve
        $this->passThrough('T03', 'Package namespaces resolve (forms::, tickets::, core-ui::)', $pkgMap['C1'] ?? null);

        // T04: Canonical routes active
        $this->runT04_canonicalRoutesActive();

        // T05: Sidebar bound (ModuleNavigationService + manifests)
        $this->passThrough('T05', 'Sidebar source bound (ModuleNavigationService + module manifests)', $uiMap['U7'] ?? null);

        // T06: Tenant isolation enforced
        $this->runT06_tenantIsolationEnforced($pkgMap['C3'] ?? null);

        // T07: No platform-only actions in site/ namespaces
        $this->passThrough('T07', 'No platform-only actions visible in site/ namespaces', $uiMap['U8'] ?? null);

        // T08: Canonical layouts resolve
        $this->passThrough('T08', 'Canonical layouts resolve (admin.layout, layouts.admin)', $uiMap['U5'] ?? null);

        // T09: No duplicate UI primitives
        $this->passThrough('T09', 'No duplicate UI primitives (core-ui canonical)', $uiMap['U3'] ?? null);

        // T10: Forms runtime canonical
        $this->runT10_formsRuntimeCanonical($pkgMap['C8'] ?? null, $uiMap['U1'] ?? null);

        // T11: Tickets runtime canonical
        $this->runT11_ticketsRuntimeCanonical($pkgMap['C9'] ?? null, $uiMap['U2'] ?? null);

        // T12: No forbidden override paths
        $this->passThrough('T12', 'No forbidden override paths (OverrideAuthorityValidator)', $uiMap['U9'] ?? null);

        // T13: API runtime canonical
        $this->runT13_apiRuntimeCanonical($pkgMap['C6'] ?? null, $pkgMap['C7'] ?? null);

        // T14: Policy enforcement active (TicketPolicy bound)
        $this->runT14_policyEnforcementActive();

        // T15: Gate filtering active (PlatformContext)
        $this->passThrough('T15', 'Gate filtering active (PlatformContext resolves)', $uiMap['U6'] ?? null);

        // ── Output ─────────────────────────────────────────────────────────────

        $failed = count(array_filter($this->checks, fn($c) => !$c['passed']));
        $total  = count($this->checks);
        $score  = $total > 0 ? round((($total - $failed) / $total) * 100) : 0;

        if ($this->option('json')) {
            $this->line(json_encode([
                'passed'  => $failed === 0,
                'score'   => $score,
                'total'   => $total,
                'failed'  => $failed,
                'checks'  => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ($this->option('fail-on-warning') && $failed > 0) ? self::FAILURE : self::SUCCESS;
        }

        $this->line('');
        $this->line('  <fg=blue>PHASE 7.25 — Tenant Runtime Authority Validation</>');
        $this->line('  ' . str_repeat('─', 55));
        $this->line('');

        foreach ($this->checks as $check) {
            $icon   = $check['passed'] ? '<fg=green>✅</>' : '<fg=red>❌</>';
            $label  = str_pad($check['check'], 4);
            $status = $check['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $this->line("  {$icon}  {$label}  {$status}  {$check['label']}");

            if (!$check['passed'] && !empty($check['error'])) {
                $this->line("               <fg=red>→ {$check['error']}</>");
            }

            if (!empty($check['details'])) {
                foreach (array_slice($check['details'], 0, 2) as $detail) {
                    if (is_string($detail)) {
                        $this->line("               <fg=yellow>  • {$detail}</>");
                    }
                }
            }
        }

        $this->line('');
        $color = $failed === 0 ? 'green' : 'red';
        $this->line("  <fg={$color}>Score: {$score}% ({$total} checks, {$failed} failed)</>");
        $this->line('');

        if ($failed === 0) {
            $this->line('  <fg=green>✅  All tenant runtime checks passed. Platform is production-ready as canonical tenant runtime.</>');
        } else {
            $this->line("  <fg=red>❌  {$failed} check(s) failed. Resolve violations before deploying to production.</>");
        }

        $this->line('');

        return ($this->option('fail-on-warning') && $failed > 0) ? self::FAILURE : self::SUCCESS;
    }

    // ─── Individual Check Implementations ────────────────────────────────────

    protected function runT01_noLegacyControllers(): void
    {
        $routes = app('router')->getRoutes();
        $legacy = [];

        foreach ($routes->getRoutes() as $route) {
            $action = $route->getActionName();
            if (str_contains($action, 'Admin\\SupportTicketController') ||
                str_contains($action, 'Admin\\AdminSupportTicketController') ||
                str_contains($action, 'AdminFormsController')
            ) {
                $legacy[] = $action;
            }
        }

        $this->addCheck('T01', 'No legacy module controllers active in routing', empty($legacy),
            $legacy ? 'Legacy controllers in routes: ' . implode(', ', $legacy) : null
        );
    }

    protected function runT04_canonicalRoutesActive(): void
    {
        $required = [
            'tickets.admin.index',
            'tickets.site.index',
            'api.tenant-tickets.index',
            'api.tenant-tickets.store',
        ];

        $routes  = app('router')->getRoutes();
        $missing = array_values(array_filter($required, fn($n) => $routes->getByName($n) === null));

        $this->addCheck('T04', 'Canonical routes active (admin + site + tenant-api)', empty($missing),
            $missing ? 'Missing routes: ' . implode(', ', $missing) : null
        );
    }

    protected function runT06_tenantIsolationEnforced(?array $serviceCheck): void
    {
        $serviceBound = $serviceCheck['passed'] ?? false;

        // Also verify assertTenantIsolation exists on TicketService via reflection
        $hasMethod = false;
        try {
            $interface = \SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface::class;
            if (app()->bound($interface)) {
                $service   = app($interface);
                $hasMethod = method_exists($service, 'assertTenantIsolation');
            }
        } catch (\Throwable) {
            // service not resolvable
        }

        $passed = $serviceBound && $hasMethod;

        $details = [];
        if (!$serviceBound) $details[] = 'TicketServiceInterface not bound';
        if (!$hasMethod) $details[] = 'assertTenantIsolation() not found on TicketService';

        $this->addCheck('T06', 'Tenant isolation enforced (TicketService::assertTenantIsolation exists)', $passed,
            !$passed ? implode('; ', $details) : null
        );
    }

    protected function runT10_formsRuntimeCanonical(?array $formsNamespace, ?array $formsUi): void
    {
        $nsOk = $formsNamespace['passed'] ?? false;
        $uiOk = $formsUi['passed'] ?? false;
        $routes = app('router')->getRoutes();
        $routeOk = $routes->getByName('admin.forms.index') !== null || $routes->getByName('forms.admin.index') !== null;

        $passed = $nsOk && $uiOk && $routeOk;

        $details = [];
        if (!$nsOk) $details[] = 'forms:: namespace not resolved';
        if (!$uiOk) $details[] = 'local forms views shadow package';
        if (!$routeOk) $details[] = 'forms admin route not registered';

        $this->addCheck('T10', 'Forms runtime canonical (routes + views + namespace)', $passed,
            !$passed ? implode('; ', $details) : null
        );
    }

    protected function runT11_ticketsRuntimeCanonical(?array $ticketsNamespace, ?array $ticketsUi): void
    {
        $nsOk = $ticketsNamespace['passed'] ?? false;
        $uiOk = $ticketsUi['passed'] ?? false;
        $routes = app('router')->getRoutes();
        $routeOk = $routes->getByName('tickets.admin.index') !== null;

        $passed = $nsOk && $uiOk && $routeOk;

        $details = [];
        if (!$nsOk) $details[] = 'tickets:: namespace not resolved';
        if (!$uiOk) $details[] = 'local ticket views shadow package';
        if (!$routeOk) $details[] = 'tickets.admin.index route not registered';

        $this->addCheck('T11', 'Tickets runtime canonical (routes + views + namespace)', $passed,
            !$passed ? implode('; ', $details) : null
        );
    }

    protected function runT13_apiRuntimeCanonical(?array $middlewareCheck, ?array $routeCheck): void
    {
        $mwOk     = $middlewareCheck['passed'] ?? false;
        $routeOk  = $routeCheck['passed'] ?? false;

        $passed = $mwOk && $routeOk;

        $details = [];
        if (!$mwOk) $details[] = 'tenant.api middleware not registered';
        if (!$routeOk) $details[] = 'api.tenant-tickets routes not registered';

        $this->addCheck('T13', 'API runtime canonical (/api/tenant/tickets + AuthenticateTenantApi middleware)', $passed,
            !$passed ? implode('; ', $details) : null
        );
    }

    protected function runT14_policyEnforcementActive(): void
    {
        try {
            $gate   = app(\Illuminate\Contracts\Auth\Access\Gate::class);
            $ticket = \SaaSPlatform\Modules\Tickets\Models\Ticket::class;
            $policy = $gate->getPolicyFor($ticket);
            $bound  = $policy !== null;

            $this->addCheck('T14', 'Policy enforcement active (TicketPolicy registered in Gate)', $bound,
                $bound ? null : 'No policy registered for Ticket model — Gate::policy() not called.'
            );
        } catch (\Throwable $e) {
            $this->addCheck('T14', 'Policy enforcement active', false, $e->getMessage());
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Pass a result directly from a sub-validator check, under a new check ID.
     */
    protected function passThrough(string $id, string $label, ?array $subCheck): void
    {
        if ($subCheck === null) {
            $this->addCheck($id, $label, false, 'Sub-validator check not available.');
            return;
        }

        $this->addCheck($id, $label, $subCheck['passed'], $subCheck['error'] ?? null,
            $subCheck['details'] ?? []
        );
    }

    protected function addCheck(string $id, string $label, bool $passed, ?string $error = null, array $details = []): void
    {
        $this->checks[] = [
            'check'   => $id,
            'label'   => $label,
            'passed'  => $passed,
            'error'   => $error,
            'details' => $details,
        ];
    }

    protected function indexByCheck(array $checks): array
    {
        $indexed = [];
        foreach ($checks as $check) {
            $indexed[$check['check']] = $check;
        }
        return $indexed;
    }
}
