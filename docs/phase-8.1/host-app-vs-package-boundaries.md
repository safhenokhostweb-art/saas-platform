# Host App vs Package Boundaries

**Phase 8.1 — Canonical Boundary Map**  
**Date:** 2026-05-26

This document defines what belongs in the host application vs what belongs in packages, and how the two communicate.

---

## The Rule

> Packages depend on contracts. The host app provides concrete implementations.  
> Packages NEVER import `App\` namespace. The host app CAN import package classes.

---

## Boundary Map

```
┌─────────────────────────────────────────────────────────────────┐
│                         HOST APPLICATION                        │
│  (app/, config/, routes/, resources/views/admin/)              │
│                                                                 │
│  app/Models/          ← Eloquent models (Site, User, Module…)  │
│  app/Services/        ← Concrete implementations               │
│  app/Adapters/        ← Implements package contracts           │
│  app/Providers/       ← InfrastructureServiceProvider          │
│                              │                                  │
│                              │ binds contracts to adapters      │
│                              ▼                                  │
│         ┌────────────────────────────────────────┐             │
│         │    SiteRepositoryInterface → Adapter   │             │
│         │    AuthUserResolverInterface → Adapter │             │
│         │    ModuleLicenseServiceInterface → …   │             │
│         │    DateFormatterInterface → …          │             │
│         │    CaptchaServiceInterface → …         │             │
│         │    ActivityLoggerInterface → …         │             │
│         │    TenantContextContract → TenantCtx   │             │
│         └────────────────────────────────────────┘             │
└──────────────────────────────┬──────────────────────────────────┘
                               │ contracts only
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PACKAGES (saas-platform/*)                   │
│                                                                 │
│  core-utils/        ← Shared infrastructure                    │
│    Contracts/       ← All interface definitions                 │
│    Support/Jalali   ← Jalali date implementation                │
│    Http/BaseController ← Shared base controller                │
│    Services/        ← Context, compatibility, monitoring       │
│    Traits/          ← HasUserStamps (via AuthUserResolver)     │
│                                                                 │
│  modules/forms/     ← Forms module                             │
│    depends only on: TenantContextContract, SiteRepositoryInterface,
│                     CaptchaServiceInterface, ModuleLicenseServiceInterface,
│                     DateFormatterInterface, ActivityLoggerInterface
│                                                                 │
│  modules/tickets/   ← Tickets module                           │
│    depends only on: TenantContextContract, SiteRepositoryInterface,
│                     AuthUserResolverInterface
└─────────────────────────────────────────────────────────────────┘
```

---

## What Lives Where

### Host App Owns

| Concern | Location | Reason |
|---|---|---|
| Eloquent models | `app/Models/` | Coupled to DB schema, migrations, host config |
| Authentication logic | `app/Services/AuthService` etc. | Host-specific guard configuration |
| License/entitlement logic | `app/Services/ModuleLicenseService` | Coupled to `Module`, `ModuleLicense` models |
| Jalali proxy | `app/Helpers/Jalali.php` | Thin proxy; delegates to `core-utils` |
| TenantContext | `app/Services/TenantContext.php` | Implements `TenantContextContract` |
| Infrastructure wiring | `app/Providers/InfrastructureServiceProvider.php` | Single place for contract → adapter bindings |
| Admin views | `resources/views/admin/` | Host layout, host branding |
| Route registration (API/web) | `routes/api.php`, `routes/web.php` | Host URL structure decisions |

### Packages Own

| Concern | Location | Reason |
|---|---|---|
| Contract definitions | `core-utils/src/Contracts/` | Single source of truth for all interfaces |
| Shared Blade components | `core-utils/resources/views/components/` | Reusable across all modules |
| Jalali implementation | `core-utils/src/Support/Jalali.php` | Used by both forms and tickets packages |
| Base controller | `core-utils/src/Http/BaseController.php` | No `App\` dependency |
| Forms business logic | `modules/forms/src/Services/` | Self-contained via contracts |
| Ticket business logic | `modules/tickets/src/Services/` | Self-contained via contracts |
| Module views | `modules/*/resources/views/` | Publishable, overridable by host |
| Module configs | `modules/*/config/` | Merged with defaults; host can override |
| Module routes | `modules/*/Routes/` | Loaded by provider; host can override |
| Module migrations | `modules/*/Database/Migrations/` | Published to host for running |

---

## The Adapter Pattern

Every host-app concern that a package needs is expressed as a contract in `core-utils/Contracts/` and implemented in `app/Adapters/`:

```
Package needs         Contract                    Host Adapter
─────────────────────────────────────────────────────────────────
Site list/find    →   SiteRepositoryInterface  →  SiteRepositoryAdapter
Auth user         →   AuthUserResolverInterface →  AuthUserResolverAdapter
Module license    →   ModuleLicenseServiceInterface → ModuleLicenseAdapter
Jalali dates      →   DateFormatterInterface   →  JalaliDateFormatterAdapter
Captcha           →   CaptchaServiceInterface  →  CaptchaServiceAdapter
Activity logging  →   ActivityLoggerInterface  →  ActivityLoggerAdapter
Tenant context    →   TenantContextContract    →  App\Services\TenantContext
```

---

## Provider Registration Order

This is critical. `InfrastructureServiceProvider` MUST register before any package provider:

```php
// config/app.php
'providers' => [
    App\Providers\InfrastructureServiceProvider::class,  // ← FIRST
    App\Providers\AppServiceProvider::class,
    // ...
    // Package providers are auto-discovered via composer.json
]
```

If `InfrastructureServiceProvider` loads after package providers, the package `register()` methods will try to bind services that depend on unbound contracts, causing `BindingResolutionException`.

---

## Invariants

These must never be violated:

1. **No `App\` import in any package PHP file** — enforced by `forms:validate-isolation`, `tickets:validate-isolation`, `package:validate-distribution`.

2. **No `App\` import in `core-utils`** — core-utils is the lowest layer; it defines contracts but never depends on host implementations.

3. **`InfrastructureServiceProvider` is always first** — it binds contracts before any downstream provider needs them.

4. **Adapters are thin** — an adapter wraps one host service. Business logic belongs in the host service, not the adapter.

5. **Contracts live in `core-utils`** — module-specific contracts live in that module's `Contracts/` directory. Cross-module contracts live in `core-utils/Contracts/`.

6. **Package configs have defaults** — `mergeConfigFrom()` means packages always have a working config even if the host never published/overrode it.

---

## Adding a New Module

1. Create `packages/saas-platform/modules/<name>/`
2. Extend only from `core-utils` contracts (no `App\` imports)
3. If you need a new host concern, add a contract to `core-utils/Contracts/Services/`
4. Add the adapter in `app/Adapters/`
5. Bind it in `InfrastructureServiceProvider`
6. Create `<name>:validate-isolation` command mirroring `FormsIsolationValidationService`

---

## Validation Commands

```bash
# Per-module isolation check
php artisan forms:validate-isolation --strict
php artisan tickets:validate-isolation --strict

# Full cross-module audit with scoring
php artisan package:validate-distribution
php artisan package:validate-distribution --strict --fail-on-warning --json

# Runtime authority checks (Phase 7.x)
php artisan tickets:validate-runtime-authority
php artisan validate:tenant-runtime
```
