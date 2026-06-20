# DUPLICATE NOTIFICATION AUDIT
> Generated: 2026-06-20 | Scope: saas-platform + customer-site

---

## EXECUTIVE SUMMARY

A comprehensive audit of both repositories found that session flash messages were rendered **twice** on the same page in 51 view files (15 in saas-platform, 36 in customer-site). The root cause: each admin layout already renders all session keys once at the top of `<main>`, but individual page views also rendered them independently inside `@section('content')`. Since Laravel's `session()` helper does NOT consume flash data on read (it is only cleared on the next request), both calls return the same value — producing two visible notifications per action.

**Result: Every affected page showed 2 notifications per user action. All fixed.**

---

## SAAS-PLATFORM AUDIT

### Layout-Level Renderers (Correct — Single Source)

| Layout | Lines | Keys | Notes |
|--------|-------|------|-------|
| `layouts/admin.blade.php` | 538–591 | success, error, info, warning, $errors | Primary admin container. Now has auto-dismiss. |
| `layouts/app.blade.php` | 69–91 | success, error, $errors | Primary app container. |
| `layouts/auth-login.blade.php` | 228–232 | success, error | Auth login card. |

### Duplicate Blocks Removed (15 files)

| File | Key(s) Removed | Severity |
|------|----------------|----------|
| `admin/lifecycle/settings.blade.php` | success | HIGH — double render |
| `admin/lifecycle/overview.blade.php` | success | HIGH |
| `admin/lifecycle/index.blade.php` | success | HIGH |
| `admin/resource-governance/site.blade.php` | success | HIGH |
| `admin/resource-governance/settings.blade.php` | success, $errors | HIGH |
| `admin/resource-governance/index.blade.php` | success | HIGH |
| `admin/studio-imports/index.blade.php` | success | HIGH |
| `admin/theme-templates/show.blade.php` | success | HIGH |
| `admin/theme-templates/index.blade.php` | success | HIGH |
| `admin/theme-packs/show.blade.php` | success | HIGH |
| `admin/theme-packs/index.blade.php` | success, $errors | HIGH |
| `admin/theme-builder/ai-generator-result.blade.php` | success | HIGH |
| `admin/theme-builder/ai-confirm.blade.php` | error | HIGH |
| `admin/theme-builder/ai-analyze.blade.php` | error | HIGH |
| `admin/theme-builder/reverse-engineer.blade.php` | error | HIGH |

### Orphaned Components (Never Used)

| File | Issue |
|------|-------|
| `resources/views/components/flash.blade.php` | Not `@include`d or used as `<x-flash>` anywhere — dead code |
| `resources/views/components/alert.blade.php` | Not included anywhere — dead code |

### JS Toast Systems (Pre-existing, Not Duplicates)

| System | Location | Trigger | Status |
|--------|----------|---------|--------|
| `x-core-ui::toast` | `core-utils` package | `$dispatch('ui-toast', {...})` | Valid — used by forms module |
| `window.showToast()` | `forms/core-ui/toast` | Direct function call | Valid — forms builder |
| Alpine `this.showToast()` | `canvas.blade.php` | Internal canvas method | Valid — isolated to canvas |
| `Alpine.store('toast')` call | `ai-runtime-store` | Store call (broken) | Dead code — store never registered |

---

## CUSTOMER-SITE AUDIT

### Layout-Level Renderers (Correct — Single Source)

| Layout | Lines | Keys | Notes |
|--------|-------|------|-------|
| `admin/layout.blade.php` | 252–295 | success, warning, error, info, $errors | Primary admin container. Now has auto-dismiss + close button. |
| `account/layout.blade.php` | 341–373 | success, error, info, warning, $errors | Primary account container. |
| `themes/default/layout.blade.php` | 521–570 | success, error, warning, info | Front public theme. Fixed: warning+info added. Now has auto-dismiss. |
| `layouts/auth-login.blade.php` | 230–235 | success, error | Auth login. |

### Duplicate Blocks Removed (36 admin + 2 account + 1 front = 39 files)

**Admin pages (admin layout already covers these):**

| File | Key(s) Removed |
|------|----------------|
| `admin/access-lists/gates-index.blade.php` | success |
| `admin/access-lists/lists-index.blade.php` | success |
| `admin/activity-log/index.blade.php` | error |
| `admin/categories/index.blade.php` | success |
| `admin/conversation-merge/permissions.blade.php` | success, error |
| `admin/coupons/index.blade.php` | success |
| `admin/courses/index.blade.php` | success |
| `admin/crm/contacts/index.blade.php` | success |
| `admin/crm/contacts/show.blade.php` | success |
| `admin/education-center/manage.blade.php` | success, $errors |
| `admin/homepage-videos/index.blade.php` | success |
| `admin/installments/orders-index.blade.php` | success, error |
| `admin/installments/orders-show.blade.php` | success |
| `admin/installments/plans-index.blade.php` | success, error |
| `admin/inventory/items/index.blade.php` | success |
| `admin/inventory/warehouses/index.blade.php` | success |
| `admin/module-store/index.blade.php` | warning (kept $error view var) |
| `admin/orders/show.blade.php` | success, error |
| `admin/payment-gateways/index.blade.php` | success, error |
| `admin/profile/index.blade.php` | success |
| `admin/reviews/index.blade.php` | success |
| `admin/site-content/edit.blade.php` | success |
| `admin/sms/index.blade.php` | success |
| `admin/support/_legacy/history.blade.php` | success, error |
| `admin/support/_legacy/index.blade.php` | success (kept $error view var) |
| `admin/support/_legacy/show.blade.php` | success |
| `admin/theme-design/canvas.blade.php` | success, error |
| `admin/theme-design/index.blade.php` | success |
| `admin/theme-marketplace/index.blade.php` | success, error (kept $error view var) |
| `admin/theme-marketplace/my-licenses.blade.php` | session('success') from combined condition; session('error') (kept $purchased and $error view var) |
| `admin/translations/index.blade.php` | success |
| `admin/unified-support/create.blade.php` | error |
| `admin/user-tickets/index.blade.php` | success |
| `admin/user-tickets/show.blade.php` | success, error (foreach loop) |
| `admin/users/chat.blade.php` | success |
| `admin/users/index.blade.php` | success |

**Account pages (account layout already covers these):**

| File | Key(s) Removed |
|------|----------------|
| `account/subscriptions/members.blade.php` | success, error |
| `account/subscriptions/upgrade-plans.blade.php` | error |

**Front/Theme pages:**

| File | Action |
|------|--------|
| `themes/default/shop/cart.blade.php` | Removed duplicate success, error (already in layout) |

### Preserved (Page-Specific Logic, Not Session Flash)

These were NOT removed because they display page-specific view variables, not session flash:

| File | Preserved |
|------|-----------|
| `admin/module-store/index.blade.php` | `@if($error)` — view variable from controller |
| `admin/theme-marketplace/index.blade.php` | `@if(!empty($error))` — view variable |
| `admin/theme-marketplace/my-licenses.blade.php` | `@if(!empty($purchased))` — purchase confirmation; `@if(!empty($error))` — view variable |
| `admin/support/_legacy/index.blade.php` | `@if($error)` — view variable |

---

## BUG: Missing Keys in themes/default/layout.blade.php

**FIXED**: The front-facing theme layout was missing `session('warning')` and `session('info')` rendering. Controllers dispatching `->with('info', ...)` (10 calls from `CourseController` etc.) or `->with('warning', ...)` would silently drop the message. Both keys now rendered.
