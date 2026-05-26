# Package Boundary Audit — Phase 8.1
**Date:** 2026-05-26 | **Scope:** All three packages

---

## DEPENDENCY GRAPH (Current State — Before Phase 8.1)

```
┌─────────────────────────────────────────────────────────────────┐
│                        HOST APPLICATION                         │
│  App\Models\Site            App\Services\TenantContext          │
│  App\Models\User            App\Services\CaptchaService         │
│  App\Models\Module          App\Services\ActivityLogger         │
│  App\Models\ModuleEntitlement  App\Services\ModuleAssignmentService │
│  App\Http\Controllers\Controller  App\Services\ModuleLicenseService │
│  App\Helpers\Jalali                                             │
└────────────┬──────────────────────────────────────────┬────────┘
             │ 14+ direct imports                        │ 4 direct imports
             ▼                                           ▼
┌────────────────────────────┐            ┌──────────────────────────┐
│   modules/forms (src/)     │            │  modules/tickets (src/)  │
│   CANNOT boot standalone   │            │  PARTIALLY coupled       │
│   CANNOT distribute safely │            │                          │
└────────────────────────────┘            └──────────────────────────┘
             │ (no imports)                              │ (no imports)
             ▼                                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                        core-utils                               │
│          config('app.*') assumptions + app() helpers            │
└─────────────────────────────────────────────────────────────────┘
```

---

## VIOLATIONS — modules/forms

### CRITICAL

| # | File | Violation | Impact |
|---|------|-----------|--------|
| C-01 | `Http/Controllers/FormController.php` | `use App\Http\Controllers\Controller` | Cannot boot on vanilla Laravel |
| C-02 | `Http/Controllers/FormFrontendController.php` | `use App\Http\Controllers\Controller` + `use App\Models\Site` + `use App\Services\CaptchaService` (static calls) | 3 direct App\ couplings |
| C-03 | `Http/Controllers/FormSiteController.php` | `use App\Http\Controllers\Controller` + `use App\Models\Site` | 2 direct App\ couplings |
| C-04 | `Http/Controllers/FormSubmissionController.php` | `use App\Http\Controllers\Controller` | Cannot boot on vanilla Laravel |
| C-05 | `Contracts/FormSiteServiceInterface.php` | `use App\Models\Site` in interface signature | Contract itself is poisoned — cannot be used outside saas-platform |
| C-06 | `Models/Form.php` | `\App\Models\Site::class` in `belongsToMany()` | Relationship hardcoded to host model |
| C-07 | `Services/FormController.php` | `use App\Services\ActivityLogger` (static `class_exists` call) | Service-layer App\ import |
| C-08 | `Services/FormService.php` | `use App\Services\CaptchaService` (static calls) + `use App\Models\Site` | 2 couplings in core service |
| C-09 | `Services/FormSiteService.php` | `use App\Models\Site` (direct static: `Site::pluck()`, `Site::latest()->get()`) | Direct Eloquent query on host model |
| C-10 | `Services/FormSubmissionService.php` | `use App\Helpers\Jalali` + inline `\App\Models\Site::orderBy()` | 2 couplings in data service |
| C-11 | `Services/FormsGateService.php` | `use App\Models\Site` + `use App\Services\TenantContext` | Gate logic tied to host models |
| C-12 | `Services/FormsLicensingService.php` | `use App\Models\Module` + `use App\Models\ModuleEntitlement` + `use App\Services\ModuleAssignmentService` + `use App\Services\ModuleLicenseService` | 4 host App\ imports in licensing logic |
| C-13 | `Services/PlatformContextService.php` | `use App\Models\Site` + `use App\Services\TenantContext` + `\App\Models\Site::count()` (static) | Context detection coupled to host models |
| C-14 | `Repositories/FormSubmissionRepository.php` | `use App\Helpers\Jalali` | Date formatting tied to host helper |

### HIGH

| # | File | Violation | Impact |
|---|------|-----------|--------|
| H-01 | `Services/FormsGateService.php` | `request()->ip()` + `request()->fullUrl()` in logging | Hidden global state coupling |
| H-02 | `Services/FormsLicensingService.php` | `request()->ip()` + `request()->userAgent()` + `request()->fullUrl()` | Request object injected via global |
| H-03 | `Services/FormsMonitoringService.php` | `request()->ip()` + `request()->userAgent()` + `request()->fullUrl()` + `request()->method()` | 4 request() calls in service |

