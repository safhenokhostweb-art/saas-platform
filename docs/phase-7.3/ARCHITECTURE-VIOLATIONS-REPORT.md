# ARCHITECTURE-VIOLATIONS-REPORT.md
## Phase 7.3 — All Confirmed Violations
**Date:** 2026-05-25 | Verified against actual runtime files

---

## CRITICAL VIOLATIONS

### V-001 — Package Imports Platform App Models Directly
**Severity:** CRITICAL  
**Package:** `modules/tickets`, `modules/forms`  
**Files:**
- `packages/saas-platform/modules/tickets/src/Services/TicketService.php:5-6` — `use App\Models\Site; use App\Models\User;`
- `packages/saas-platform/modules/forms/src/Services/FormService.php:8-9` — `use App\Services\CaptchaService; use App\Models\Site;`
- `packages/saas-platform/modules/forms/src/Services/FormsLicensingService.php:5-8` — `use App\Models\{Module,ModuleEntitlement,Site}; use App\Services\ModuleAssignmentService;`
- `packages/saas-platform/modules/forms/src/Services/FormsGateService.php:5-6` — `use App\Models\Site; use App\Services\TenantContext;`
- `packages/saas-platform/modules/forms/src/Contracts/FormSiteServiceInterface.php:6` — `use App\Models\Site;` *(contract references platform model)*

**Impact:** Packages are non-distributable. Installing these modules on any system WITHOUT the exact platform app namespace will fail. Violates every principle of package isolation. Blocks marketplace distribution.

---

### V-002 — Dual Ticket System Running Simultaneously
**Severity:** CRITICAL  
**Files:**
- `app/Providers/AppServiceProvider.php:12-16` — binds legacy `SupportTicketServiceInterface` → `SupportTicketService`
- `packages/saas-platform/modules/tickets/src/Providers/TicketsServiceProvider.php` — binds canonical `TicketServiceInterface` → `TicketService`

**Impact:** Two INDEPENDENT ticket service implementations are live simultaneously. Memory doubled. Ambiguity about which system is authoritative for new writes. Admin views use canonical routes (redirect chain) while legacy API routes (`/api/support-tickets`) still use the legacy service. Data divergence risk if any code accidentally uses the wrong binding.

---

### V-003 — Legacy API Routes Still Active Without Auth
**Severity:** CRITICAL  
**File:** `routes/api.php:67-76`
```
Route::prefix('support-tickets')->middleware('throttle:30,1')->group(function () {
    Route::post('/', [ApiSupportTicketController::class, 'store'])->name('api.support-tickets.store');
    Route::get('/', [ApiSupportTicketController::class, 'index'])->name('api.support-tickets.index');
    Route::post('{id}/reply', [ApiSupportTicketController::class, 'reply'])->name('api.support-tickets.reply');
    Route::get('unread-count', [ApiSupportTicketController::class, 'unreadCount'])->name('api.support-tickets.unread-count');
});
```
**Impact:** Legacy endpoint has only `throttle:30,1` — NO license validation, NO domain validation, NO auth. Any unauthenticated caller can POST tickets or READ all ticket unread-counts. The new `/api/tenant/tickets` correctly requires `tenant.api` middleware.

---

### V-004 — Tenant API Credentials in POST Body (Not Header)
**Severity:** CRITICAL  
**File:** `app/Http/Middleware/AuthenticateTenantApi.php:23-26`
```php
$licenseKey = (string) $request->input('license_key', '');
$domain     = (string) $request->input('domain', '');
```
**Impact:** Credentials appear in server access logs, proxy logs, and debug dumps. Standard practice is `Authorization: Bearer <token>` header. POST body credentials are logged by default in most HTTP infrastructure. Anyone with log access can extract license keys.

---

### V-005 — ModuleLifecycleInterface Namespace Collision Risk
**Severity:** CRITICAL  
**Files:**
- `SaaSPlatform\Modules\Tickets\Contracts\ModuleLifecycleInterface` → bound to `TicketModuleLifecycle`
- `SaaSPlatform\Modules\Forms\Contracts\ModuleLifecycleInterface` → bound to `ModuleLifecycleService`

**Impact:** Both interfaces share the exact local name `ModuleLifecycleInterface`. When a third module or orchestrator resolves this interface by its short name or via `app()->make()` with the wrong FQN, it silently gets the wrong implementation. Last-registered binding wins at container level if ever bound to a shared key.

---

## HIGH VIOLATIONS

### V-006 — Package Controller Extends Platform Controller
**Severity:** HIGH  
**File:** `packages/saas-platform/modules/tickets/src/Http/Controllers/TicketsController.php:5`  
```php
use App\Http\Controllers\Controller;
```
**Impact:** Module package depends on the consuming app's base controller. Makes the package non-portable. Any tenant installation WITHOUT `App\Http\Controllers\Controller` breaks silently.

---

### V-007 — DetectResellerFromDomain + SetTenantContext = Double DB Lookup
**Severity:** HIGH  
**Files:**
- `app/Http/Middleware/DetectResellerFromDomain.php` — calls `ResellerDetectionService::fromRequest()`
- `app/Http/Middleware/SetTenantContext.php` — calls `ResellerDetectionService::fromRequest()` again

**Impact:** Every single web request performs two identical reseller domain resolution queries. On high-traffic pages this doubles the reseller DB lookups. No caching between the two middleware.

---

