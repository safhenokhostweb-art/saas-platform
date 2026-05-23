# PHASE 0.5 — Architecture Conflict Resolution / Foundation Stabilization

> **Date:** 2026-05-22
> **Status:** ✅ COMPLETE — Analysis & Strategy Only, No Refactoring
> **Prerequisite:** Phase 0 Complete (docs/PHASE-0-COMPLETE-REPORT.md)

---

## Table of Contents

1. [Tenant Context Unification](#1-tenant-context-unification)
2. [Shared Ownership Rules](#2-shared-ownership-rules)
3. [Shared Package Registry Architecture](#3-shared-package-registry-architecture)
4. [Update Safety Foundation](#4-update-safety-foundation)
5. [Blade Ownership Enforcement](#5-blade-ownership-enforcement)
6. [Module Contract Standard](#6-module-contract-standard)
7. [Anti-Drift Safeguards](#7-anti-drift-safeguards)

---

## 1. Tenant Context Unification

### 1.1 Current State Analysis

#### saas-platform `TenantContext`
- **Namespace:** `App\Services\TenantContext`
- **Tenant Types:** `Reseller` or `Site` (via `TenantResolver`)
- **Resolution Strategy:**
  1. Domain/subdomain → `ResellerDetectionService::fromRequest()`
  2. Request attributes (`current_reseller`, `current_reseller_from_domain`)
- **API Surface:**
  - `current(): ?object` — returns Reseller or Site
  - `set(object $tenant): void`
  - `clear(): void`
  - `type(): ?string` — 'reseller' | 'site' | null
  - `isReseller(): bool`
  - `isSite(): bool`
  - `reseller(): ?Reseller`
  - `site(): ?Site`
  - `id(): ?int`
  - `scopeQueryToTenant($query, string $tenantColumn = 'reseller_id')`
  - `hasTenant(): bool`
- **Middleware:** `SetTenantContext` (clears, resolves from domain, sets, runs request, clears)
- **Middleware:** `EnsureTenantScope` (aborts 403 if no tenant)

#### customer-site `TenantContext`
- **Namespace:** `App\Services\TenantContext`
- **Tenant Type:** `License` (single installation = single tenant)
- **Resolution Strategy:**
  1. `License::current()` → latest license record
  2. `isValid()` check against request host
- **API Surface:**
  - `current(): ?License`
  - `set(?License $license): void`
  - `clear(): void`
  - `domain(): ?string`
  - `hasTenant(): bool`
  - `allowedModules(): array`
  - `hasModule(string $slug): bool`
- **Middleware:** `SetTenantContext` (clears, checks installation lock + licenses table, sets License)

### 1.2 Key Divergences

| Aspect | saas-platform | customer-site | Impact |
|--------|--------------|---------------|--------|
| **Tenant type** | Reseller or Site | License | Different domain concepts |
| **Resolution** | Domain/subdomain + request attributes | License::current() + isValid() | Cannot share resolution logic |
| **API methods** | 11 methods | 7 methods | Different interfaces |
| **Module access** | Via Site->modules() relationship | Via License->modules array | Different data sources |
| **Scope query** | scopeQueryToTenant() | Not present | Different query scoping |
| **Middleware** | SetTenantContext + EnsureTenantScope | SetTenantContext only | Different middleware stacks |

### 1.3 Canonical Ownership Strategy

**saas-platform OWNS:**
- `TenantContext` as the canonical service definition
- `TenantResolver` for domain/subdomain resolution
- `ResellerDetectionService` for white-label detection
- `BelongsToSiteTenant` trait for Site-scoped models
- `BelongsToResellerTenant` trait for Reseller-scoped models
- `SetTenantContext` middleware (canonical implementation)
- `EnsureTenantScope` middleware

**customer-site ADAPTS:**
- `TenantContext` implements the same interface but with License-based resolution
- `SetTenantContext` middleware follows the same pattern but resolves from License
- No need for `TenantResolver` or `ResellerDetectionService` (single-tenant)

### 1.4 Compatibility Layer Strategy

**Phase 1 — Interface Contract (No Code Changes):**
1. Define a `TenantContextContract` interface in saas-platform:
   ```php
   namespace App\Contracts\Services;
   
   interface TenantContextContract
   {
       public function current(): ?object;
       public function set(object $tenant): void;
       public function clear(): void;
       public function hasTenant(): bool;
       public function id(): ?int;
       public function type(): ?string;
   }
   ```

2. Both implementations implicitly implement this contract (duck-typing compatible)

**Phase 2 — Shared Trait (Optional):**
- Extract common logic (clear, hasTenant, id) into a `HasTenantContext` trait
- Each project uses the trait + project-specific resolution

**Phase 3 — Full Unification (Future):**
- Move `TenantContextContract` to a shared package
- Both projects implement the contract
- customer-site adds `scopeQueryToTenant()` for future multi-tenant support

### 1.5 Migration Safety Rules

| Rule | Description |
|------|-------------|
| **NO** | Change the return type of `current()` in either project |
| **NO** | Remove any existing methods from either TenantContext |
| **NO** | Change middleware execution order |
| **NO** | Break auth/session isolation |
| **YES** | Add new methods that don't conflict with existing ones |
| **YES** | Create the interface contract (additive only) |

---

## 2. Shared Ownership Rules

### 2.1 Formal Ownership Matrix

| Component | Owner | customer-site Role |
|-----------|-------|-------------------|
| **TenantContext** | saas-platform (canonical) | Implements contract, License-based |
| **AdminMenu** | saas-platform (canonical) | Extends with site-specific items |
| **Module Model/Catalog** | saas-platform (source of truth) | Consumes via API + config |
| **User Model** | Each project (different domains) | Site users (different from platform admins) |
| **Form Model** | Shared (extract to package) | Tenant-specific overrides |
| **Page Model** | Shared (extract to package) | Tenant-specific overrides |
| **Menu Model** | Shared (extract to package) | Tenant-specific overrides |
| **Captcha** | Shared (extract to package) | Tenant-specific settings |
| **Activity Log** | Shared (extract to package) | Tenant-specific logging |
| **Settings** | Shared (extract to package) | Tenant-specific settings |
| **Persian Components** | Shared (extract to package) | Direct reuse |
| **Helpers (Jalali, Number)** | Shared (extract to package) | Direct reuse |
| **Validation Rules** | Shared (extract to package) | Direct reuse |
| **Blade Components** | Shared (extract to package) | Tenant-specific overrides |
| **API Routes** | saas-platform (provider) | Consumer (HTTP client) |
| **Middleware** | Each project (different stacks) | Project-specific |
| **Config** | Each project (different needs) | Project-specific |

### 2.2 Ownership Principles

1. **saas-platform owns shared modules/UI/services** — Any component that exists in both projects must be owned by saas-platform as the canonical source
2. **customer-site owns tenant overrides/configs/branding** — Site-specific customizations, theme, branding, and local config
3. **No direct model coupling across projects** — customer-site never references saas-platform models directly; always via API
4. **API is the contract** — All cross-project communication goes through versioned APIs
5. **Shared code = shared package** — Any code duplicated across projects must be extracted to a shared package before any new development

### 2.3 Decision Tree for New Code

```
Is this code needed in both projects?
├── YES → Is it a shared component (UI, helper, validation)?
│   ├── YES → Must go in shared package FIRST
│   └── NO → Is it a model/service?
│       ├── YES → saas-platform owns canonical version
│       │         customer-site extends/adapts via contract
│       └── NO → Evaluate ownership per matrix
└── NO → Project-specific, no restrictions
```

---

## 3. Shared Package Registry Architecture

### 3.1 Loading Structure

```
packages/
└── saas-platform/
    └── shared/
        ├── src/
        │   ├── Contracts/
        │   │   ├── Services/
        │   │   │   └── TenantContextContract.php
        │   │   └── Repositories/
        │   │       └── BaseRepositoryContract.php
        │   ├── Traits/
        │   │   ├── HasUuid.php
        │   │   ├── HasUserStamps.php
        │   │   └── ApiResponse.php
        │   ├── Helpers/
        │   │   ├── Jalali.php
        │   │   └── NumberHelper.php
        │   ├── Rules/
        │   │   └── IranianMobile.php
        │   ├── View/
        │   │   └── Components/
        │   │       ├── PersianCaptchaChallenge.php
        │   │       ├── PersianCaptchaMath.php
        │   │       └── PersianDateInput.php
        │   └── ServiceProvider.php
        ├── resources/
        │   └── views/
        │       └── components/
        │           ├── persian-captcha-challenge.blade.php
        │           ├── persian-captcha-math.blade.php
        │           └── persian-date-input.blade.php
        ├── composer.json
        └── README.md
```

### 3.2 Registration Contracts

```php
// packages/saas-platform/shared/src/Contracts/Services/TenantContextContract.php
namespace SaasPlatform\Shared\Contracts\Services;

interface TenantContextContract
{
    public function current(): ?object;
    public function set(object $tenant): void;
    public function clear(): void;
    public function hasTenant(): bool;
    public function id(): ?int;
    public function type(): ?string;
}
```

```php
// packages/saas-platform/shared/src/Contracts/Repositories/BaseRepositoryContract.php
namespace SaasPlatform\Shared\Contracts\Repositories;

interface BaseRepositoryContract
{
    public function getAll(array $columns = ['*']);
    public function getById(int $id, array $columns = ['*']);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id);
}
```

### 3.3 Service Provider Strategy

```php
// packages/saas-platform/shared/src/ServiceProvider.php
namespace SaasPlatform\Shared;

use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind contracts to implementations (project-specific)
        $this->app->bind(
            \SaasPlatform\Shared\Contracts\Services\TenantContextContract::class,
            \App\Services\TenantContext::class  // resolved per-project
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shared');
        $this->loadViewComponentsAs('shared', [
            View\Components\PersianCaptchaChallenge::class,
            View\Components\PersianCaptchaMath::class,
            View\Components\PersianDateInput::class,
        ]);
    }
}
```

### 3.4 Namespace Standards

| Component | Namespace |
|-----------|-----------|
| Contracts | `SaasPlatform\Shared\Contracts\Services\*` |
| Traits | `SaasPlatform\Shared\Traits\*` |
| Helpers | `SaasPlatform\Shared\Helpers\*` |
| Rules | `SaasPlatform\Shared\Rules\*` |
| View Components | `SaasPlatform\Shared\View\Components\*` |
| Models (future) | `SaasPlatform\Shared\Models\*` |

### 3.5 composer.json Structure

```json
{
    "name": "saas-platform/shared",
    "description": "Shared modules, contracts, traits, and components for SaaS Platform ecosystem",
    "type": "library",
    "autoload": {
        "psr-4": {
            "SaasPlatform\\Shared\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "SaasPlatform\\Shared\\SharedServiceProvider"
            ]
        }
    },
    "require": {
        "php": "^8.2",
        "illuminate/support": "^11.0"
    }
}
```

### 3.6 Integration Path

**Phase 1 — Local Path Repository (Immediate):**
```json
// Both projects' composer.json
"repositories": [
    {
        "type": "path",
        "url": "../packages/saas-platform/shared"
    }
],
"require": {
    "saas-platform/shared": "@dev"
}
```

**Phase 2 — Private Packagist/Satis (Short-term):**
- Set up Satis or Private Packagist
- Publish shared package
- Both projects require from private repository

**Phase 3 — Public Packagist (Long-term):**
- Open-source shared components
- Versioned releases with semantic versioning

---

## 4. Update Safety Foundation

### 4.1 Current State

| Aspect | Status | Risk |
|--------|--------|------|
| API versioning | ❌ No version prefix | Medium |
| Backward compatibility | ❌ No guarantees | Medium |
| Offline fallback | ❌ No graceful degradation | Medium |
| Update rollback | ❌ No mechanism | High |
| Update staging | ❌ No staging environment | High |

### 4.2 Rollback Hooks Design

```php
// packages/saas-platform/shared/src/Contracts/Services/UpdateContract.php
namespace SaasPlatform\Shared\Contracts\Services;

interface UpdateContract
{
    /**
     * Create a pre-update snapshot for rollback.
     */
    public function createSnapshot(string $moduleSlug, string $version): string;

    /**
     * Rollback to a previous snapshot.
     */
    public function rollback(string $snapshotId): bool;

    /**
     * List available snapshots for a module.
     */
    public function listSnapshots(string $moduleSlug): array;

    /**
     * Prune old snapshots (keep last N).
     */
    public function pruneSnapshots(string $moduleSlug, int $keep = 3): int;
}
```

**customer-site Implementation:**
```php
// app/Services/UpdateRollbackService.php
namespace App\Services;

class UpdateRollbackService implements \SaasPlatform\Shared\Contracts\Services\UpdateContract
{
    public function createSnapshot(string $moduleSlug, string $version): string
    {
        // 1. Backup current module files to storage/app/backups/{module}/{version}_{timestamp}/
        // 2. Record current DB schema state
        // 3. Return snapshot ID
    }

    public function rollback(string $snapshotId): bool
    {
        // 1. Restore files from backup
        // 2. Run down migration if applicable
        // 3. Update ModuleVersion record
    }
}
```

### 4.3 Module Version Contracts

```json
// module.json (proposed schema for each module)
{
    "name": "forms",
    "version": "2.1.0",
    "min_platform_version": "1.0.0",
    "max_platform_version": "2.0.0",
    "dependencies": {
        "captcha": "^1.0",
        "laravel/framework": "^11.0"
    },
    "compatibility": {
        "php": "^8.2",
        "database": ["mysql 8.0+", "mariadb 10.6+"]
    },
    "migrations": {
        "type": "versioned",
        "rollback_supported": true
    },
    "permissions": [
        "forms.manage",
        "forms.submissions.view"
    ]
}
```

### 4.4 Compatibility Validation

```php
// packages/saas-platform/shared/src/Services/ModuleCompatibilityValidator.php
namespace SaasPlatform\Shared\Services;

class ModuleCompatibilityValidator
{
    /**
     * Validate module compatibility before update.
     *
     * @throws IncompatibleModuleException
     */
    public function validate(array $moduleManifest, string $currentVersion): void
    {
        // 1. Check PHP version compatibility
        // 2. Check platform version compatibility
        // 3. Check dependency versions
        // 4. Check database compatibility
        // 5. Validate migration path (no breaking changes)
    }

    /**
     * Check if update is safe (non-breaking).
     */
    public function isSafeUpdate(string $fromVersion, string $toVersion): bool
    {
        // Semantic versioning check:
        // - Same major version = safe (patch/minor)
        // - Major version bump = breaking (requires staging)
        return version_compare($fromVersion, $toVersion, '>=')
            || explode('.', $fromVersion)[0] === explode('.', $toVersion)[0];
    }
}
```

### 4.5 Semantic Versioning Rules

| Version Bump | Meaning | Update Safety |
|-------------|---------|---------------|
| **MAJOR** (x.0.0) | Breaking changes | Requires staging, manual approval |
| **MINOR** (0.x.0) | New features, backward compatible | Auto-eligible, notify admin |
| **PATCH** (0.0.x) | Bug fixes, backward compatible | Auto-apply, log only |

### 4.6 API Versioning Strategy

**Immediate (Phase 0.5):**
```php
// Add version prefix to all API routes
Route::prefix('v1')->group(function () {
    // existing routes...
});
```

**Backward Compatibility:**
```php
// Support both old and new routes during transition
Route::prefix('v1')->group(function () {
    Route::post('license/validate', ...);
    Route::get('updates/check', ...);
});

// Legacy routes (deprecated, redirect to v1)
Route::post('license/validate', function () {
    return redirect()->route('api.v1.license.validate');
});
```

---

## 5. Blade Ownership Enforcement

### 5.1 Shared Blade Ownership Rules

| Component | Owner | customer-site Access |
|-----------|-------|---------------------|
| `components/persian-captcha-challenge` | Shared package | `@include('shared::components.persian-captcha-challenge')` |
| `components/persian-captcha-math` | Shared package | `@include('shared::components.persian-captcha-math')` |
| `components/persian-date-input` | Shared package | `@include('shared::components.persian-date-input')` |
| `partials/auth-captcha` | Shared package | `@include('shared::partials.auth-captcha')` |
| `partials/guide-widget` | Shared package | `@include('shared::partials.guide-widget')` |
| `partials/vazirmatn-font-faces-inline` | Shared package | `@include('shared::partials.vazirmatn-font-faces-inline')` |
| `layouts/auth-login` | Each project | Project-specific (different branding) |
| `layouts/auth` | Each project | Project-specific (different branding) |
| `admin/forms/*` | Shared (future) | Tenant-specific overrides |
| `admin/menus/*` | Shared (future) | Tenant-specific overrides |
| `admin/pages/*` | Shared (future) | Tenant-specific overrides |

### 5.2 Override-Safe Views Pattern

```blade
{{-- resources/views/vendor/shared/components/persian-captcha-challenge.blade.php --}}
{{-- This file overrides the shared package view --}}
{{-- Only create this file if you need tenant-specific customization --}}
<x-shared::persian-captcha-challenge />
```

**Override Resolution Order:**
1. `resources/views/vendor/shared/` — Project-specific override
2. `vendor/saas-platform/shared/resources/views/` — Package default

### 5.3 Tenant-Specific Override Rules

| Rule | Description |
|------|-------------|
| **NO** | Edit shared package views directly |
| **NO** | Copy-paste shared views into project |
| **YES** | Create override in `resources/views/vendor/shared/` |
| **YES** | Use `@include('shared::*')` for shared components |
| **YES** | Use `@extends('shared::layouts.*')` for shared layouts |

### 5.4 UI Kit Ownership

**saas-platform OWNS:**
- Base component definitions (Alpine.js + Tailwind)
- Component documentation
- Component tests
- Accessibility compliance

**customer-site INHERITS:**
- All base components via shared package
- Can override styling via CSS variables
- Can extend components with `@props` and slots

---

## 6. Module Contract Standard

### 6.1 module.json Schema (Canonical)

```json
{
    "$schema": "https://saas-platform.example.com/module-schema.json",
    "name": "string (required) — unique slug, e.g. 'forms'",
    "version": "string (required) — semver, e.g. '1.0.0'",
    "title": "string (required) — Persian display name",
    "description": "string — Persian description",
    "type": "enum: 'core' | 'paid' | 'internal'",
    "distribution_mode": "enum: 'free_assignable' | 'paid_marketplace' | 'license_only' | 'disabled' | 'retired'",

    "dependencies": {
        "module_slugs": ["string"],
        "php": "string — semver constraint, e.g. '^8.2'",
        "laravel": "string — semver constraint, e.g. '^11.0'",
        "packages": {
            "composer_package_name": "string — version constraint"
        }
    },

    "compatibility": {
        "min_platform_version": "string — semver",
        "max_platform_version": "string — semver",
        "database": ["string — e.g. 'mysql 8.0+'"],
        "browser": ["string — e.g. 'chrome 90+'"]
    },

    "update": {
        "rollback_supported": "boolean",
        "migration_type": "enum: 'versioned' | 'idempotent' | 'none'",
        "staging_required": "boolean — true for major version bumps"
    },

    "permissions": {
        "admin": ["string — permission slugs"],
        "api": ["string — API permission slugs"]
    },

    "views": {
        "admin": ["string — view paths"],
        "front": ["string — view paths"]
    },

    "routes": {
        "web": ["string — route name prefixes"],
        "api": ["string — API route prefixes"]
    },

    "config": {
        "default": "object — default configuration values",
        "schema": "object — JSON schema for configuration validation"
    }
}
```

### 6.2 Dependency Contract

```php
// packages/saas-platform/shared/src/Contracts/Modules/ModuleDependencyContract.php
namespace SaasPlatform\Shared\Contracts\Modules;

interface ModuleDependencyContract
{
    /**
     * Get all dependencies for this module.
     */
    public function getDependencies(): array;

    /**
     * Check if all dependencies are satisfied.
     */
    public function checkDependencies(): array; // returns [satisfied => bool, missing => []]

    /**
     * Get the dependency graph for installation order.
     */
    public function getDependencyGraph(): array; // returns ordered list of module slugs
}
```

### 6.3 Update Contract

```php
// packages/saas-platform/shared/src/Contracts/Modules/ModuleUpdateContract.php
namespace SaasPlatform\Shared\Contracts\Modules;

interface ModuleUpdateContract
{
    /**
     * Check if update is available.
     */
    public function hasUpdate(): bool;

    /**
     * Get available update versions.
     */
    public function getAvailableUpdates(): array;

    /**
     * Validate update compatibility.
     */
    public function validateUpdate(string $targetVersion): array;

    /**
     * Apply update.
     */
    public function applyUpdate(string $targetVersion): bool;
}
```

### 6.4 Override Contract

```php
// packages/saas-platform/shared/src/Contracts/Modules/ModuleOverrideContract.php
namespace SaasPlatform\Shared\Contracts\Modules;

interface ModuleOverrideContract
{
    /**
     * Get list of overrideable views.
     */
    public function getOverrideableViews(): array;

    /**
     * Get list of overrideable config keys.
     */
    public function getOverrideableConfig(): array;

    /**
     * Check if a specific view is overridden.
     */
    public function isViewOverridden(string $view): bool;
}
```

### 6.5 Migration Contract

```php
// packages/saas-platform/shared/src/Contracts/Modules/ModuleMigrationContract.php
namespace SaasPlatform\Shared\Contracts\Modules;

interface ModuleMigrationContract
{
    /**
     * Run module migrations.
     */
    public function migrate(): bool;

    /**
     * Rollback module migrations.
     */
    public function rollback(): bool;

    /**
     * Get current migration version.
     */
    public function getCurrentVersion(): string;

    /**
     * Check if migration is needed.
     */
    public function needsMigration(): bool;
}
```

### 6.6 Permissions Contract

```php
// packages/saas-platform/shared/src/Contracts/Modules/ModulePermissionContract.php
namespace SaasPlatform\Shared\Contracts\Modules;

interface ModulePermissionContract
{
    /**
     * Get all permissions defined by this module.
     */
    public function getPermissions(): array;

    /**
     * Register permissions with the permission system.
     */
    public function registerPermissions(): void;

    /**
     * Check if a user has a specific module permission.
     */
    public function hasPermission($user, string $permission): bool;
}
```

---

## 7. Anti-Drift Safeguards

### 7.1 Duplicate Blade Prevention

**Rule:** Any Blade file that exists in both projects with the same relative path must be flagged.

```php
// Automated check (run as CI step or artisan command)
// packages/saas-platform/shared/src/Commands/CheckBladeDriftCommand.php
namespace SaasPlatform\Shared\Commands;

use Illuminate\Console\Command;

class CheckBladeDriftCommand extends Command
{
    protected $signature = 'shared:check-blade-drift';

    public function handle(): int
    {
        $saasViews = $this->getViewFiles(base_path('../saas-platform/resources/views'));
        $customerViews = $this->getViewFiles(base_path('../customer-site/resources/views'));

        $duplicates = array_intersect_key($saasViews, $customerViews);

        foreach ($duplicates as $path => $files) {
            $this->warn("DUPLICATE BLADE: {$path}");
            $this->line("  saas-platform: {$files[0]}");
            $this->line("  customer-site: {$files[1]}");
        }

        return count($duplicates) > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function getViewFiles(string $basePath): array
    {
        // Recursively find all .blade.php files and return [relativePath => absolutePath]
    }
}
```

### 7.2 Duplicate Controller Prevention

**Rule:** Any controller class with the same name in both projects must be flagged.

```php
// Automated check
$saasControllers = [
    'FormController', 'MenuController', 'PageController',
    'CaptchaController', 'SettingController', 'ActivityLogController',
];

$customerControllers = [
    'FormController', 'MenuController', 'PageController',
    'CaptchaController', 'SettingController', 'AdminActivityLogController',
];

$duplicates = array_intersect($saasControllers, $customerControllers);
// → ['FormController', 'MenuController', 'PageController', 'CaptchaController', 'SettingController']
```

### 7.3 Duplicate Business Logic Prevention

**Rule:** Any service/helper class with the same name and similar implementation in both projects must be extracted to shared package.

**Detection Strategy:**
1. Same class name in both projects → flag
2. Same method signatures → flag
3. Same file path relative to project root → flag
4. Same namespace pattern → flag

### 7.4 Parallel Frontend Development Prevention

**Rule:** No new Blade files should be created in customer-site without first checking if a shared version exists in saas-platform.

**Workflow:**
1. Developer wants to create `admin/forms/index.blade.php` in customer-site
2. Check if `admin/forms/index.blade.php` exists in saas-platform
3. If yes → use shared package or create override
4. If no → create in saas-platform first, then consume via shared package

### 7.5 Enforcement Mechanisms

| Mechanism | Description | Trigger |
|-----------|-------------|---------|
| **CI Check** | `shared:check-blade-drift` command | Pre-commit hook / CI pipeline |
| **CI Check** | `shared:check-controller-drift` command | Pre-commit hook / CI pipeline |
| **Artisan Command** | `shared:check-all-drift` — runs all checks | Manual / scheduled |
| **Git Hook** | Pre-commit check for new Blade files | `git commit` |
| **Code Review Rule** | Any new Blade in customer-site must reference shared source | PR review checklist |
| **Architecture Decision Record** | Any deviation from ownership matrix must be documented | ADR creation |

### 7.6 Anti-Drift Checklist for PRs

```markdown
## Anti-Drift Checklist

- [ ] No new Blade files duplicate existing ones in the other project
- [ ] No new controllers duplicate existing ones in the other project
- [ ] No new services/helpers duplicate existing ones in the other project
- [ ] Shared components use `@include('shared::*')` not copy-paste
- [ ] New features follow the ownership matrix
- [ ] API changes are backward compatible
- [ ] Module contracts are followed for module changes
```

---

## Summary of Expected Outputs

| # | Output | Status | Location |
|---|--------|--------|----------|
| 1 | **Unified ownership strategy** | ✅ Defined | Section 2 — Shared Ownership Rules |
| 2 | **Canonical TenantContext strategy** | ✅ Defined | Section 1 — Tenant Context Unification |
| 3 | **Shared package architecture** | ✅ Defined | Section 3 — Shared Package Registry |
| 4 | **Module contract specification** | ✅ Defined | Section 6 — Module Contract Standard |
| 5 | **Rollback/versioning strategy** | ✅ Defined | Section 4 — Update Safety Foundation |
| 6 | **Shared Blade ownership strategy** | ✅ Defined | Section 5 — Blade Ownership Enforcement |
| 7 | **Anti-drift enforcement plan** | ✅ Defined | Section 7 — Anti-Drift Safeguards |
| 8 | **Safe migration prerequisites** | ✅ Defined | All sections (additive-only approach) |
| 9 | **Shared namespace standards** | ✅ Defined | Section 3.4 — Namespace Standards |
| 10 | **Compatibility layer plan** | ✅ Defined | Section 1.4 — Compatibility Layer Strategy |

---

## Next Steps (Phase 1)

1. **Create shared package repository** — Set up `packages/saas-platform/shared/` with initial contracts and traits
2. **Add API versioning** — Prefix all API routes with `/v1/`
3. **Create drift detection commands** — Implement `shared:check-blade-drift` and `shared:check-controller-drift`
4. **Extract Persian components** — Move captcha, date-input, font faces to shared package
5. **Extract helpers** — Move Jalali, NumberHelper, IranianMobile to shared package
6. **Begin Forms module extraction** — Forms is the simplest shared module candidate

---

*End of Phase 0.5 Report — Ready for Phase 1 Implementation*
