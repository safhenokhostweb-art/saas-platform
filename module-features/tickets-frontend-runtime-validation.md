# Tickets Module — Frontend Runtime Validation

**PHASE 7.1 — TASK 8**  
**Date:** 2026-05-25  
**Status:** Static validation complete (PHP not in PATH — runtime validation deferred to CI)

---

## Overview

This document defines all frontend runtime checks for the Tickets module canonical migration. Checks are grouped by layer: Blade directive authority, route namespace integrity, config resolution, and form security.

---

## 1. Blade Directive Authority

### CHECK F-1: `@tenant` wraps all site views

**Expected:** Every site view's content section is wrapped in `@tenant`/`@endtenant`.

**Files verified:**
- `resources/views/site/tickets/index.blade.php` ✅ `@tenant` on line 6
- `resources/views/site/tickets/create.blade.php` ✅ `@tenant` on line 6
- `resources/views/site/tickets/show.blade.php` ✅ `@tenant` on line 6

**Drift command:** `php artisan drift:blades`

---

### CHECK F-2: No `@platform` in site views

**Expected:** Zero occurrences of `@platform` in `resources/views/site/`.

**Manual check result:** ✅ No `@platform` usage in site views.

---

### CHECK F-3: No hardcoded layout strings in package views

**Expected:** All views use `@extends(config('tickets.layouts.*'))` — not `@extends('admin.layout')` directly.

**Admin views verified:**
- `admin/tickets/index.blade.php` → `@extends(config('tickets.layouts.admin', 'admin.layout'))` ✅
- `admin/tickets/create.blade.php` → `@extends(config('tickets.layouts.admin', 'admin.layout'))` ✅
- `admin/tickets/show.blade.php` → `@extends(config('tickets.layouts.admin', 'admin.layout'))` ✅

**Site views verified:**
- `site/tickets/index.blade.php` → `@extends(config('tickets.layouts.site', 'layouts.app'))` ✅
- `site/tickets/create.blade.php` → `@extends(config('tickets.layouts.site', 'layouts.app'))` ✅
- `site/tickets/show.blade.php` → `@extends(config('tickets.layouts.site', 'layouts.app'))` ✅

---

## 2. Route Namespace Integrity

### CHECK F-4: Admin routes under `tickets.admin.*`

**Expected:** All admin routes are named `tickets.admin.*` and use middleware `['web', 'auth']`.

```php
// Routes/admin.php
Route::prefix('admin/tickets')->name('tickets.admin.')->middleware(['web', 'auth'])->group(...)
```

✅ Verified in `Routes/admin.php`.

---

### CHECK F-5: Site routes under `tickets.site.*`

**Expected:** All portal routes are named `tickets.site.*` and scoped to authenticated tenant users.

```php
// Routes/site.php
Route::prefix('tickets')->name('tickets.site.')->middleware(['web', 'auth'])->group(...)
```

✅ Verified in `Routes/site.php`.

---

### CHECK F-6: API routes under `tickets.api.*` with Sanctum

**Expected:** All API routes use `auth:sanctum` middleware.

```php
// Routes/api.php
Route::prefix('api/tickets')->name('tickets.api.')->middleware(['api', 'auth:sanctum'])->group(...)
```

✅ Verified in `Routes/api.php`.

---

### CHECK F-7: No old `Routes/web.php` routes colliding

**Risk:** The legacy `Routes/web.php` still registers routes that may conflict with the new route files.

**Recommended action:** After TicketsServiceProvider is updated to load admin/site/api routes, remove the `loadRoutesFrom` call for `Routes/web.php`. The old web.php should be archived or deleted.

**Status:** ⚠️ Pending — web.php is still loaded. New ServiceProvider replaces this.

---

## 3. Config Resolution

### CHECK F-8: Layout config keys resolve at runtime

**Expected:** `config('tickets.layouts.admin')` returns a non-null string before any view renders.

