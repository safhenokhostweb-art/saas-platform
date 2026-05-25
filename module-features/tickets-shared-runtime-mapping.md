# Tickets Module — Shared Runtime Mapping (PHASE 7.0 TASK 3)

**Date:** 2026-05-24  
**Author:** AI Architecture Audit  
**Status:** ✅ COMPLETE — All runtime dependencies mapped

---

## 1. PURPOSE

Map all runtime dependencies across the three implementations (A: Package, B: Project, C: customer-site) to identify:
- Shared dependencies (same across all)
- Divergent dependencies (different versions/configs)
- Missing dependencies (present in canonical but absent in package)
- External dependencies (services, APIs, third-party packages)

---

## 2. COMPOSER DEPENDENCIES

### 2.1 Required Packages (saas-platform)

| Package | Version | Used By | Purpose |
|---------|---------|:-------:|---------|
| `laravel/framework` | ^10.0 | A, B | Core framework |
| `spatie/laravel-permission` | ^5.0 | B | Role/permission checks in controllers |
| `spatie/laravel-activitylog` | ^4.0 | B | Activity logging (`ActivityLogger::log()`) |
| `mohammad-fouladgar/laravel-jalali` | ^2.0 | B | Jalali date formatting in views |
| `laravel/sanctum` | ^3.0 | B | API authentication for customer-site |
| `guzzlehttp/guzzle` | ^7.0 | C | HTTP client for API calls |

### 2.2 Required Packages (customer-site)

| Package | Version | Used By | Purpose |
|---------|---------|:-------:|---------|
| `laravel/framework` | ^10.0 | C | Core framework |
| `guzzlehttp/guzzle` | ^7.0 | C | HTTP client for `SupportApiClient` |
| `mohammad-fouladgar/laravel-jalali` | ^2.0 | C | Jalali date formatting in views |

### 2.3 Gap Analysis

| Package | In A (Package) | In B (Project) | In C (customer-site) | Action |
|---------|:--------------:|:--------------:|:--------------------:|--------|
| `spatie/laravel-activitylog` | ❌ | ✅ | N/A | **ADD to package** |
| `mohammad-fouladgar/laravel-jalali` | ❌ | ✅ | ✅ | **ADD to package** |
| `guzzlehttp/guzzle` | ❌ | ❌ | ✅ | Keep in customer-site only |
| `laravel/sanctum` | ❌ | ✅ | ❌ | **ADD to package** (for API) |
| `spatie/laravel-permission` | ❌ | ✅ | ❌ | **ADD to package** (for admin auth) |

---

## 3. LARAVEL PROVIDERS & ALIASES

### 3.1 Service Providers

| Provider | A (Package) | B (Project) | C (customer-site) | Notes |
|----------|:-----------:|:-----------:|:-----------------:|-------|
| `TicketsServiceProvider` | ✅ | ❌ | ❌ | Package provider, needs lifecycle |
| `ActivitylogServiceProvider` | ❌ | ✅ | ❌ | From spatie/laravel-activitylog |
| `JalaliServiceProvider` | ❌ | ✅ | ✅ | From fouladgar/laravel-jalali |
| `PermissionServiceProvider` | ❌ | ✅ | ❌ | From spatie/laravel-permission |
| `SanctumServiceProvider` | ❌ | ✅ | ❌ | From laravel/sanctum |

### 3.2 Facades / Aliases

| Alias | A (Package) | B (Project) | C (customer-site) | Notes |
|-------|:-----------:|:-----------:|:-----------------:|-------|
| `ActivityLogger` | ❌ | ✅ | ❌ | Used in SupportTicketService |
| `Jalali` | ❌ | ✅ | ✅ | Used in views |
| `NumberHelper` | ❌ | ✅ | ✅ | Custom helper, not a facade |

---

## 4. CONFIGURATION DEPENDENCIES

### 4.1 Config Files Referenced

| Config Key | A (Package) | B (Project) | C (customer-site) | Purpose |
|------------|:-----------:|:-----------:|:-----------------:|---------|
| `tickets.*` | ✅ | ❌ | ❌ | Package config (statuses, priorities, pagination, prefix) |
| `support.*` | ❌ | ✅ | ❌ | Project config (SLA, grace_days, levels, business_hours) |
| `support.sla.*` | ❌ | ✅ | ❌ | SLA hours per support level |
| `support.grace_days` | ❌ | ✅ | ❌ | Grace period for expired support |
| `support.levels` | ❌ | ✅ | ❌ | Available support levels |
| `support.business_hours.*` | ❌ | ✅ | ❌ | Business hours config |
| `activitylog.*` | ❌ | ✅ | ❌ | Activity log configuration |
| `permission.*` | ❌ | ✅ | ❌ | Permission configuration |
| `sanctum.*` | ❌ | ✅ | ❌ | Sanctum configuration |
| `module-assets.modules` | ✅ | ❌ | ❌ | Asset symlink registration |

