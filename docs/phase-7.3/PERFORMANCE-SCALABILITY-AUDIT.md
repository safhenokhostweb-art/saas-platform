# PERFORMANCE-SCALABILITY-AUDIT.md
## Phase 7.3 — Performance & Scalability Audit
**Date:** 2026-05-25

---

## 1. N+1 RISKS

### TicketRepository.search()
```php
return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
```
No eager loading in the base search. The `TicketController::index()` calls:
```php
$tickets = $this->ticketService->getAll($filters);
```
If each rendered ticket row accesses `$ticket->site->name`, `$ticket->assignee->name`, or `$ticket->replies`, this is N+1.

The `getById()` method does eager load: `['site', 'assignee', 'replies.user']` — but `getAll()` does NOT pass relations to `search()`.

**Risk Level:** HIGH — index pages with 20+ tickets will fire 40-60 extra queries if relations are accessed in the view.

### UserRepository
At 900 lines, this repository almost certainly has multiple query methods. Without seeing the full implementation, any method that returns a collection and then accesses user relations in a loop is a N+1 risk.

### ModuleAssignmentService
When loading enabled modules for all sites, if there's any per-site lookup in a loop, N+1 occurs. This is a likely pattern in admin dashboards showing multiple sites.

---

## 2. EAGER LOADING CONSISTENCY

| Method | Eager Loads | Risk |
|--------|-------------|------|
| `TicketService::getById()` | `site, assignee, replies.user` | SAFE |
| `TicketService::getBySiteId()` | `assignee` | PARTIAL |
| `TicketService::getAll()` | NONE | RISK |
| `TicketService::getByIdForSite()` | `site, assignee, replies.user` | SAFE |
| `CustomerRepository` | Unverified | RISK |

---

## 3. REPOSITORY QUERY EFFICIENCY

### Missing Database Indexes (Inferred)
The `TicketRepository::search()` filters on:
- `status` — likely indexed (common filter)
- `priority` — likely not indexed
- `site_id` — MUST be indexed for tenant isolation
- `category` — likely not indexed
- Full-text search on `subject`, `ticket_number`, `description` — LIKE '%term%' with leading wildcard = full table scan

Without explicit index definitions in migrations, these filters will cause full table scans on large datasets.

### Missing: Full-Text Search Index
```php
$query->where('subject', 'like', "%{$search}%")
    ->orWhere('ticket_number', 'like', "%{$search}%")
    ->orWhere('description', 'like', "%{$search}%");
```
Three `LIKE '%...%'` queries on potentially large text columns. This will not use any index. For production with thousands of tickets, this will be extremely slow.

---

## 4. CACHE OPPORTUNITIES

### Currently Identified Caching
- `UserRepository` has caching (900-line class)
- No reseller cache layer on `TenantContext`
- No navigation/menu caching confirmed

### Missing Cache Layers
1. **Reseller domain resolution** — called TWICE per request (DetectResellerFromDomain + SetTenantContext). Should be cached by domain for the request lifecycle at minimum. Redis cache with 5-minute TTL for domain→reseller mapping would eliminate these 2 DB queries per request.

2. **Module registry** — `ModuleRegistryService` is a singleton but re-scans on each instantiation. Should cache the resolved module list.

3. **Ticket stats** — `TicketService::getStats()` called on every admin index page load. Should be cached with a short TTL (30 seconds).

4. **Navigation items** — `ModuleNavigationService` singleton may re-query menu items on each render.

### Unsafe Cache Usage
- If `UserRepository` uses simple cache keys (e.g., `users:all`) without tenant scoping, cross-tenant data leaks via shared Redis.

---

## 5. QUEUE BOTTLENECKS

### Event Dispatch Analysis
`TicketCreated`, `TicketReplied`, `TicketStatusChanged` events are dispatched from `TicketService`.
- `TicketNotificationService` presumably handles these
- No evidence of queue driver configuration
- If email/SMS notifications are sent synchronously (in the request), every ticket creation blocks the web response for SMTP round-trip time

**Risk:** With a slow SMTP server, ticket creation could take 3-10 seconds from user perspective.

### No Queue Isolation
No evidence of separate queue workers per tenant. All ticket notifications from all sites go through the same queue. A flood of tickets from one site can starve notifications for other sites.

---

## 6. EVENT EXPLOSION RISKS

