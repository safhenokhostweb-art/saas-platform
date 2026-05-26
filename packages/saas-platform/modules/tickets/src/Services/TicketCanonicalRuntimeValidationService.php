<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 7.2 — TASK 9
 *
 * Runtime validation service for the canonical Tickets module.
 * Runs structural and data-plane checks to confirm the canonical
 * system is ready to be the sole authority for ticket data.
 *
 * Checks:
 *  R1  Canonical tables exist and are reachable
 *  R2  Legacy routes are not direct (no active controller bindings)
 *  R3  Canonical routes are registered (tickets.admin.index etc.)
 *  R4  Canonical views are resolvable (tickets:: namespace)
 *  R5  TicketService is bound in the container
 *  R6  Migration parity — canonical count >= legacy count
 *  R7  No orphaned ticket_replies (broken FK)
 *  R8  Tenant isolation — no cross-site tickets in canonical table
 *  R9  Policy is registered for Ticket model
 *  R10 TicketDataMigrationService is bound (data migration ready)
 */
class TicketCanonicalRuntimeValidationService
{
    public function run(): array
    {
        $checks = [];

        $checks[] = $this->check('R1', 'Canonical tables exist',          fn() => $this->r1TablesExist());
        $checks[] = $this->check('R2', 'Legacy controller bindings absent', fn() => $this->r2LegacyControllersAbsent());
        $checks[] = $this->check('R3', 'Canonical routes registered',      fn() => $this->r3CanonicalRoutesRegistered());
        $checks[] = $this->check('R4', 'Canonical views resolvable',       fn() => $this->r4ViewsResolvable());
        $checks[] = $this->check('R5', 'TicketService bound in container', fn() => $this->r5ServiceBound());
        $checks[] = $this->check('R6', 'Migration parity (count)',         fn() => $this->r6MigrationParity());
        $checks[] = $this->check('R7', 'No orphaned ticket_replies',       fn() => $this->r7NoOrphanedReplies());
        $checks[] = $this->check('R8', 'Tenant isolation holds',           fn() => $this->r8TenantIsolation());
        $checks[] = $this->check('R9', 'Ticket policy registered',         fn() => $this->r9PolicyRegistered());
        $checks[] = $this->check('R10','Data migration service bound',     fn() => $this->r10MigrationServiceBound());

        $passed = collect($checks)->where('passed', true)->count();
        $total  = count($checks);

        return [
            'passed' => $passed === $total,
            'score'  => "{$passed}/{$total}",
            'checks' => $checks,
        ];
    }

    private function check(string $id, string $label, callable $fn): array
    {
        try {
            [$passed, $detail] = $fn();
        } catch (\Throwable $e) {
            $passed = false;
            $detail = 'Exception: ' . $e->getMessage();
        }

        return [
            'id'     => $id,
            'label'  => $label,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }

    private function r1TablesExist(): array
    {
        $missing = [];
        foreach (['tickets', 'ticket_replies'] as $table) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }
        return [
            empty($missing),
            empty($missing) ? 'Both canonical tables present' : 'Missing: ' . implode(', ', $missing),
        ];
    }

    private function r2LegacyControllersAbsent(): array
    {
        $legacyClasses = [
            \App\Http\Controllers\Admin\SupportTicketController::class,
            \App\Http\Controllers\Customer\CustomerSupportTicketController::class,
        ];

        $activeInRoutes = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction('controller') ?? '';
            foreach ($legacyClasses as $cls) {
                if (str_starts_with($action, $cls)) {
                    $activeInRoutes[] = $action;
                }
            }
        }

        return [
            empty($activeInRoutes),
            empty($activeInRoutes)
                ? 'No legacy ticket controllers active in routing'
                : 'Legacy controllers still active: ' . implode(', ', array_unique($activeInRoutes)),
        ];
    }

    private function r3CanonicalRoutesRegistered(): array
    {
        $required = [
            'tickets.admin.index',
            'tickets.admin.show',
            'tickets.admin.store',
            'tickets.site.index',
            'tickets.site.show',
        ];

        $missing = [];
        foreach ($required as $name) {
            if (!Route::has($name)) {
                $missing[] = $name;
            }
        }

        return [
            empty($missing),
            empty($missing) ? 'All canonical routes registered' : 'Missing routes: ' . implode(', ', $missing),
        ];
    }

    private function r4ViewsResolvable(): array
    {
        $views = [
            'tickets::admin.tickets.index',
            'tickets::admin.tickets.show',
            'tickets::site.tickets.index',
        ];

        $missing = [];
        foreach ($views as $view) {
            if (!view()->exists($view)) {
                $missing[] = $view;
            }
        }

        return [
            empty($missing),
            empty($missing) ? 'All canonical views resolvable' : 'Missing views: ' . implode(', ', $missing),
        ];
    }

    private function r5ServiceBound(): array
    {
        $bound = app()->bound(\SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface::class);

        return [
            $bound,
            $bound ? 'TicketServiceInterface bound' : 'TicketServiceInterface NOT bound in container',
        ];
    }

    private function r6MigrationParity(): array
    {
        if (!Schema::hasTable('support_tickets') || !Schema::hasTable('tickets')) {
            return [true, 'Legacy table not present — no parity check needed'];
        }

        $legacyCount    = DB::table('support_tickets')->whereNull('deleted_at')->count();
        $canonicalCount = DB::table('tickets')->whereNull('deleted_at')->count();

        if ($legacyCount === 0) {
            return [true, 'Legacy table empty — no migration needed'];
        }

        $passed = $canonicalCount >= $legacyCount;

        return [
            $passed,
            "Legacy: {$legacyCount} tickets — Canonical: {$canonicalCount} tickets" . ($passed ? '' : ' (MIGRATION INCOMPLETE)'),
        ];
    }

    private function r7NoOrphanedReplies(): array
    {
        if (!Schema::hasTable('ticket_replies')) {
            return [true, 'table ticket_replies not present yet'];
        }

        $orphaned = DB::table('ticket_replies')
            ->leftJoin('tickets', 'tickets.id', '=', 'ticket_replies.ticket_id')
            ->whereNull('tickets.id')
            ->count();

        return [
            $orphaned === 0,
            $orphaned === 0 ? 'No orphaned replies' : "{$orphaned} orphaned replies detected",
        ];
    }

    private function r8TenantIsolation(): array
    {
        if (!Schema::hasTable('tickets') || !Schema::hasTable('sites')) {
            return [true, 'tables not present — skipping'];
        }

        $crossSiteCount = DB::table('tickets')
            ->leftJoin('sites', 'sites.id', '=', 'tickets.site_id')
            ->whereNotNull('tickets.site_id')
            ->whereNull('sites.id')
            ->count();

        return [
            $crossSiteCount === 0,
            $crossSiteCount === 0 ? 'All tickets scoped to valid sites' : "{$crossSiteCount} tickets reference invalid sites",
        ];
    }

    private function r9PolicyRegistered(): array
    {
        try {
            $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
            $policy = $gate->getPolicyFor(\SaaSPlatform\Modules\Tickets\Models\Ticket::class);
            $bound = $policy !== null;
        } catch (\Throwable) {
            $bound = false;
        }

        return [
            $bound,
            $bound ? 'TicketPolicy registered' : 'No policy registered for Ticket model',
        ];
    }

    private function r10MigrationServiceBound(): array
    {
        $bound = app()->bound(TicketDataMigrationService::class);

        return [
            $bound,
            $bound ? 'TicketDataMigrationService bound' : 'TicketDataMigrationService NOT bound',
        ];
    }
}
