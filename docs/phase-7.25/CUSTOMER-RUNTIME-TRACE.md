# PHASE 7.25 — Customer-Site Active Runtime Trace

**Generated:** 2026-05-25  
**Scope:** c:\laragon\www\customer-site — full active runtime audit  
**Purpose:** Canonical baseline before tenant-consumption enforcement

---

## 1. Active Route Summary

**Framework:** Laravel 12.0  
**Route file:** `routes/web.php` (465 lines — single file, no api.php)  
**Total route groups:** 40+

### 1.1 Module Routes — Forms (CANONICAL ✅)

| HTTP | URL | Controller | Status |
|------|-----|------------|--------|
| GET/POST | `/admin/forms` | `SaaSPlatform\Modules\Forms\Http\Controllers\FormController` | Canonical |
| POST | `/admin/forms/{form}/fields` | `FormController` (package) | Canonical |
| GET | `/admin/forms/submissions` | `FormSubmissionController` (package) | Canonical |

**Assessment:** Forms routes already use canonical package controllers. No legacy ownership.

### 1.2 Module Routes — Support/Tickets (LEGACY ⚠️)

| HTTP | URL | Route Name | Controller |
|------|-----|------------|------------|
| GET | `/admin/support` | `admin.support.index` | `Admin\SupportController@index` |
| GET | `/admin/support/create` | `admin.support.create` | `Admin\SupportController@create` |
| POST | `/admin/support` | `admin.support.store` | `Admin\SupportController@store` |
| GET | `/admin/support/history` | `admin.support.history` | `Admin\SupportController@history` |
| GET | `/admin/support/{ticketId}` | `admin.support.show` | `Admin\SupportController@show` |
| POST | `/admin/support/{ticketId}/reply` | `admin.support.reply` | `Admin\SupportController@reply` |

**Middleware:** `permission:support`, `throttle:30,1`  
**Data source:** HTTP API via `SupportApiClient` → `{PLATFORM_URL}/api/support-tickets`  
**Assessment:** Routes use local controller + local views. Controller is an API proxy (no local DB). Views are @deprecated (PHASE 7.15).

---

## 2. Active Controller Map

### 2.1 Module-Related Controllers — Status

| Controller | Path | Module | Ownership | Status |
|------------|------|--------|-----------|--------|
| `Admin\SupportController` | `app/Http/Controllers/Admin/SupportController.php` | Tickets | LOCAL (proxy) | ⚠️ Legacy — API proxy, no business logic, renders local views |
| `Account\FormController` | `app/Http/Controllers/Account/FormController.php` | Forms | LOCAL | ℹ️ Account-level form view for end users |
| All others | various | Non-module | LOCAL | ✅ Appropriate — no module ownership |

### 2.2 Forms Package Controllers (Active via Routes)

- `SaaSPlatform\Modules\Forms\Http\Controllers\FormController` — CRUD for forms
- `SaaSPlatform\Modules\Forms\Http\Controllers\FormSubmissionController` — submissions

---

## 3. Active View Map

### 3.1 Support Views (Legacy — @deprecated)

| File | Deprecated Since | Canonical Replacement | Active? |
|------|-----------------|----------------------|---------|
| `resources/views/admin/support/index.blade.php` | PHASE 7.15 | `tickets::site.tickets.index` | YES |
| `resources/views/admin/support/show.blade.php` | PHASE 7.15 | `tickets::site.tickets.show` | YES |
| `resources/views/admin/support/create.blade.php` | PHASE 7.15 | `tickets::site.tickets.create` | YES |
| `resources/views/admin/support/history.blade.php` | PHASE 7.15 | `tickets::site.tickets.index` | YES |
| `resources/views/admin/support/guide.blade.php` | PHASE 7.15 | — | YES |

### 3.2 Forms Views

- Forms views are rendered via `FormController` (package) — they render `forms::*` namespace views
- No local forms module views exist
- ✅ Forms view authority is canonical

### 3.3 Published Vendor Views

`resources/views/vendor/` — **DOES NOT EXIST**

No package views have been published to customer-site. All package views are resolved directly from their package path via view namespace resolution. This is correct behavior.

---

## 4. Admin Layout & Sidebar

