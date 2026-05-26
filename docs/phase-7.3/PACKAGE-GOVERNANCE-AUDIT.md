# PACKAGE-GOVERNANCE-AUDIT.md
## Phase 7.3 — Package Governance Audit
**Date:** 2026-05-25

---

## 1. CORE-UTILS PACKAGE

### Package Identity
```
Name: saas-platform/core-utils
Namespace: SaaSPlatform\CoreUtils
Version: @dev (no semantic version)
Depends on: Laravel ^10|^11|^12, PHP ^8.1
```

### Boundary Integrity
**Status: CLEAN**
- No imports from `App\` namespace
- Provides contracts, not implementations of platform business logic
- `TenantContextContract` is an interface — consuming apps bind their own implementation

### Concerns

**C-001 — PlatformContext Detection Logic Is Fragile**
```php
// Priority 1: check config('app.role')
// Priority 2: TenantContextContract
// Priority 3: injectedContext flags
// Priority 4: App environment fallback
```
`config('app.role')` is not a standard Laravel config key. If not set, the detection falls to `TenantContextContract` which in the platform context resolves to a Reseller object — but `isTenant()` vs `isCentralAdmin()` logic may be incorrect in edge cases.

**C-002 — Singletons Registered Regardless of Usage**
11 singletons + 4 support services registered in `CoreUtilsServiceProvider::register()` on EVERY boot of any app that includes this package. Apps that only need `ModuleVersionRegistry` still pay the boot cost of `CanonicalUiParityValidator`, `CustomerPackageRuntimeValidator`, etc.

**C-003 — Blade Directives Use app() at Render Time**
```php
Blade::if('platform', function () {
    return app(PlatformContext::class)->isCentralAdmin();
});
```
Using `app()` facade inside a closure creates a hidden runtime dependency. If `PlatformContext` is not bound (e.g., in test environments that don't load the full service provider stack), this throws silently or returns wrong results.

**C-004 — No Package Version Published**
The `ModuleVersionRegistry` and `ReleaseManifest` classes exist for versioning, but the `composer.json` has no `version` field. Packagist will not be able to serve specific versions.

### Publishability Assessment
- **Config publishable:** YES (`feature-flags.php`, `module-assets.php`)
- **Views publishable:** YES (`core-ui-views` tag)
- **Commands registered:** YES (all 14 commands)
- **Auto-discovery:** YES (via `extra.laravel.providers`)
- **Semantic version:** NO
- **Private Packagist ready:** NO (path repository only)

**Score: 60/100**

---

## 2. MODULES/TICKETS PACKAGE

### Package Identity
```
Name: saas-platform/modules-tickets
Namespace: SaaSPlatform\Modules\Tickets
Version: @dev
Depends on: core-utils @dev, PHP ^8.1
```

### Boundary Integrity
**Status: CRITICALLY BROKEN**

Confirmed illegal cross-boundary imports:
```
TicketService.php:5      → use App\Models\Site;
TicketService.php:6      → use App\Models\User;
TicketsController.php:5  → use App\Http\Controllers\Controller;
TicketsController.php:6  → use App\Services\TenantContext;
```

The package CANNOT be installed on any project that does not have these exact classes at these exact namespaces. This fundamentally breaks the "distributable package module" architecture goal.

### Service Architecture
**14 canonical singletons** is excessive for a ticket domain:
- `TicketLifecycleService`, `TicketAssignmentService`, `TicketStatusService` — reasonable decomposition
- `TicketRuntimeSafetyService`, `TicketOverrideSafetyService`, `TicketCanonicalValidationService` — validation/safety services that should be lightweight and not singletons (they hold no state)
- `TicketDataMigrationService`, `TicketIntegrityValidationService`, `TicketRollbackService` — migration services registered as singletons on EVERY boot, even when no migration is needed

**Impact:** On every request to any route, the container boots 14+ ticket singletons. If the tickets module is disabled for a site, the singletons are still registered.

### ModuleLifecycleInterface
`SaaSPlatform\Modules\Tickets\Contracts\ModuleLifecycleInterface` — bound to `TicketModuleLifecycle`.
This interface is LOCAL to this package namespace. The forms package has a DIFFERENT interface with the same local name in its own namespace. There is no SHARED lifecycle contract in `core-utils`. This means there is no way to orchestrate lifecycle across modules through a single interface.

### Migration Safety
- Package migrations in `Database/Migrations/`
- Migrations reference `sites` table (must exist from platform app migrations)
- No explicit dependency declaration on platform migration
- `TicketRollbackService` custom rollback — not standard `down()` methods
- Publishable migrations: YES — but if published to app, they conflict with the auto-loaded package migrations

### Asset Registration Bug
```php
protected function registerModuleAssets(): void
{
    if (app()->environment('production')) {
        return; // SKIPS production — BUG
    }
```
Production gets NO asset registration in the module-assets config.

### Route Conflict Risk
The tickets module registers THREE route files:
- `Routes/admin.php` — prefix `/admin/tickets`
- `Routes/site.php` — unknown prefix
- `Routes/api.php` — prefix `/api/tickets`

Main `routes/api.php` also registers:
- `/api/tenant/tickets` via `TenantTicketController`

There are now TWO ticket API route trees: `/api/tickets` (auth:sanctum, package) and `/api/tenant/tickets` (tenant.api, main app). Their purposes overlap.

### Publishability Assessment
- **Config publishable:** YES
- **Views publishable:** YES
- **Migrations publishable:** YES (but risky — see above)
- **Assets publishable:** YES
- **Semantic version:** NO
- **Distributable without platform app:** NO (illegal imports)
- **Auto-discovery:** YES

**Score: 30/100**

---

## 3. MODULES/FORMS PACKAGE

### Package Identity
```
Name: saas-platform/modules-forms
Namespace: SaaSPlatform\Modules\Forms
Version: @dev
Depends on: core-utils @dev, PHP ^8.1
```

### Boundary Integrity
**Status: CRITICALLY BROKEN**

Confirmed illegal cross-boundary imports (14+):
```
FormSiteServiceInterface.php → use App\Models\Site;  ← CONTRACT references platform model
FormService.php              → use App\Models\Site, App\Services\CaptchaService
FormsLicensingService.php    → use App\Models\{Module, ModuleEntitlement, Site}
                             → use App\Services\{ModuleAssignmentService, ModuleLicenseService}
FormsGateService.php         → use App\Models\Site, App\Services\TenantContext
FormSiteService.php          → use App\Models\Site
FormFrontendController.php   → use App\Models\Site, App\Services\CaptchaService
FormController.php           → use App\Services\ActivityLogger
FormSubmissionRepository.php → use App\Helpers\Jalali
FormSubmissionService.php    → use App\Helpers\Jalali
PlatformContextService.php   → use App\Models\Site, App\Services\TenantContext
```

The contracts themselves reference platform models (e.g., `FormSiteServiceInterface` has `use App\Models\Site`). This means the INTERFACE BOUNDARY IS BROKEN — not just the implementation.

### Missing Contracts for Platform Dependencies
The following platform services are imported directly with no interface abstraction:
- `App\Services\CaptchaService` — no `CaptchaServiceInterface` in core-utils
- `App\Services\ModuleAssignmentService` — no interface
- `App\Services\ActivityLogger` — no interface
- `App\Helpers\Jalali` — hardcoded date helper

### Duplicate PlatformContextService
The forms package has its OWN `PlatformContextService` that duplicates functionality of core-utils's `PlatformContext`. This is architectural duplication.

### Publishability Assessment
- **Config publishable:** YES
- **Views publishable:** YES
- **Distributable without platform app:** NO
- **Semantic version:** NO
- **Contract isolation:** BROKEN

**Score: 20/100**

---

## 4. PACKAGE GOVERNANCE GAPS

### No Shared BaseController in Core-Utils
Both modules extend `App\Http\Controllers\Controller`. A `SaaSPlatform\CoreUtils\Http\Controllers\BaseController` should exist in core-utils so modules don't import from the host app.

### No Shared Model Contracts
Platform models (`Site`, `User`, `Module`) are referenced by packages but no contracts/interfaces exist. Packages should depend on `SiteInterface`, `UserInterface` from core-utils — not concrete `App\Models`.

### No Module Registry Enforcement
`ModuleRegistryService` exists but there's no enforcement that modules register themselves in a standard way. `TicketsServiceProvider` and `FormsServiceProvider` register assets inconsistently (one has production guard, one doesn't).

### No Package Test Suites
No test files found in the packages themselves. Package correctness depends entirely on the platform app's test suite — this means packages can't be independently tested before distribution.

### Version Registry vs Actual Package Versions
`ModuleVersionRegistry` (core-utils) tracks versions in a registry, but the actual `composer.json` files have no `version` field. There is no single source of truth for what version a package is at.

---

## 5. FUTURE MODULE READINESS

### Third-Party Module Acceptance
**Status: NOT READY**

For the platform to accept third-party modules:
- [ ] Module contract: a standardized `composer.json` `extra.saas-platform.module` section
- [ ] No `App\` imports allowed in modules
- [ ] Module lifecycle interface in core-utils (shared, not per-module)
- [ ] Module manifest validation command (partially exists via drift commands)
- [ ] Module signature verification
- [ ] Module sandbox/isolation (filesystem, DB migrations)

Current modules (forms, tickets) fail the first three requirements.

### Package Governance Score

| Package | Boundary | Contracts | Versioning | Publishable | Score |
|---------|----------|-----------|------------|-------------|-------|
| core-utils | CLEAN | GOOD | MISSING | YES | 60/100 |
| modules/tickets | BROKEN | PARTIAL | MISSING | NO | 30/100 |
| modules/forms | BROKEN | BROKEN | MISSING | NO | 20/100 |

**Overall Package Governance Score: 37/100**