### V-008 — TicketsServiceProvider Skips Asset Registration in Production
**Severity:** HIGH  
**File:** `packages/saas-platform/modules/tickets/src/Providers/TicketsServiceProvider.php:registerModuleAssets()`
```php
protected function registerModuleAssets(): void
{
    if (app()->environment('production')) {
        return; // ← silently skips in production
    }
    ...
}
```
**Impact:** Module asset registration is completely skipped in production. Assets that depend on `module-assets.modules` config at runtime will find the tickets module missing from the registry in production. This is the opposite of what production should do.

---

### V-009 — Legacy Support-Tickets Views Still Exist and Are Decorated
**Severity:** HIGH  
**Files:**
- `resources/views/admin/support-tickets/index.blade.php` — marked `@deprecated PHASE 7.15` but still contains ~200 lines of active CSS and HTML
- `resources/views/admin/support-tickets/_legacy/` — 5 full legacy views
- `resources/views/customer/support-tickets/` — 3 legacy customer views

**Impact:** Legacy views are not deleted. They are referenced by the redirect chain but also have their own route names. If any cached route hits them directly (e.g., a bookmark or old session link), they render deprecated UI. The presence of `_legacy/` directories creates confusion about what is active.

---

### V-010 — TicketRepository.search() Has No Default Tenant Scope
**Severity:** HIGH  
**File:** `packages/saas-platform/modules/tickets/src/Repositories/TicketRepository.php:search()`

The `search()` method applies `site_id` filter only when `$filters['site_id']` is provided by the caller. If a controller calls `$this->ticketService->getAll([])` without specifying `site_id`, it returns ALL tickets from ALL sites.

**Impact:** Admin controllers currently pass `site_id` from the request. But nothing at the repository layer enforces tenant isolation — it's a caller convention, not an architectural guarantee.

---

### V-011 — Forms Module Repository Imports Persian Date Helper
**Severity:** HIGH  
**File:** `packages/saas-platform/modules/forms/src/Repositories/FormSubmissionRepository.php:7`
```php
use App\Helpers\Jalali;
```
**Impact:** Forms package depends on a Persian-language calendar helper in the host app. This makes the forms module language/locale specific and non-distributable to non-Persian platforms.

---

### V-012 — TenantContext Only Resolves Resellers, Not Sites
**Severity:** HIGH  
**File:** `app/Services/TenantContext.php`

The three resolution strategies (domain, request attributes, `current_reseller_from_domain`) ALL resolve to `Reseller` objects. There is NO mechanism to resolve to a `Site` object.

**Impact:** Module-level site isolation is entirely manual — every service must pass `site_id` explicitly. There is no authoritative "current site" context. If any developer forgets to scope a query to `site_id`, there is no safety net.

---

## MEDIUM VIOLATIONS

### V-013 — Web Routes File Imports Module Controller Directly
**Severity:** MEDIUM  
**File:** `routes/web.php:51-54`
```php
use SaaSPlatform\Modules\Forms\Http\Controllers\FormFrontendController;
use SaaSPlatform\Modules\Forms\Http\Controllers\FormController;
```
**Impact:** Platform's main routes file has hard-coded imports from module packages. If Forms module is removed or renamed, the routes file breaks on load — even for non-forms routes.

---

### V-014 — Package @dev Version Constraint (No Semantic Versioning)
**Severity:** MEDIUM  
**File:** `composer.json:25-28`
```json
"saas-platform/modules-forms": "@dev",
"saas-platform/modules-tickets": "@dev"
```
**Impact:** No version pinning. `composer update` can pull breaking changes silently. Distributed installations will always get the latest dev HEAD, not a stable release. Rollback requires git revert, not version downgrade.

---

### V-015 — Forms Admin Views Still Live in App Resources
**Severity:** MEDIUM  
**File:** `resources/views/admin/forms/_legacy/` (7 files)

Legacy forms views under `_legacy/` subdirectory. The canonical views are in `packages/saas-platform/modules/forms/resources/views/`. These legacy files create a false sense of having a backup when they are actually dead code.

---

### V-016 — AppServiceProvider Registers SupportTicket Services After Phase 7.2
**Severity:** MEDIUM  
**File:** `app/Providers/AppServiceProvider.php:10-16`

The legacy `SupportTicketServiceInterface` → `SupportTicketService` binding was supposed to be superseded by the canonical tickets module. It remains active. The canonical module's `TicketService` uses `tickets` table; the legacy `SupportTicketService` uses `support_tickets` table. These may be different tables depending on migration history.

---

### V-017 — TenantMarketplaceController Referenced in Routes But Not Imported
**Severity:** MEDIUM  
**File:** `routes/api.php:78-82`
```php
Route::get('modules', [\App\Http\Controllers\Api\TenantMarketplaceController::class, 'modules'])
```
This controller is referenced inline without a `use` statement at the top of the file.

---

## LOW VIOLATIONS

### V-018 — Phase Comments in Production Code
**Severity:** LOW  
Multiple files contain `// PHASE 6.95 — TASK 1:`, `// PHASE 7.1 —` etc. These are documentation within code that should be in git history / CHANGELOG.

### V-019 — Laravel Name `laravel/laravel` in composer.json
**Severity:** LOW  
**File:** `composer.json:2` — `"name": "laravel/laravel"` — platform has not been renamed from the Laravel skeleton default.

### V-020 — Persian-only Error Messages in API Middleware
**Severity:** LOW  
**File:** `app/Http/Middleware/AuthenticateTenantApi.php` — all error messages are in Persian only. Multi-tenant distribution to non-Persian clients would require changes.