### 4.2 Config Gap Analysis

| Config | In Package | In Project | Action |
|--------|:----------:|:----------:|--------|
| `tickets.statuses` | ✅ | ❌ | Keep in package |
| `tickets.priorities` | ✅ | ❌ | Keep in package |
| `tickets.pagination` | ✅ | ❌ | Keep in package |
| `tickets.ticket_number_prefix` | ✅ | ❌ | Keep in package |
| `support.sla` | ❌ | ✅ | **ADD to package** as `tickets.sla` |
| `support.grace_days` | ❌ | ✅ | **ADD to package** as `tickets.grace_days` |
| `support.levels` | ❌ | ✅ | **ADD to package** as `tickets.levels` |
| `support.business_hours` | ❌ | ✅ | **ADD to package** as `tickets.business_hours` |

---

## 5. MODEL DEPENDENCIES

### 5.1 Related Models Referenced

| Model | A (Package) | B (Project) | C (customer-site) | Purpose |
|-------|:-----------:|:-----------:|:-----------------:|---------|
| `App\Models\Site` | ✅ | ✅ | ❌ | Site ownership, licensing |
| `App\Models\User` | ✅ | ✅ | ❌ | Assignee, customer relation |
| `App\Models\Customer` | ❌ | ✅ | ❌ | Customer portal site scoping |
| `App\Models\License` | ❌ | ❌ | ✅ | Customer-site license model |

### 5.2 Model Relationship Map

```
Ticket (Package) / SupportTicket (Project)
├── belongsTo: Site (site_id)
├── belongsTo: User (assigned_to) — assignee
├── belongsTo: User (customer_id) — Package only, REMOVE
├── hasMany: TicketReply / SupportTicketReply (replies)
└── (Project only) support_level from SupportLicenseService → Site

TicketReply (Package) / SupportTicketReply (Project)
├── belongsTo: Ticket / SupportTicket (ticket_id / support_ticket_id)
└── belongsTo: User (user_id)
```

---

## 6. SERVICE DEPENDENCIES

### 6.1 Service Dependency Graph (Canonical — B)

```
SupportTicketService
├── SupportTicketRepositoryInterface
│   └── SupportTicketRepository
│       ├── SupportTicket (Model)
│       └── Cache (Facade)
├── SupportTicketReplyRepositoryInterface
│   └── SupportTicketReplyRepository
│       ├── SupportTicketReply (Model)
│       └── Cache (Facade)
├── SupportLicenseService
│   └── Site (Model)
│       └── config('support.*')
└── ActivityLogger (Facade)

SupportPortalService
├── LicenseServerService
│   └── HTTP Client → External License Server
├── SupportLicenseService
└── SupportTicketService

SupportSlaService
├── SupportLicenseService
└── config('support.sla.*')
    └── config('support.business_hours.*')
```

### 6.2 Service Dependencies Missing from Package (A)

| Dependency | Present in B | Present in A | Action |
|------------|:------------:|:------------:|--------|
| `SupportTicketRepositoryInterface` | ✅ | ❌ | **ADD to package** |
| `SupportTicketReplyRepositoryInterface` | ✅ | ❌ | **ADD to package** |
| `SupportTicketRepository` | ✅ | ❌ | **ADD to package** |
| `SupportTicketReplyRepository` | ✅ | ❌ | **ADD to package** |
| `SupportLicenseService` | ✅ | ❌ | **ADD to package** |
| `SupportSlaService` | ✅ | ❌ | **ADD to package** |
| `SupportPortalService` | ✅ | ❌ | **ADD to package** |
| `ActivityLogger` | ✅ | ❌ | **ADD to package** (via spatie/laravel-activitylog) |
| `Cache` (Facade) | ✅ | ❌ | **ADD to package** (Laravel built-in) |
| `LicenseServerService` | ✅ | ❌ | **ADD to package** (or keep in project) |

---

## 7. CONTROLLER DEPENDENCIES

### 7.1 Admin Controller Dependencies

