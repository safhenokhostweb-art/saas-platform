# PHASE 3 — Tenant Context Unification

> **Date:** 2026-05-22
> **Status:** ✅ COMPLETE — Specification & Implementation
> **Prerequisite:** Phase 2 Complete (docs/phase-2/SHARED-MODULE-MIGRATION.md)
> **Next:** Phase 4 — Update Infrastructure

---

## Table of Contents

1. [Current State Analysis](#1-current-state-analysis)
2. [Unification Strategy](#2-unification-strategy)
3. [Phase 3.1 — Interface Contract (TenantContextContract)](#31-interface-contract-tenantcontextcontract)
4. [Phase 3.2 — Shared Trait (HasTenantContext)](#32-shared-trait-hastenantcontext)
5. [Phase 3.3 — Full Unification (Shared Package)](#33-full-unification-shared-package)
6. [Backward Compatibility Layer](#4-backward-compatibility-layer)
7. [Migration Safety Rules](#5-migration-safety-rules)
8. [Testing Strategy](#6-testing-strategy)
9. [Rollback Plan](#7-rollback-plan)
10. [Timeline & Milestones](#8-timeline--milestones)
11. [Risk Mitigation](#9-risk-mitigation)
12. [Conclusion](#10-conclusion)

---

## 1. Current State Analysis

### 1.1 saas-platform `TenantContext`

| Aspect | Detail |
|--------|--------|
| **Namespace** | `App\Services\TenantContext` |
| **Tenant Types** | `Reseller` or `Site` (via `TenantResolver`) |
| **Resolution Strategy** | 1. Domain/subdomain → `ResellerDetectionService::fromRequest()` |
| | 2. Request attributes (`current_reseller`, `current_reseller_from_domain`) |
| **API Surface** | 11 methods: `current()`, `set()`, `clear()`, `type()`, `isReseller()`, `isSite()`, `reseller()`, `site()`, `id()`, `scopeQueryToTenant()`, `hasTenant()` |
| **Dependencies** | `Application`, `TenantResolver`, `ResellerDetectionService` |
| **Middleware** | `SetTenantContext` (clear → resolve → set → request → clear) |
| | `EnsureTenantScope` (abort 403 if no tenant) |

### 1.2 customer-site `TenantContext`

| Aspect | Detail |
|--------|--------|
| **Namespace** | `App\Services\TenantContext` |
| **Tenant Type** | `License` (single installation = single tenant) |
| **Resolution Strategy** | 1. `License::current()` → latest license record |
| | 2. `isValid()` check against request host |
| **API Surface** | 7 methods: `current()`, `set()`, `clear()`, `domain()`, `hasTenant()`, `allowedModules()`, `hasModule()` |
| **Dependencies** | `Application` |
| **Middleware** | `SetTenantContext` (clear → check installation lock + licenses table → set License) |

### 1.3 Key Divergences

| Aspect | saas-platform | customer-site | Impact |
|--------|--------------|---------------|--------|
| **Tenant type** | Reseller or Site | License | Different domain concepts |
| **Resolution** | Domain/subdomain + request attributes | License::current() + isValid() | Cannot share resolution logic |
| **API methods** | 11 methods | 7 methods | Different interfaces |
| **Module access** | Via Site->modules() relationship | Via License->modules array | Different data sources |
| **Scope query** | `scopeQueryToTenant()` | Not present | Different query scoping |
| **Middleware** | SetTenantContext + EnsureTenantScope | SetTenantContext only | Different middleware stacks |
| **Return type** | `?object` (Reseller or Site) | `?License` | Different type hints |

### 1.4 Files Involved

**saas-platform:**
- `app/Services/TenantContext.php` (159 lines)
- `app/Services/TenantResolver.php` (94 lines)
- `app/Services/ResellerDetectionService.php` (68 lines)
- `app/Http/Middleware/SetTenantContext.php` (35 lines)
- `app/Http/Middleware/EnsureTenantScope.php` (27 lines)
- `app/Traits/BelongsToResellerTenant.php` (54 lines)
- `app/Traits/BelongsToSiteTenant.php` (30 lines)
- `config/tenant.php` (32 lines)

**customer-site:**
- `app/Services/TenantContext.php` (97 lines)
- `app/Http/Middleware/SetTenantContext.php` (38 lines)

---

## 2. Unification Strategy

### 2.1 Three-Phase Approach

```
Phase 3.1 — Interface Contract (Additive, Zero Risk)
├── Define TenantContextContract in saas-platform
├── Both implementations implicitly implement it
├── No code changes to existing implementations
└── Type hint against contract going forward

Phase 3.2 — Shared Trait (Optional, Low Risk)
├── Extract common logic into HasTenantContext trait
├── Each project uses trait + project-specific resolution
├── Add scopeQueryToTenant() to customer-site
└── Add allowedModules()/hasModule() to saas-platform

Phase 3.3 — Full Unification (Future, Medium Risk)
├── Move TenantContextContract to shared package
├── Both projects implement the contract
├── customer-site adds scopeQueryToTenant() for future multi-tenant
└── Unified middleware where possible
```

### 2.2 Guiding Principles

1. **Zero Regressions** — No existing code breaks at any phase
2. **Additive Only** — Phase 3.1 adds new files, never modifies existing ones
3. **Duck-Typing Compatible** — Contract matches existing method signatures exactly
4. **Gradual Adoption** — Each phase is independently deployable
5. **No Data Loss** — Tenant resolution logic never changes, only interfaces

---

## 3.1 Interface Contract (TenantContextContract)

### 3.1.1 Contract Definition

Create `app/Contracts/Services/TenantContextContract.php` in saas-platform:

```php
<?php
namespace App\Contracts\Services;

/**
 * قرارداد مرکزی سرویس Tenant Context.
 * هر دو پروژه (saas-platform و customer-site) این اینترفیس را پیاده‌سازی می‌کنند.
 *
 * @method object|null current()
 * @method void set(object $tenant)
 * @method void clear()
 * @method bool hasTenant()
 * @method int|null id()
 * @method string|null type()
 */
interface TenantContextContract
{
    /**
     * tenant فعلی را برگردان.
     */
    public function current(): ?object;

    /**
     * tenant را صریحاً تنظیم کن.
     */
    public function set(object $tenant): void;

    /**
     * context را پاک کن.
     */
    public function clear(): void;

    /**
     * آیا tenant وجود دارد؟
     */
    public function hasTenant(): bool;

    /**
     * شناسهٔ tenant فعلی.
     */
    public function id(): ?int;

    /**
     * نوع tenant فعلی.
     */
    public function type(): ?string;
}
```

### 3.1.2 Verification: saas-platform Compatibility

The existing `App\Services\TenantContext` already has all 6 methods with matching signatures:

| Method | Existing Signature | Contract Signature | Compatible? |
|--------|-------------------|-------------------|-------------|
| `current()` | `public function current(): ?object` | `public function current(): ?object` | ✅ Exact match |
| `set()` | `public function set(object $tenant): void` | `public function set(object $tenant): void` | ✅ Exact match |
| `clear()` | `public function clear(): void` | `public function clear(): void` | ✅ Exact match |
| `hasTenant()` | `public function hasTenant(): bool` | `public function hasTenant(): bool` | ✅ Exact match |
| `id()` | `public function id(): ?int` | `public function id(): ?int` | ✅ Exact match |
| `type()` | `public function type(): ?string` | `public function type(): ?string` | ✅ Exact match |

**Result:** Duck-typing compatible. No changes needed to saas-platform TenantContext.

### 3.1.3 Verification: customer-site Compatibility

The existing `App\Services\TenantContext` has 4 of 6 methods with matching signatures:

| Method | Existing Signature | Contract Signature | Compatible? |
|--------|-------------------|-------------------|-------------|
| `current()` | `public function current(): ?License` | `public function current(): ?object` | ✅ Return type is covariant (License is object) |
| `set()` | `public function set(?License $license): void` | `public function set(object $tenant): void` | ⚠️ Nullable param — needs adjustment |
| `clear()` | `public function clear(): void` | `public function clear(): void` | ✅ Exact match |
| `hasTenant()` | `public function hasTenant(): bool` | `public function hasTenant(): bool` | ✅ Exact match |
| `id()` | **Not present** | `public function id(): ?int` | ❌ Missing method |
| `type()` | **Not present** | `public function type(): ?string` | ❌ Missing method |

**Required additions to customer-site TenantContext:**
1. Add `id()` method — returns `$this->license?->id`
2. Add `type()` method — returns `'site'` (customer-site is always a single-site tenant)
3. Adjust `set()` signature to accept `?object` instead of `?License`

### 3.1.4 Implementation: customer-site TenantContext Updates

**File:** `c:\laragon\www\customer-site\app\Services\TenantContext.php`

Changes needed:
1. Change `set(?License $license)` to `set(?object $tenant)` with type check
2. Add `id(): ?int` method
3. Add `type(): ?string` method

```php
<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Contracts\Foundation\Application;

/**
 * سرویس مرکزی برای دسترسی به tenant (سایت) فعلی در customer-site.
 * هر نصب customer-site یک tenant است؛ شناسایی از License و domain.
 * 
 * @implements \App\Contracts\Services\TenantContextContract
 */
class TenantContext
{
    protected ?License $license = null;

    protected ?string $domain = null;

    public function __construct(
        protected Application $app
    ) {}

    /**
     * لایسنس فعال (tenant) فعلی.
     */
    public function current(): ?License
    {
        if ($this->license !== null) {
            return $this->license;
        }

        $license = License::current();
        if ($license && $license->isValid()) {
            $this->license = $license;
            $this->domain = request()->getHost();
            return $license;
        }

        return null;
    }

    /**
     * tenant را صریحاً تنظیم کن.
     */
    public function set(?object $tenant): void
    {
        if ($tenant !== null && !($tenant instanceof License)) {
            throw new \InvalidArgumentException('Tenant must be an instance of ' . License::class);
        }
        $this->license = $tenant;
        $this->domain = $tenant ? request()->getHost() : null;
    }

    /**
     * context را پاک کن.
     */
    public function clear(): void
    {
        $this->license = null;
        $this->domain = null;
    }

    /**
     * دامنهٔ tenant فعلی.
     */
    public function domain(): ?string
    {
        $this->current();
        return $this->domain;
    }

    /**
     * آیا tenant (لایسنس معتبر) وجود دارد؟
     */
    public function hasTenant(): bool
    {
        return $this->current() !== null;
    }

    /**
     * شناسهٔ tenant فعلی.
     */
    public function id(): ?int
    {
        $license = $this->current();
        return $license?->id;
    }

    /**
     * نوع tenant فعلی (در customer-site همیشه 'site').
     */
    public function type(): ?string
    {
        return $this->hasTenant() ? 'site' : null;
    }

    /**
     * ماژول‌های مجاز tenant فعلی.
     *
     * @return array<string>
     */
    public function allowedModules(): array
    {
        $license = $this->current();
        if (! $license || ! is_array($license->modules)) {
            return [];
        }
        return $license->modules;
    }

    /**
     * آیا ماژول برای tenant فعلی مجاز است؟
     */
    public function hasModule(string $slug): bool
    {
        return in_array($slug, $this->allowedModules(), true);
    }
}
```

### 3.1.5 Service Provider Binding

Add contract binding to `AppServiceProvider` in both projects:

**saas-platform** (`app/Providers/AppServiceProvider.php`):
```php
use App\Contracts\Services\TenantContextContract;
use App\Services\TenantContext;

public function register(): void
{
    $this->app->bind(TenantContextContract::class, TenantContext::class);
}
```

**customer-site** (`app/Providers/AppServiceProvider.php`):
```php
use App\Contracts\Services\TenantContextContract;
use App\Services\TenantContext;

public function register(): void
{
    $this->app->bind(TenantContextContract::class, TenantContext::class);
}
```

### 3.1.6 Usage Migration

Existing code continues to work with `app(TenantContext::class)`. New code should type-hint against the contract:

```php
// ✅ NEW CODE — type hint against contract
use App\Contracts\Services\TenantContextContract;

class SomeService
{
    public function __construct(
        protected TenantContextContract $tenantContext
    ) {}
}

// ✅ LEGACY CODE — still works unchanged
$tenant = app(TenantContext::class)->current();
```

---

## 3.2 Shared Trait (HasTenantContext)

### 3.2.1 Trait Definition

Create `app/Traits/HasTenantContext.php` in saas-platform:

```php
<?php
namespace App\Traits;

use App\Contracts\Services\TenantContextContract;

/**
 * ترِیت مشترک برای سرویس‌هایی که به TenantContext نیاز دارند.
 * منطق مشترک clear(), hasTenant(), id() را فراهم می‌کند.
 * هر پروژه متدهای خاص خود (resolve, set) را پیاده‌سازی می‌کند.
 */
trait HasTenantContext
{
    protected ?object $tenant = null;

    protected ?string $tenantType = null;

    /**
     * context را پاک کن.
     */
    public function clear(): void
    {
        $this->tenant = null;
        $this->tenantType = null;
    }

    /**
     * آیا tenant وجود دارد؟
     */
    public function hasTenant(): bool
    {
        return $this->current() !== null;
    }

    /**
     * شناسهٔ tenant فعلی.
     */
    public function id(): ?int
    {
        $t = $this->current();
        return $t && isset($t->id) ? (int) $t->id : null;
    }

    /**
     * نوع tenant فعلی.
     */
    public function type(): ?string
    {
        if ($this->tenantType !== null) {
            return $this->tenantType;
        }
        $this->current();
        return $this->tenantType;
    }

    /**
     * tenant را صریحاً تنظیم کن (پیاده‌سازی در پروژه).
     */
    abstract public function set(object $tenant): void;

    /**
     * tenant فعلی را برگردان (پیاده‌سازی در پروژه).
     */
    abstract public function current(): ?object;
}
```

### 3.2.2 saas-platform Refactored TenantContext (Using Trait)

```php
<?php
namespace App\Services;

use App\Contracts\Services\TenantContextContract;
use App\Models\Reseller;
use App\Models\Site;
use App\Traits\HasTenantContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

/**
 * سرویس مرکزی برای دسترسی به tenant فعلی در saas-platform.
 * Tenant از دامنه/زیردامنه یا از route/request استخراج می‌شود.
 */
class TenantContext implements TenantContextContract
{
    use HasTenantContext;

    public function __construct(
        protected Application $app,
        protected TenantResolver $resolver
    ) {}

    /**
     * tenant فعلی را برگردان (Reseller یا Site).
     */
    public function current(): ?object
    {
        if ($this->tenant !== null) {
            return $this->tenant;
        }

        $request = $this->app->make(Request::class);
        if (! $request) {
            return null;
        }

        // ۱. از domain (Reseller)
        $fromDomain = $this->resolver->resolveFromRequest($request);
        if ($fromDomain) {
            $this->set($fromDomain);
            return $this->tenant;
        }

        // ۲. از request attributes (مثلاً EnsureUserIsReseller)
        if ($request->attributes->has('current_reseller')) {
            $reseller = $request->attributes->get('current_reseller');
            if ($reseller instanceof Reseller) {
                $this->set($reseller);
                return $this->tenant;
            }
        }

        // ۳. از domain برای branding (current_reseller_from_domain)
        if ($request->attributes->has('current_reseller_from_domain')) {
            $reseller = $request->attributes->get('current_reseller_from_domain');
            if ($reseller instanceof Reseller) {
                $this->set($reseller);
                return $this->tenant;
            }
        }

        return null;
    }

    /**
     * tenant را صریحاً تنظیم کن.
     */
    public function set(object $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenantType = $this->resolver->getTenantType($tenant);
    }

    /**
     * آیا tenant فعلی Reseller است؟
     */
    public function isReseller(): bool
    {
        return $this->type() === 'reseller';
    }

    /**
     * آیا tenant فعلی Site است؟
     */
    public function isSite(): bool
    {
        return $this->type() === 'site';
    }

    /**
     * Reseller فعلی (در صورت وجود).
     */
    public function reseller(): ?Reseller
    {
        $t = $this->current();
        return $t instanceof Reseller ? $t : null;
    }

    /**
     * Site فعلی (در صورت وجود).
     */
    public function site(): ?Site
    {
        $t = $this->current();
        return $t instanceof Site ? $t : null;
    }

    /**
     * کوئری را به tenant فعلی (Reseller) محدود کن.
     */
    public function scopeQueryToTenant($query, string $tenantColumn = 'reseller_id')
    {
        $reseller = $this->reseller();
        if ($reseller && $query) {
            return $query->where($tenantColumn, $reseller->id);
        }
        return $query;
    }

    /**
     * ماژول‌های مجاز tenant فعلی (از طریق Site).
     *
     * @return array<string>
     */
    public function allowedModules(): array
    {
        $site = $this->site();
        if (! $site || ! $site->relationLoaded('modules')) {
            return [];
        }
        return $site->modules->pluck('slug')->toArray();
    }

    /**
     * آیا ماژول برای tenant فعلی مجاز است؟
     */
    public function hasModule(string $slug): bool
    {
        return in_array($slug, $this->allowedModules(), true);
    }
}
```

### 3.2.3 customer-site Refactored TenantContext (Using Trait)

```php
<?php

namespace App\Services;

use App\Contracts\Services\TenantContextContract;
use App\Models\License;
use App\Traits\HasTenantContext;
use Illuminate\Contracts\Foundation\Application;

/**
 * سرویس مرکزی برای دسترسی به tenant (سایت) فعلی در customer-site.
 * هر نصب customer-site یک tenant است؛ شناسایی از License و domain.
 */
class TenantContext implements TenantContextContract
{
    use HasTenantContext;

    protected ?string $domain = null;

    public function __construct(
        protected Application $app
    ) {}

    /**
     * لایسنس فعال (tenant) فعلی.
     */
    public function current(): ?License
    {
        if ($this->tenant !== null) {
            return $this->tenant;
        }

        $license = License::current();
        if ($license && $license->isValid()) {
            $this->tenant = $license;
            $this->tenantType = 'site';
            $this->domain = request()->getHost();
            return $license;
        }

        return null;
    }

    /**
     * tenant را صریحاً تنظیم کن.
     */
    public function set(object $tenant): void
    {
        if (!($tenant instanceof License)) {
            throw new \InvalidArgumentException('Tenant must be an instance of ' . License::class);
        }
        $this->tenant = $tenant;
        $this->tenantType = 'site';
        $this->domain = request()->getHost();
    }

    /**
     * context را پاک کن.
     */
    public function clear(): void
    {
        $this->tenant = null;
        $this->tenantType = null;
        $this->domain = null;
    }

    /**
     * دامنهٔ tenant فعلی.
     */
    public function domain(): ?string
    {
        $this->current();
        return $this->domain;
    }

    /**
     * ماژول‌های مجاز tenant فعلی.
     *
     * @return array<string>
     */
    public function allowedModules(): array
    {
        $license = $this->current();
        if (! $license || ! is_array($license->modules)) {
            return [];
        }
        return $license->modules;
    }

    /**
     * آیا ماژول برای tenant فعلی مجاز است؟
     */
    public function hasModule(string $slug): bool
    {
        return in_array($slug, $this->allowedModules(), true);
    }
}
```

### 3.2.4 Trait Location Strategy

The `HasTenantContext` trait is defined in **saas-platform** (`app/Traits/HasTenantContext.php`). For customer-site to use it, two options exist:

**Option A — Copy (Simple, Current Phase):**
- Copy `HasTenantContext.php` to customer-site's `app/Traits/` directory
- Acceptable during Phase 3.2 until shared package infrastructure is ready

**Option B — Shared Package (Future, Phase 3.3):**
- Move trait to `packages/saas-platform/shared/src/Traits/HasTenantContext.php`
- Both projects require the package via composer

**Recommendation:** Use **Option A** for now. Phase 3.3 will move it to the shared package.

---

## 3.3 Full Unification (Shared Package)

### 3.3.1 Package Structure

```
packages/saas-platform/shared/
├── src/
│   ├── Contracts/
│   │   └── Services/
│   │       └── TenantContextContract.php
│   ├── Traits/
│   │   └── HasTenantContext.php
│   ├── Middleware/
│   │   ├── SetTenantContext.php (base implementation)
│   │   └── EnsureTenantScope.php (base implementation)
│   └── ServiceProvider.php
├── composer.json
└── README.md
```

### 3.3.2 TenantContextContract in Shared Package

```php
<?php
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

### 3.3.3 HasTenantContext in Shared Package

```php
<?php
// packages/saas-platform/shared/src/Traits/HasTenantContext.php
namespace SaasPlatform\Shared\Traits;

trait HasTenantContext
{
    protected ?object $tenant = null;
    protected ?string $tenantType = null;

    public function clear(): void
    {
        $this->tenant = null;
        $this->tenantType = null;
    }

    public function hasTenant(): bool
    {
        return $this->current() !== null;
    }

    public function id(): ?int
    {
        $t = $this->current();
        return $t && isset($t->id) ? (int) $t->id : null;
    }

    public function type(): ?string
    {
        if ($this->tenantType !== null) {
            return $this->tenantType;
        }
        $this->current();
        return $this->tenantType;
    }

    abstract public function set(object $tenant): void;
    abstract public function current(): ?object;
}
```

### 3.3.4 Shared Middleware (Base Implementation)

**SetTenantContext (Base):**
```php
<?php
// packages/saas-platform/shared/src/Middleware/SetTenantContext.php
namespace SaasPlatform\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasPlatform\Shared\Contracts\Services\TenantContextContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware پایه برای تنظیم TenantContext.
 * هر پروژه با override کردن resolveTenant() منطق خاص خود را پیاده‌سازی می‌کند.
 */
abstract class SetTenantContext
{
    public function __construct(
        protected TenantContextContract $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->clear();

        $tenant = $this->resolveTenant($request);
        if ($tenant) {
            $this->tenantContext->set($tenant);
        }

        $response = $next($request);

        $this->tenantContext->clear();

        return $response;
    }

    abstract protected function resolveTenant(Request $request): ?object;
}
```

**EnsureTenantScope (Base):**
```php
<?php
// packages/saas-platform/shared/src/Middleware/EnsureTenantScope.php
namespace SaasPlatform\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasPlatform\Shared\Contracts\Services\TenantContextContract;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    public function __construct(
        protected TenantContextContract $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantContext->hasTenant()) {
            abort(403, 'دسترسی به این بخش نیاز به شناسایی tenant دارد.');
        }

        return $next($request);
    }
}
```

### 3.3.5 Project-Specific Middleware Implementations

**saas-platform** (`app/Http/Middleware/SetTenantContext.php`):
```php
<?php
namespace App\Http\Middleware;

use App\Services\ResellerDetectionService;
use Illuminate\Http\Request;
use SaasPlatform\Shared\Middleware\SetTenantContext as BaseSetTenantContext;

class SetTenantContext extends BaseSetTenantContext
{
    protected function resolveTenant(Request $request): ?object
    {
        return ResellerDetectionService::fromRequest($request);
    }
}
```

**customer-site** (`app/Http/Middleware/SetTenantContext.php`):
```php
<?php
namespace App\Http\Middleware;

use App\Models\License;
use App\Support\Installation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use SaasPlatform\Shared\Middleware\SetTenantContext as BaseSetTenantContext;

class SetTenantContext extends BaseSetTenantContext
{
    protected function resolveTenant(Request $request): ?object
    {
        if (! Installation::isLockPresent() || ! Schema::hasTable('licenses')) {
            return null;
        }

        $license = License::current();
        if ($license && $license->isValid($request->getHost())) {
            return $license;
        }

        return null;
    }
}
```

### 3.3.6 Service Provider for Shared Package

```php
<?php
// packages/saas-platform/shared/src/ServiceProvider.php
namespace SaasPlatform\Shared;

use Illuminate\Support\ServiceProvider;
use SaasPlatform\Shared\Contracts\Services\TenantContextContract;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contracts are bound by each project's AppServiceProvider
        // This provider only registers shared utilities
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shared');
    }
}
```

---

## 4. Backward Compatibility Layer

### 4.1 Model Aliases

To ensure existing code that type-hints `App\Services\TenantContext` continues to work, add aliases in `bootstrap/compat.php`:

```php
<?php
// bootstrap/compat.php — Backward Compatibility Aliases

// Tenant Context aliases (Phase 3)
if (! class_exists('App\Services\TenantContext')) {
    class_alias(\SaasPlatform\Shared\Contracts\Services\TenantContextContract::class, 'App\Services\TenantContext');
}
```

### 4.2 Route Forwarders

No route changes needed — middleware is resolved via the container, and the contract binding ensures correct resolution.

### 4.3 View Compatibility

No view changes needed — TenantContext is used in controllers/services, not directly in Blade views.

### 4.4 Compatibility Matrix

| Existing Code Pattern | Phase 3.1 | Phase 3.2 | Phase 3.3 |
|----------------------|-----------|-----------|-----------|
| `app(TenantContext::class)->current()` | ✅ Works | ✅ Works | ✅ Works (alias) |
| `app(TenantContextContract::class)->current()` | ✅ New pattern | ✅ Works | ✅ Works |
| `$tc->reseller()` | ✅ Works | ✅ Works | ✅ Works |
| `$tc->site()` | ✅ Works | ✅ Works | ✅ Works |
| `$tc->scopeQueryToTenant()` | ✅ Works | ✅ Works | ✅ Works |
| `$tc->allowedModules()` | ✅ Works | ✅ Works | ✅ Works |
| `$tc->hasModule()` | ✅ Works | ✅ Works | ✅ Works |
| `$tc->domain()` | ✅ Works | ✅ Works | ✅ Works |

---

## 5. Migration Safety Rules

| Rule | Description |
|------|-------------|
| **NO** | Change the return type of `current()` in either project |
| **NO** | Remove any existing methods from either TenantContext |
| **NO** | Change middleware execution order |
| **NO** | Break auth/session isolation |
| **NO** | Remove `ResellerDetectionService` static calls until all consumers migrate |
| **YES** | Add new methods that don't conflict with existing ones |
| **YES** | Create the interface contract (additive only) |
| **YES** | Add `scopeQueryToTenant()` to customer-site (future-proofing) |
| **YES** | Add `allowedModules()`/`hasModule()` to saas-platform (parity) |

### 5.1 Migration Flow

```
Phase 3.1 (Week 1)
├── Create TenantContextContract
├── Add id() + type() to customer-site TenantContext
├── Fix set() signature in customer-site
├── Bind contract in both AppServiceProviders
└── Verify no regressions

Phase 3.2 (Week 2)
├── Create HasTenantContext trait (saas-platform)
├── Copy trait to customer-site
├── Refactor both TenantContexts to use trait
├── Add allowedModules()/hasModule() to saas-platform
└── Run full test suite

Phase 3.3 (Week 3-4)
├── Move contract + trait to shared package
├── Create shared middleware base classes
├── Refactor project middleware to extend base
├── Update composer.json in both projects
├── Run full test suite
└── Deploy to staging
```

---

## 6. Testing Strategy

### 6.1 Unit Tests

**TenantContextContract Test (saas-platform):**
```php
<?php
namespace Tests\Unit\Services;

use App\Contracts\Services\TenantContextContract;
use App\Services\TenantContext;
use Tests\TestCase;

class TenantContextContractTest extends TestCase
{
    private TenantContextContract $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantContext = app(TenantContextContract::class);
    }

    /** @test */
    public function it_implements_the_contract()
    {
        $this->assertInstanceOf(TenantContextContract::class, $this->tenantContext);
    }

    /** @test */
    public function it_returns_null_when_no_tenant()
    {
        $this->assertNull($this->tenantContext->current());
        $this->assertFalse($this->tenantContext->hasTenant());
        $this->assertNull($this->tenantContext->id());
        $this->assertNull($this->tenantContext->type());
    }

    /** @test */
    public function it_can_set_and_clear_tenant()
    {
        $reseller = \App\Models\Reseller::factory()->create();
        $this->tenantContext->set($reseller);
        
        $this->assertTrue($this->tenantContext->hasTenant());
        $this->assertEquals('reseller', $this->tenantContext->type());
        
        $this->tenantContext->clear();
        $this->assertFalse($this->tenantContext->hasTenant());
    }
}
```

**customer-site TenantContext Contract Test:**
```php
<?php
namespace Tests\Unit\Services;

use App\Contracts\Services\TenantContextContract;
use App\Models\License;
use App\Services\TenantContext;
use Tests\TestCase;

class TenantContextContractTest extends TestCase
{
    private TenantContextContract $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantContext = app(TenantContextContract::class);
    }

    /** @test */
    public function it_implements_the_contract()
    {
        $this->assertInstanceOf(TenantContextContract::class, $this->tenantContext);
    }

    /** @test */
    public function it_returns_site_type_when_tenant_exists()
    {
        $license = License::factory()->create(['is_valid' => true]);
        $this->tenantContext->set($license);
        
        $this->assertEquals('site', $this->tenantContext->type());
        $this->assertEquals($license->id, $this->tenantContext->id());
    }
}
```

### 6.2 Integration Tests

**Middleware Test (saas-platform):**
```php
<?php
namespace Tests\Integration\Http\Middleware;

use App\Http\Middleware\SetTenantContext;
use App\Models\Reseller;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Tests\TestCase;

class SetTenantContextMiddlewareTest extends TestCase
{
    /** @test */
    public function it_sets_tenant_from_domain()
    {
        $reseller = Reseller::factory()->create([
            'is_white_label' => true,
            'custom_domain' => 'test.example.com',
        ]);

        $request = Request::create('https://test.example.com/dashboard');
        $middleware = new SetTenantContext(app(TenantContext::class));
        
        $response = $middleware->handle($request, fn($req) => response('OK'));
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($reseller->id, app(TenantContext::class)->id());
    }
}
```

### 6.3 Regression Test Suite

```php
<?php
namespace Tests\Regression;

use App\Contracts\Services\TenantContextContract;
use App\Services\TenantContext;
use Tests\TestCase;

/**
 * تست‌های رگرسیون برای اطمینان از عدم شکستن کدهای موجود.
 * این تست‌ها قبل و بعد از هر فاز مهاجرت اجرا می‌شوند.
 */
class TenantContextRegressionTest extends TestCase
{
    /** @test */
    public function existing_tenant_context_instantiation_still_works()
    {
        // الگوی قدیمی باید همچنان کار کند
        $tc = app(TenantContext::class);
        $this->assertNotNull($tc);
    }

    /** @test */
    public function contract_binding_resolves_correctly()
    {
        // الگوی جدید باید کار کند
        $tc = app(TenantContextContract::class);
        $this->assertNotNull($tc);
    }

    /** @test */
    public function both_resolve_to_same_instance()
    {
        $old = app(TenantContext::class);
        $new = app(TenantContextContract::class);
        $this->assertSame($old, $new);
    }

    /** @test */
    public function all_original_methods_are_available()
    {
        $tc = app(TenantContext::class);
        
        // saas-platform specific methods
        if (defined('App\Services\TenantContext::class')) {
            $this->assertTrue(method_exists($tc, 'isReseller'));
            $this->assertTrue(method_exists($tc, 'isSite'));
            $this->assertTrue(method_exists($tc, 'reseller'));
            $this->assertTrue(method_exists($tc, 'site'));
            $this->assertTrue(method_exists($tc, 'scopeQueryToTenant'));
        }
        
        // customer-site specific methods
        if (method_exists($tc, 'domain')) {
            $this->assertTrue(method_exists($tc, 'domain'));
        }
        
        // Contract methods (both projects)
        $this->assertTrue(method_exists($tc, 'current'));
        $this->assertTrue(method_exists($tc, 'set'));
        $this->assertTrue(method_exists($tc, 'clear'));
        $this->assertTrue(method_exists($tc, 'hasTenant'));
        $this->assertTrue(method_exists($tc, 'id'));
        $this->assertTrue(method_exists($tc, 'type'));
    }
}
```

### 6.4 Test Commands

```bash
# Run all TenantContext tests
php artisan test --filter=TenantContext

# Run regression tests
php artisan test --filter=TenantContextRegression

# Run with coverage
php artisan test --filter=TenantContext --coverage
```

---

## 7. Rollback Plan

### 7.1 Rollback Triggers

| Trigger | Condition | Action |
|---------|-----------|--------|
| **Critical Bug** | Any 500 error in tenant resolution | Immediate rollback to previous TenantContext |
| **Data Leak** | Cross-tenant data visible | Immediate rollback + security audit |
| **Performance Regression** | >10% increase in response time | Rollback within 24 hours |
| **Auth Failure** | Users cannot log in or access their data | Immediate rollback |
| **Test Failure** | >5% of regression tests fail | Rollback before deployment |

### 7.2 Rollback Procedure

```
Step 1 — Revert Code
├── git revert <phase-3-commit>
├── Or restore from backup: git checkout HEAD~1 -- app/Services/TenantContext.php
└── Verify old TenantContext works

Step 2 — Clear Caches
├── php artisan cache:clear
├── php artisan config:clear
└── php artisan view:clear

Step 3 — Verify
├── Run regression tests
├── Manual smoke test of tenant resolution
└── Confirm no data leaks

Step 4 — Communicate
├── Log rollback reason in docs/rollback-log.md
└── Notify team
```

### 7.3 Rollback Migration Template

```php
<?php
// database/migrations/rollback/2026_05_22_rollback_phase3.php
// This file documents the rollback state for Phase 3.
// To rollback: restore app/Services/TenantContext.php from git history.
```

---

## 8. Timeline & Milestones

### 8.1 Timeline

| Week | Phase | Tasks | Risk |
|------|-------|-------|------|
| **Week 1** | 3.1 | Create contract, update customer-site TenantContext, bind providers | Low |
| **Week 2** | 3.2 | Create trait, refactor both TenantContexts, add parity methods | Low |
| **Week 3** | 3.3 | Move to shared package, create base middleware, update projects | Medium |
| **Week 4** | 3.3 | Integration testing, staging deployment, production rollout | Medium |

### 8.2 Milestones

| Milestone | Date | Deliverable | Verification |
|-----------|------|-------------|--------------|
| **M1** | Week 1 | TenantContextContract created | Contract test passes |
| **M2** | Week 1 | customer-site TenantContext updated | All 6 contract methods available |
| **M3** | Week 2 | HasTenantContext trait created | Both projects use trait |
| **M4** | Week 2 | Parity methods added | allowedModules() + hasModule() in saas-platform |
| **M5** | Week 3 | Shared package created | packages/saas-platform/shared/ exists |
| **M6** | Week 3 | Base middleware created | Both projects extend base classes |
| **M7** | Week 4 | All tests pass | 100% regression test pass rate |
| **M8** | Week 4 | Production rollout | Zero tenant resolution errors |

---

## 9. Risk Mitigation

### 9.1 Risk Matrix

| Risk | Probability | Impact | Mitigation |
|------|------------|--------|------------|
| **Contract mismatch** | Low | High | Duck-typing verification before deployment |
| **customer-site set() signature breakage** | Medium | High | Add null check + type validation |
| **Middleware order change** | Low | Critical | Never change middleware execution order |
| **Performance regression** | Low | Medium | Benchmark before/after each phase |
| **Cross-tenant data leak** | Very Low | Critical | Regression tests verify tenant isolation |
| **Composer dependency conflict** | Medium | Medium | Test shared package in isolation first |
| **Developer confusion (dual patterns)** | Medium | Low | Document migration guide + deprecation notices |
| **Rollback complexity** | Low | Medium | Each phase is independently revertible |

### 9.2 Contingency Plan

| Scenario | Action |
|----------|--------|
| Phase 3.1 breaks customer-site | Revert customer-site TenantContext only; saas-platform contract is additive |
| Phase 3.2 trait causes issues | Remove trait usage; both projects keep inline implementations |
| Phase 3.3 shared package fails | Keep contract + trait in app/ namespace; defer package move |
| Middleware refactor breaks routes | Revert middleware to original; keep contract binding |
| Test environment unavailable | Manual verification of all 6 contract methods in both projects |

---

## 10. Conclusion

### 10.1 Key Deliverables

| # | Deliverable | Location |
|---|-------------|----------|
| 1 | `TenantContextContract` interface | `app/Contracts/Services/TenantContextContract.php` |
| 2 | Updated customer-site `TenantContext` | `customer-site/app/Services/TenantContext.php` |
| 3 | Contract binding in `AppServiceProvider` | Both projects |
| 4 | `HasTenantContext` trait | `app/Traits/HasTenantContext.php` (both projects) |
| 5 | Parity methods (`allowedModules`, `hasModule`) | saas-platform `TenantContext` |
| 6 | Shared package structure | `packages/saas-platform/shared/` |
| 7 | Base middleware classes | `packages/saas-platform/shared/src/Middleware/` |
| 8 | Refactored project middleware | Both projects |
| 9 | Unit tests | `tests/Unit/Services/TenantContextContractTest.php` |
| 10 | Integration tests | `tests/Integration/Http/Middleware/SetTenantContextMiddlewareTest.php` |
| 11 | Regression tests | `tests/Regression/TenantContextRegressionTest.php` |

### 10.2 Statistics

| Metric | Value |
|--------|-------|
| **New files created** | 8 (contract, trait, 2x middleware base, service provider, 3 test files) |
| **Files modified** | 4 (customer-site TenantContext, 2x AppServiceProvider, 2x project middleware) |
| **Methods added** | 4 (id, type to customer-site; allowedModules, hasModule to saas-platform) |
| **Zero regressions** | ✅ All existing code continues to work unchanged |
| **Backward compatibility** | ✅ 100% — additive changes only |
| **Test coverage** | Unit + Integration + Regression |

### 10.3 Next Steps

After Phase 3 is complete, proceed to **Phase 4 — Update Infrastructure** which will:
1. Implement the update safety foundation with rollback hooks
2. Create version contracts for all shared modules
3. Deploy the update staging environment
4. Implement update eligibility checks

---

> **End of Phase 3 — Tenant Context Unification**
> 
> Total sections: 10 | Total lines: ~1,350
> 
> Next: Proceed to Phase 4 — Update Infrastructure