### MEDIUM

| # | File | Violation | Impact |
|---|------|-----------|--------|
| M-01 | `resources/views/site/forms/show.blade.php` | `config('app.name')` | View assumes host app.name config |
| M-02 | `Services/FormsRuntimeSafetyService.php` | `config('app.providers', [])` | Assumes host app.providers config |

---

## VIOLATIONS — modules/tickets

### CRITICAL

| # | File | Violation | Impact |
|---|------|-----------|--------|
| C-15 | `Http/Controllers/TicketsController.php` | `use App\Http\Controllers\Controller` + `use App\Services\TenantContext` + `\App\Models\Site::where(...)` inline | Legacy controller — 3 violations |
| C-16 | `Services/TicketService.php` | `use App\Models\Site` + `use App\Models\User` (both dead imports) | Stale App\ imports — indicates prior coupling |

### HIGH

| # | File | Violation | Impact |
|---|------|-----------|--------|
| H-04 | `Services/TicketGateService.php` | `auth()->user()?->is_admin` | Assumes `is_admin` property on host User model |
| H-05 | `Http/Controllers/Admin/TicketAssignmentController.php` | `auth()->id()` passed to services | Auth assumption in controller layer |
| H-06 | `Http/Controllers/Admin/TicketStatusController.php` | `auth()->id()` passed to services | Auth assumption in controller layer |
| H-07 | `Http/Controllers/Admin/TicketPriorityController.php` | `auth()->id()` passed to services | Auth assumption in controller layer |

---

## VIOLATIONS — core-utils

### MEDIUM

| # | File | Violation | Impact |
|---|------|-----------|--------|
| M-03 | `Services/PlatformContext.php` | `config('app.role')` | Assumes host app.role config key |
| M-04 | `Console/Commands/ModuleListCommand.php` | `config('app.platform_version', '1.0.0')` | Assumes host version config |
| M-05 | `Services/PlatformCompatibilityService.php` | `config('app.platform_version', '1.0.0')` | Assumes host version config |

### LOW

| # | File | Violation | Impact |
|---|------|-----------|--------|
| L-01 | `Traits/HasUserStamps.php` | `auth()` helper | Framework-level global (acceptable but replaceable) |
| L-02 | `resources/views/components/filter-bar.blade.php` | `request()` helper | Framework-level global in view (acceptable) |
| L-03 | `Blade/AssetModuleDirective.php` | `app('blade.compiler')` | Framework container call (acceptable) |

---

## SUMMARY BY SEVERITY

| Severity | Count | Packages Affected |
|----------|-------|-------------------|
| CRITICAL | 16 | forms (14), tickets (2) |
| HIGH | 7 | forms (3), tickets (4) |
| MEDIUM | 5 | forms (2), core-utils (3) |
| LOW | 3 | core-utils (3) |
| **TOTAL** | **31** | **all 3** |

---

## REQUIRED CONTRACTS (to be created in core-utils)

| Contract | Replaces |
|----------|----------|
| `SiteModelInterface` (expand) | `App\Models\Site` type hints |
| `SiteRepositoryInterface` | `Site::where()`, `Site::pluck()`, `Site::latest()->get()` |
| `CaptchaServiceInterface` | `App\Services\CaptchaService` static calls |
| `ActivityLoggerInterface` | `App\Services\ActivityLogger` static calls |
| `ModuleLicenseServiceInterface` | `App\Models\Module` + `App\Services\ModuleAssignmentService` + `App\Services\ModuleLicenseService` |
| `DateFormatterInterface` | `App\Helpers\Jalali` |
| `AuthUserResolverInterface` | `auth()->user()?->is_admin` |
| `BaseController` (class) | `App\Http\Controllers\Controller` |
| `TenantContextContract` (exists) | `App\Services\TenantContext` |

---

## FILES TO MODIFY/CREATE

| Action | Count |
|--------|-------|
| Create new contracts in core-utils | 8 |
| Create BaseController in core-utils | 1 |
| Move Jalali to core-utils/Support | 1 |
| Modify forms module files | 10 |
| Modify tickets module files | 3 |
| Create host app adapters | 7 |
| Create InfrastructureServiceProvider | 1 |
| **Total** | **31** |
