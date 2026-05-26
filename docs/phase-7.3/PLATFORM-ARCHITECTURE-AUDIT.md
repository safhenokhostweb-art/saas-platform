# PLATFORM-ARCHITECTURE-AUDIT.md
## Phase 7.3 — Real Architecture Audit
**Date:** 2026-05-25 | **Auditor:** Principal Software Architect (Phase 7.3)

---

## 1. AUTHORITY MAP

### Platform Authority (saas-platform / Server A)
```
saas-platform/
├── OWNS: Platform admin UI (all /admin/* routes)
├── OWNS: Tenant/Reseller lifecycle
├── OWNS: Module marketplace & licensing
├── OWNS: License server (issues + validates keys)
├── OWNS: API surface for customer-site consumption
├── OWNS: Core data: users, sites, modules, licenses
├── OWNS: Canonical ticket & form data (tickets table, forms table)
└── DELIVERS: Module packages (core-utils, modules/tickets, modules/forms)
```

### Customer-Site Authority (customer-site / Servers B-D)
```
customer-site/
├── OWNS: End-user frontend rendering
├── OWNS: Local user accounts (separate users table)
├── OWNS: Shop, orders, products
├── OWNS: Page builder (8231 PHP files — NOT a thin runtime)
├── CONSUMES: saas-platform API (license validation, ticket CRUD, site-config)
├── CONSUMES: Platform packages (Forms module via AppServiceProvider binding)
└── WARNING: Has its own TenantContext, License model, AdminMenu, Builder — fully autonomous app
```

---

## 2. DEPENDENCY GRAPH

```
saas-platform (app/)
    ├── core-utils (packages/saas-platform/core-utils)
    │   ├── PlatformContext
    │   ├── TenantContextContract (interface only)
    │   ├── ModuleVersionRegistry
    │   ├── ReleaseManifest
    │   ├── ModuleUpdateOrchestrator
    │   ├── SharedLayoutResolver
    │   ├── ModuleNavigationService
    │   ├── OverrideAuthorityValidator
    │   └── Blade directives: @platform, @tenant, @central, @tenantadmin
    │
    ├── modules/tickets (packages/saas-platform/modules/tickets)
    │   ├── DEPENDS ON: core-utils (@dev)
    │   ├── ILLEGAL IMPORT: App\Models\Site  ← VIOLATION
    │   ├── ILLEGAL IMPORT: App\Models\User  ← VIOLATION
    │   └── ILLEGAL IMPORT: App\Http\Controllers\Controller ← VIOLATION
    │
    └── modules/forms (packages/saas-platform/modules/forms)
        ├── DEPENDS ON: core-utils (@dev)
        ├── ILLEGAL IMPORT: App\Models\Site  ← VIOLATION
        ├── ILLEGAL IMPORT: App\Models\Module ← VIOLATION
        ├── ILLEGAL IMPORT: App\Models\ModuleEntitlement ← VIOLATION
        ├── ILLEGAL IMPORT: App\Services\TenantContext ← VIOLATION
        ├── ILLEGAL IMPORT: App\Services\CaptchaService ← VIOLATION
        ├── ILLEGAL IMPORT: App\Services\ModuleAssignmentService ← VIOLATION
        ├── ILLEGAL IMPORT: App\Services\ModuleLicenseService ← VIOLATION
        ├── ILLEGAL IMPORT: App\Services\ActivityLogger ← VIOLATION
        └── ILLEGAL IMPORT: App\Helpers\Jalali ← VIOLATION
```

---

## 3. RUNTIME OWNERSHIP GRAPH

### Request Lifecycle (Web)
```
HTTP Request
  → TrustProxies
  → HandleCors
  → EncryptCookies / Session / CSRF
  → AddNoindexHeaders
  → DetectResellerFromDomain   ← calls ResellerDetectionService::fromRequest()
  → SetTenantContext            ← calls ResellerDetectionService::fromRequest() AGAIN (redundant)
  → Router
    → [admin middleware] AdminMiddleware → checks is_admin OR roles
    → [tenant.api middleware] AuthenticateTenantApi → license_key+domain from POST body
    → Controller
      → Service (via Interface)
        → Repository
          → Eloquent Model → MySQL
```

### Double TenantContext Resolution (Confirmed Bug)
Both `DetectResellerFromDomain` and `SetTenantContext` independently call `ResellerDetectionService::fromRequest()`.
This means every web request runs reseller domain lookup TWICE.

---

## 4. PACKAGE BOUNDARIES

### Core-Utils Package
- **Legitimate boundary**: Contracts, shared services, Blade directives, version registry
- **Status**: Clean. No illegal imports from app/.

### Tickets Module Package
- **Claimed boundary**: Autonomous ticket domain
- **Actual boundary**: BROKEN — directly imports `App\Models\{Site,User}` and `App\Http\Controllers\Controller`
- **Files with violations**: `TicketService.php`, `TicketsController.php`

### Forms Module Package
- **Claimed boundary**: Autonomous forms domain
- **Actual boundary**: BROKEN — 14+ cross-boundary imports from app/
- **Files with violations**: `FormService.php`, `FormSiteService.php`, `FormsLicensingService.php`, `FormsGateService.php`, `FormFrontendController.php`, `FormController.php`, `FormSiteController.php`, `FormSubmissionRepository.php`, `FormSubmissionService.php`, `PlatformContextService.php`, `FormSiteServiceInterface.php`