### Chain Events
`TicketStatusChanged` → triggers notification → notification sends email/SMS
If a bulk status update is performed (admin changes 50 tickets at once), 50 events fire, potentially 50 email jobs queued simultaneously.

No batch notification or event debouncing is visible.

---

## 7. RUNTIME VALIDATOR OVERHEAD

Core-utils registers:
- `CanonicalFrontendValidationService`
- `CustomerPackageRuntimeValidator`
- `CanonicalUiParityValidator`
- `OverrideAuthorityValidator`

Tickets module registers:
- `TicketRuntimeSafetyService`
- `TicketCanonicalValidationService`
- `TicketCanonicalRuntimeValidationService`
- `TicketOverrideSafetyService`
- `TicketMonitoringService`

These are ALL singletons. If any of them perform file-system scans, DB queries, or config lookups in their constructors or on first access, every request that touches these singletons pays that cost.

**Risk:** If validation services scan the packages directory to verify integrity on each boot, the overhead is significant. This type of "canonical validation" often involves `glob()` calls on the filesystem.

---

## 8. SINGLETON STATE ISSUES

### TenantContext Singleton + Octane
`TenantContext` is a singleton with mutable state (`$this->tenant`, `$this->tenantType`). On traditional FPM, `clear()` in `SetTenantContext` middleware resets it between requests. However:

- **Laravel Octane:** PHP workers are long-lived. If `clear()` is not called, tenant state persists between requests on the same worker.
- **Test environments:** If test classes don't reset the singleton between tests, tenant bleeds between test cases.

The current `SetTenantContext` does call `clear()` before AND after the request — this is correct for FPM. But it's a fragile guarantee.

### 11+ Core-Utils Singletons + 14+ Tickets Singletons + 9+ Forms Singletons
Total: 34+ singletons registered per request lifecycle. Even if they're lightweight, this is a boot overhead concern for serverless/short-lived runtimes.

---

## 9. CONTAINER RESOLUTION OVERHEAD

All packages use constructor injection. The container must resolve the full dependency tree for each singleton:
- `TicketService` → requires `TicketRepositoryInterface` + `TicketReplyRepositoryInterface`
- `TicketController` → requires `TicketServiceInterface` + `TicketGateService` + `TicketSlaService`

Each new request creates new controller instances, which trigger new repository instances (repositories are NOT singletons). If repository constructors perform any initialization work, this overhead is repeated per request.

---

## 10. BLADE RENDERING OVERHEAD

### Inline CSS in Views
`resources/views/admin/support-tickets/index.blade.php` has ~200 lines of inline CSS in `@push('head')`. This CSS is re-rendered on every page load. It should be in a compiled asset file.

### View Composer on Every Admin Tickets View
```php
view()->composer('tickets::admin.*', function ($view) {
    if (! $this->app->bound(PlatformContext::class)) { return; }
    $context = $this->app->make(PlatformContext::class);
    if ($context->isTenantAdmin()) {
        config(['tickets.layouts.admin' => $tenantLayout]);
    }
});
```
This view composer runs on EVERY render of any `tickets::admin.*` view. It calls `config()` mutation during rendering, which modifies the config store for the rest of the request.

---

## 11. PACKAGE BOOT OVERHEAD

Every request boots:
- `CoreUtilsServiceProvider::register()` — 11 singleton bindings + 2 config merges
- `CoreUtilsServiceProvider::boot()` — view registration + 14 commands + Blade directives
- `TicketsServiceProvider::register()` — 18 service bindings + 2 config merges
- `TicketsServiceProvider::boot()` — 3 route files + views + migrations + policies + publishables + view composers
- `FormsServiceProvider::register()` — 12 service bindings + 2 config merges
- `FormsServiceProvider::boot()` — routes + views + migrations + commands

**Total:** ~100+ container operations at bootstrap.

---

## PERFORMANCE SCALABILITY SCORE

| Category | Score | Notes |
|----------|-------|-------|
| N+1 protection | 40/100 | Partial eager loading, getAll() unprotected |
| Query efficiency | 30/100 | LIKE searches, missing indexes |
| Cache coverage | 35/100 | Some caching in UserRepository, gaps elsewhere |
| Queue architecture | 30/100 | No isolation, potential sync notifications |
| Boot overhead | 40/100 | 34+ singletons, all loaded at boot |
| Singleton safety | 50/100 | FPM safe, Octane risky |
| Batch operations | 20/100 | No bulk operation patterns |

**Overall Performance Scalability Score: 35/100**