**Verification path:**
1. `TicketsServiceProvider::register()` calls `mergeConfigFrom(config/tickets.php, 'tickets')`
2. `config/tickets.php` defines `layouts.admin = 'admin.layout'`
3. Views use `config('tickets.layouts.admin', 'admin.layout')` with fallback

✅ Static verification complete.

---

### CHECK F-9: SLA feature flag gates SLA views

**Expected:** `TicketSlaController::index()` returns 404 when `config('feature-flags.tickets.sla')` is false.

```php
if (! $this->gateService->isSlaEnabled()) {
    abort(404);
}
```

✅ Verified in `TicketSlaController`.

---

### CHECK F-10: Attachment config gates file input

**Expected:** Create form only renders `<input type="file">` when `config('tickets.attachments.enabled')` is true.

```blade
@if($attachmentsEnabled)
<div>
    <label>پیوست</label>
    <input type="file" name="attachments[]" multiple>
</div>
@endif
```

✅ Verified in `site/tickets/create.blade.php`.

---

## 4. Form Security

### CHECK F-11: CSRF tokens on all forms

**Expected:** Every POST form includes `@csrf`.

- `admin/tickets/create.blade.php` → `@csrf` ✅
- `admin/tickets/show.blade.php` (reply form) → `@csrf` ✅
- `site/tickets/create.blade.php` → `@csrf` ✅
- `site/tickets/show.blade.php` (reply form) → `@csrf` ✅

---

### CHECK F-12: `site_id` never a form field in portal views

**Expected:** Zero hidden `site_id` inputs in site views.

✅ Confirmed — site_id is resolved server-side only via `TicketPortalService::resolveSiteId()`.

---

### CHECK F-13: `author_type` never a form field

**Expected:** Zero `author_type` inputs in any view. Set server-side only.

- Admin reply: `$data['author_type'] = TicketReply::AUTHOR_STAFF` in controller ✅
- Portal reply: `author_type` set in `TicketSubmissionController::storeReply()` ✅

---

## 5. View Composer Validation

### CHECK F-14: View composer registered for admin layout resolution

**Expected:** `TicketsServiceProvider::boot()` registers a view composer for `tickets::admin.*` that resolves the correct layout per context.

✅ Registered in `TicketsServiceProvider::registerViewComposers()`.

---

## 6. Static Namespace Validation

All files checked for correct namespace declarations:

| File                                          | Expected Namespace                                        | Status |
|-----------------------------------------------|-----------------------------------------------------------|--------|
| `src/Providers/TicketsServiceProvider.php`    | `SaaSPlatform\Modules\Tickets\Providers`                  | ✅     |
| `src/Http/Controllers/Admin/TicketController` | `SaaSPlatform\Modules\Tickets\Http\Controllers\Admin`     | ✅     |
| `src/Http/Controllers/Site/TicketPortalController` | `SaaSPlatform\Modules\Tickets\Http\Controllers\Site` | ✅     |
| `src/Http/Controllers/Api/TicketApiController` | `SaaSPlatform\Modules\Tickets\Http\Controllers\Api`      | ✅     |
| `src/Services/TicketLifecycleService`         | `SaaSPlatform\Modules\Tickets\Services`                   | ✅     |
| `src/Policies/TicketPolicy`                   | `SaaSPlatform\Modules\Tickets\Policies`                   | ✅     |

---

## Deferred Runtime Checks (requires live PHP)

These checks pass statically but require a running application to fully validate:

| Check | Command | Expected |
|-------|---------|----------|
| Service container bindings | `php artisan tinker` → `app(TicketServiceInterface::class)` | `TicketService` instance |
| Policy gate resolution | `php artisan tinker` → `Gate::getPolicyFor(Ticket::class)` | `TicketPolicy` instance |
| Route list | `php artisan route:list --name=tickets` | 20+ routes registered |
| Config merge | `php artisan tinker` → `config('tickets.layouts')` | array with admin/site keys |
| Module list | `php artisan module:list` | tickets v1.1.0 listed |
| Module build | `php artisan module:build tickets` | All 9 checks pass |