---

## 5. TENANT BOUNDARIES

### Platform Tenant Model (Reseller)
- TenantContext resolves to `Reseller` objects ONLY
- Isolation: Reseller sees only their own Customers
- Scope: `reseller_id` column enforced via `TenantContext::scopeQueryToTenant()`
- **GAP**: No enforcement of `site_id` at TenantContext level — done manually in services

### Module Tenant Isolation (Site)
- Tickets: `site_id` scoped in `TicketRepository::findByIdForSite()` and `getBySiteId()`
- Forms: `site_id` scoped via `FormSiteService`
- **GAP**: `TicketRepository::search()` has NO automatic tenant scope — admin can see all sites' tickets. Must be filtered by caller passing `site_id` filter explicitly.

---

## 6. FORBIDDEN COUPLING (ALL VIOLATIONS CONFIRMED)

| Source | Target | Type | Severity |
|--------|--------|------|----------|
| `tickets/TicketService.php` | `App\Models\Site` | Direct import | CRITICAL |
| `tickets/TicketService.php` | `App\Models\User` | Direct import | CRITICAL |
| `tickets/TicketsController.php` | `App\Http\Controllers\Controller` | Direct import | HIGH |
| `tickets/TicketsController.php` | `App\Services\TenantContext` | Direct import | CRITICAL |
| `forms/FormService.php` | `App\Models\Site` | Direct import | CRITICAL |
| `forms/FormService.php` | `App\Services\CaptchaService` | Direct import | HIGH |
| `forms/FormsLicensingService.php` | `App\Models\Module` | Direct import | CRITICAL |
| `forms/FormsLicensingService.php` | `App\Models\ModuleEntitlement` | Direct import | CRITICAL |
| `forms/FormsLicensingService.php` | `App\Services\ModuleAssignmentService` | Direct import | CRITICAL |
| `forms/FormsGateService.php` | `App\Services\TenantContext` | Direct import | CRITICAL |
| `forms/FormSiteServiceInterface.php` | `App\Models\Site` | Direct import in CONTRACT | CRITICAL |
| `forms/FormSubmissionRepository.php` | `App\Helpers\Jalali` | Direct import | MEDIUM |

---

## 7. HIDDEN COUPLING

### Ghost System: Dual Ticket Binding
`AppServiceProvider` binds:
- `SupportTicketRepositoryInterface` → `SupportTicketRepository`
- `SupportTicketServiceInterface` → `SupportTicketService`

`TicketsServiceProvider` binds:
- `TicketServiceInterface` → `TicketService`

Both systems are ACTIVE simultaneously. There are TWO ticket service trees running.

### Shared ModuleLifecycleInterface Name (Different Namespaces)
- `SaaSPlatform\Modules\Tickets\Contracts\ModuleLifecycleInterface`
- `SaaSPlatform\Modules\Forms\Contracts\ModuleLifecycleInterface`

These are different interfaces with the same local name. If any code tries to resolve `ModuleLifecycleInterface` without full namespace qualification, it gets whichever was bound last. This is a hidden runtime ambiguity.

---

## 8. CIRCULAR DEPENDENCY RISKS

### PlatformContext → TenantContextContract → TenantContext
- `PlatformContext` is constructed with an optional `TenantContextContract`
- `TenantContext` implements `TenantContextContract`
- `TenantContext` depends on `TenantResolver`
- If `PlatformContext` ever calls back into a service that triggers `TenantContext`, circular resolution could occur at boot

### CoreUtils → Tickets → CoreUtils
- `TicketsServiceProvider` registers a view composer that calls `app(PlatformContext::class)` (from core-utils) at VIEW RENDER time
- This is deferred, so no boot-time circular, but runtime circular could occur if PlatformContext tries to resolve ticket-related context

---

## 9. RUNTIME HOT SPOTS

| Hot Spot | Type | Impact |
|----------|------|--------|
| `SetTenantContext::handle()` | Redundant DB lookup | Every web request hits `resellers` table twice |
| `TicketRepository::search()` | No default scope | Full table scan without tenant filter possible |
| `UserRepository` | 900-line class with caching | Over-engineered, cache invalidation risk |
| `CoreUtilsServiceProvider::register()` | 11 singletons registered at boot | Boot time overhead, even if unused |
| `TicketsServiceProvider` | 14 singletons + 4 repository bindings | All registered regardless of whether tickets are enabled |
| `FormsServiceProvider` | 9 singletons + 3 repo bindings + 5 commands | All registered regardless of module enablement |

---

## 10. PRODUCTION BOTTLENECKS

1. **No cache layer on tenant resolution** — every request hits DB for reseller lookup
2. **Legacy dual ticket system** — two service trees active simultaneously, double memory usage
3. **Package boot overhead** — all module singletons registered at boot even when module is disabled
4. **TicketRepository.search() missing default tenant scope** — risk of data leaks under misconfiguration
5. **All 125 migrations run in sequence** — no parallelism; slow fresh installs and risky rolling deploys