| Dependency | A: Package (TicketsController) | B: Project (SupportTicketController) |
|------------|:-----------------------------:|:------------------------------------:|
| `Controller` (base) | ✅ | ✅ |
| `TenantContext` | ✅ | ❌ |
| `TicketService` / `SupportTicketService` | ✅ | ✅ |
| `Request` | ✅ (inline validation) | ❌ (uses FormRequests) |
| `StoreSupportTicketRequest` | ❌ | ✅ |
| `StoreSupportTicketReplyRequest` | ❌ | ✅ |
| `UpdateSupportTicketStatusRequest` | ❌ | ✅ |
| `AssignSupportTicketRequest` | ❌ | ✅ |
| `Site` (Model) | ✅ | ✅ |
| `Ticket` / `SupportTicket` (Model) | ✅ | ✅ |

### 7.2 API Controller Dependencies (B only)

| Dependency | Present | Notes |
|------------|:-------:|-------|
| `SupportPortalService` | ✅ | Core service |
| `SupportTicket` (Model) | ✅ | For payload mapping |
| `SupportTicketReply` (Model) | ✅ | For reply payload mapping |
| `StoreSupportTicketApiRequest` | ✅ | API FormRequest |
| `StoreSupportTicketReplyApiRequest` | ✅ | API FormRequest |
| `IndexSupportTicketsRequest` | ✅ | API FormRequest |
| `SupportPortalException` | ✅ | Exception handling |

### 7.3 Customer Controller Dependencies (B only)

| Dependency | Present | Notes |
|------------|:-------:|-------|
| `CustomerMenu` (Helper) | ✅ | Feature visibility check |
| `SupportTicketService` | ✅ | Core service |
| `StoreCustomerSupportTicketRequest` | ✅ | Customer FormRequest |
| `StoreCustomerReplyRequest` | ✅ | Customer FormRequest |
| `SupportTicket` (Model) | ✅ | Route model binding |
| `SupportTicketReply` (Model) | ✅ | Constants |

---

## 8. VIEW DEPENDENCIES

### 8.1 Layout Dependencies

| Dependency | A: Package | B: Project | C: customer-site | Canonical |
|------------|:----------:|:----------:|:----------------:|:---------:|
| `@extends('admin.layout')` | ✅ | ❌ | ✅ | ✅ |
| `@extends('layouts.admin')` | ❌ | ✅ | ❌ | ❌ |
| `@asset_module('tickets', ...)` | ✅ | ❌ | ❌ | ✅ |
| `<x-module-features ...>` | ❌ | ✅ | ✅ | ✅ |
| `@include('...guide')` | ❌ | ✅ | ✅ | ✅ |

### 8.2 Helper Dependencies

| Helper | A: Package | B: Project | C: customer-site | Canonical |
|--------|:----------:|:----------:|:----------------:|:---------:|
| `Jalali::format()` | ❌ | ✅ | ❌ | ✅ |
| `Jalali::formatFa()` | ❌ | ❌ | ✅ | ✅ |
| `NumberHelper::formatFa()` | ❌ | ✅ | ✅ | ✅ |
| `number_format()` | ✅ | ❌ | ❌ | ❌ |
| `Carbon::parse()` | ❌ | ❌ | ✅ | ❌ (use Eloquent) |
| `method_exists($tickets, 'links')` | ❌ | ❌ | ✅ | ❌ (always paginate) |

### 8.3 Alpine.js Dependencies

| Component | A: Package | B: Project | C: customer-site | Canonical |
|-----------|:----------:|:----------:|:----------------:|:---------:|
| `x-data` filters | ✅ | ✅ | ✅ | ✅ |
| `supportChat()` | ❌ | ✅ | ✅ | ✅ |
| `x-show` client filter | ❌ | ❌ | ✅ | ❌ (server-side) |
| `Setting` model for colors | ❌ | ✅ | ❌ | ✅ |

---

## 9. ROUTE DEPENDENCIES

### 9.1 Middleware Dependencies

| Middleware | A: Package | B: Project | C: customer-site | Canonical |
|------------|:----------:|:----------:|:----------------:|:---------:|
| `web` | ✅ | ✅ | ✅ | ✅ |
| `auth` | ✅ | ✅ | ✅ | ✅ |
| `admin` | ✅ | ✅ | ✅ | ✅ |
| `customer` | ❌ | ✅ | ❌ | ✅ |
| `EnsureModuleAssetsLinked` | ✅ | ❌ | ❌ | ✅ |
| `tenant` | ❌ | ❌ | ❌ | Optional |

### 9.2 Route Parameter Dependencies

| Parameter | A: Package | B: Project | C: customer-site | Canonical |
|-----------|:----------:|:----------:|:----------------:|:---------:|
| `{ticket}` | ✅ | ❌ | ❌ | ✅ |
| `{support_ticket}` | ❌ | ✅ | ❌ | ❌ |
| `{ticketId}` | ❌ | ❌ | ✅ | ❌ |
| `{id}` (API) | ❌ | ✅ | ❌ | ✅ |