**Primary layout:** `resources/views/admin/layout.blade.php`  
**Sidebar built by:** `AdminMenu::getGroups()` (app/Helpers/AdminMenu.php — 568 lines)

### 4.1 AdminMenu Structure

AdminMenu is a **hardcoded static helper**. It does NOT use `ModuleNavigationService`.

Menu groups (hardcoded order):
1. اصلی (dashboard)
2. محتوا و ساختار سایت (pages, menu, categories, posts, advanced_search)
3. فروشگاه (products, orders, coupons)
4. **فرم‌ها و ارتباط با مخاطب (forms, forms_submissions — SHOULD BE manifest-driven)**
5. اشتراک و آموزش (subscriptions, courses)
6. کسب‌وکار و نمایش (invoice, business_card, social_links)
7. ظاهر، امنیت و تنظیمات (settings, theme, captcha)
8. **لایسنس، ماژول‌ها و پشتیبانی (support, support_history — SHOULD BE manifest-driven)**
9. ماژول‌های از پلتفرم (dynamic from license.modules)

### 4.2 Module Menu Items (Hardcoded — VIOLATION)

| Key | Route | Should come from |
|-----|-------|-----------------|
| `forms` | `admin.forms.index` | `config('module-manifest-forms').menu[0]` |
| `forms_submissions` | `admin.forms.submissions.index` | `config('module-manifest-forms').menu[1]` |
| `support` | `admin.support.index` | `config('module-manifest-tickets').menu[0]` |
| `support_history` | `admin.support.history` | `config('module-manifest-tickets').menu[1]` |

---

## 5. API Runtime

### 5.1 SupportApiClient (c:\laragon\www\customer-site\app\Services\SupportApiClient.php)

**HTTP base URL:** `config('site.platform_url')`  
**Auth:** `license_key` + `domain` in request payload

| Method | Endpoint Called | Purpose |
|--------|----------------|---------|
| `index()` | `GET {platform}/api/support-tickets` | List tenant's tickets |
| `store()` | `POST {platform}/api/support-tickets` | Create ticket |
| `reply()` | `POST {platform}/api/support-tickets/{id}/reply` | Add reply |

**Status:** ALL calls use the LEGACY API endpoint `/api/support-tickets` → ⚠️ Must migrate to `/api/tickets`

### 5.2 JavaScript/AJAX

`resources/js/app.js` imports `support-chat.js` (Alpine.js component).  
The support chat component uses **traditional form POST submission** — no AJAX, no fetch(), no axios calls to ticket APIs.  
✅ No JavaScript URL changes required.

---

## 6. Package Dependencies

| Package | Version | Mount | Status |
|---------|---------|-------|--------|
| `saas-platform/core-utils` | @dev | Symlink | ✅ Active |
| `saas-platform/modules-forms` | @dev | Symlink | ✅ Active |
| `saas-platform/modules-tickets` | @dev | Symlink | ✅ Active |

---

## 7. Identified Violations

| # | Violation | Location | Severity |
|---|-----------|----------|----------|
| V1 | Local support views active (should be package views) | `resources/views/admin/support/` | HIGH |
| V2 | AdminMenu hardcodes module menu items | `app/Helpers/AdminMenu.php` | HIGH |
| V3 | SupportApiClient calls legacy `/api/support-tickets` | `app/Services/SupportApiClient.php` | HIGH |
| V4 | No vendor view namespace published (expected) | `resources/views/vendor/` | INFO |
| V5 | `Admin\SupportController` renders local views | `app/Http/Controllers/Admin/SupportController.php` | MEDIUM |

---

## 8. Compliant Items (No Action Required)

| Item | Status |
|------|--------|
| Forms routes use package controllers | ✅ Canonical |
| Forms views resolve from `forms::` namespace | ✅ Canonical |
| Tickets package registered via composer | ✅ Available |
| Core-utils registered (blade directives, services) | ✅ Available |
| No local ticket DB models | ✅ Correct (tenant consumer pattern) |
| Rate limiters in AppServiceProvider | ✅ Tenant-owned, appropriate |
| Alpine support-chat has no rogue API calls | ✅ Clean |
| License validation middleware chain | ✅ Tenant-appropriate |