---

## 10. EXTERNAL SERVICE DEPENDENCIES

### 10.1 LicenseServerService (B only)

| Dependency | Type | Purpose |
|------------|------|---------|
| `LicenseServerService` | Internal service | Validates license keys against external server |
| HTTP Client | Guzzle | Makes HTTP requests to license server |
| External License API | Third-party | Validates `license_key` + `domain` pairs |

### 10.2 SupportApiClient (C only)

| Dependency | Type | Purpose |
|------------|------|---------|
| `SupportApiClient` | Internal service | HTTP client to platform API |
| HTTP Client | Guzzle | Makes HTTP requests to platform |
| Platform API | Internal API | Consumes `api/support-tickets/*` endpoints |

---

## 11. FILE STORAGE DEPENDENCIES

### 11.1 Upload Paths

| Aspect | A: Package | B: Project | C: customer-site | Canonical |
|--------|:----------:|:----------:|:----------------:|:---------:|
| **Disk** | `public` | `public` | `public` | `public` |
| **Path** | `tickets/Y/m` | `support-tickets/Y/m` | `support-tickets/Y/m` | `tickets/Y/m` |
| **Attachment Struct** | `{name, path, size, mime}` | `{name, path, size, mime}` | `{name, path, url, size, mime}` | `{name, path, size, mime}` |

**Decision:** Use `tickets/Y/m` (package convention) as canonical. The `support-tickets/` path in B and C will be migrated.

---

## 12. CACHE DEPENDENCIES

### 12.1 Cache Keys

| Cache Key | A: Package | B: Project | Canonical |
|-----------|:----------:|:----------:|:---------:|
| `support_ticket_{id}_...` | ❌ | ✅ | ✅ (as `ticket_{id}_...`) |
| `support_ticket_stats` | ❌ | ✅ | ✅ (as `ticket_stats`) |
| TTL | N/A | 900s | 900s |

---

## 13. RUNTIME DEPENDENCY SUMMARY

### 13.1 What Must Be Added to Package (A)

| Category | Items |
|----------|-------|
| **Composer Packages** | `spatie/laravel-activitylog`, `mohammad-fouladgar/laravel-jalali`, `laravel/sanctum`, `spatie/laravel-permission` |
| **Config** | `tickets.sla`, `tickets.grace_days`, `tickets.levels`, `tickets.business_hours` |
| **Contracts** | `TicketServiceInterface`, `TicketRepositoryInterface`, `TicketReplyRepositoryInterface` |
| **Repositories** | `TicketRepository`, `TicketReplyRepository` |
| **Services** | `TicketService` (rewrite), `TicketLicenseService`, `TicketSlaService`, `TicketPortalService` |
| **Controllers** | Admin, API, Customer controllers |
| **FormRequests** | 4 Admin + 3 API + 2 Customer = 9 FormRequest classes |
| **Views** | index, create, show, settings, guide |
| **Exception** | `TicketPortalException` |
| **Lifecycle** | ModuleLifecycleInterface integration |

### 13.2 What Must Be Updated in Project (B)

| Category | Items |
|----------|-------|
| **Routes** | Change `admin/support-tickets` → `admin/tickets` |
| **Views** | Change `@extends('layouts.admin')` → `@extends('admin.layout')` |
| **Config** | Merge `support.*` into `tickets.*` |
| **Models** | Alias or extend package models |

### 13.3 What Must Be Updated in customer-site (C)

| Category | Items |
|----------|-------|
| **Field Names** | `email` → `customer_email`, `name` → `customer_name` |
| **Views** | Update data access patterns, SLA display |
| **API Client** | Update endpoints if changed |

---

## 14. RUNTIME COMPATIBILITY MATRIX

| Runtime Aspect | A: Package | B: Project | C: customer-site | Compatible? |
|----------------|:----------:|:----------:|:----------------:|:-----------:|
| PHP Version | 8.4.16 | 8.4.16 | 8.4.16 | ✅ Same |
| Laravel Version | ^10.0 | ^10.0 | ^10.0 | ✅ Same |
| Tailwind CSS | v3.4.19 | v3.4.19 | v3.4.19 | ✅ Same |
| Alpine.js | 3.15+ | 3.15+ | 3.15+ | ✅ Same |
| Database | MySQL | MySQL | MySQL | ✅ Same |
| Cache Driver | file/redis | file/redis | file/redis | ✅ Same |
| Session Driver | file | file | file | ✅ Same |
| Filesystem Disk | public | public | public | ✅ Same |

---

*End of Shared Runtime Mapping — Ready for TASK 4: Tickets Frontend Canonicalization*
