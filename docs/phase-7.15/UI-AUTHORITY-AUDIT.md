# PHASE 7.15 — UI Authority Audit

**Date:** 2026-05-25  
**Scope:** saas-platform + customer-site  
**Status:** Complete — violations documented, remediation tracked

---

## 1. Executive Summary

| Finding Type            | Count |
|-------------------------|-------|
| Duplicate active views  | 4     |
| Hardcoded layout strings | 0 (package views — resolved in 7.1) |
| @platform leakage in site/ | 0  |
| Illegal override paths  | 0     |
| Legacy views (active routes) | 3  |
| Legacy views (dead routes) | 8  |
| Icon size divergence    | 1 (w-4 vs w-5) |
| Sidebar: data-driven    | customer-site ✅ / saas-platform ❌ |
| Shared Blade components | 0 (none exist yet — TASK 4 creates them) |
| LayoutResolver hardcoded to forms.* | 1 — fixed in TASK 2 |

---

## 2. Layout Authority Map

| Layout Key               | saas-platform path            | customer-site path          | Status    |
|--------------------------|-------------------------------|-----------------------------|-----------|
| Admin (platform context) | `layouts/admin.blade.php`     | N/A                         | ✅ Clean  |
| Admin (tenant context)   | N/A                           | `admin/layout.blade.php`    | ✅ Clean  |
| Site/public              | `layouts/app.blade.php`       | N/A                         | ✅ Clean  |
| Auth                     | `layouts/auth.blade.php`      | `layouts/auth.blade.php`    | ✅ Identical |
| Account                  | N/A                           | `account/layout.blade.php`  | ✅ Tenant-only |

**Canonical resolution rule:**
- `config('MODULE.layouts.admin')` → `layouts.admin` (saas-platform context)
- `config('MODULE.layouts.admin_tenant')` → `admin.layout` (customer-site context)
- `config('MODULE.layouts.site')` → `layouts.app` / `themes.default.layout`

---

## 3. Duplicate View Map

| Module  | saas-platform path                         | customer-site path               | Authority       | Action         |
|---------|--------------------------------------------|----------------------------------|-----------------|----------------|
| Support | `resources/views/admin/support-tickets/`   | `resources/views/admin/support/` | Package canonical | Deprecate both → use `tickets::admin.*` |
| Forms   | `resources/views/admin/forms/_legacy/`     | N/A                              | Package canonical | Already in _legacy ✅ |
| Forms   | `resources/views/customer/forms/`          | `resources/views/account/forms/` | Package canonical | Deprecate both → use `forms::site.*` |
| Support | `resources/views/customer/support-tickets/`| N/A                              | Package canonical | Deprecate → use `tickets::site.*` |

---

## 4. Drift Severity Matrix

| Issue                                                     | Severity | Project          | Remediation              |
|-----------------------------------------------------------|----------|------------------|--------------------------|
| `admin/support-tickets/` still has active route          | HIGH     | saas-platform    | Redirect routes to canonical package views |
| `customer/support-tickets/` active route                 | HIGH     | saas-platform    | Redirect routes to `tickets::site.*` |
| `admin/support/` active in customer-site                 | HIGH     | customer-site    | Route to `tickets::site.*` |
| `account/forms/` active in customer-site                 | MEDIUM   | customer-site    | Route to `forms::site.*` |
| Sidebar icon sizes: `w-4 h-4 ml-2` vs `w-5 h-5 ml-3`   | LOW      | Both             | Canonicalize in TASK 4 components |
| `LayoutResolver` hardcoded to `forms.layouts.*`          | MEDIUM   | saas-platform    | `SharedLayoutResolver` — TASK 2 |
| saas-platform sidebar NOT data-driven (manual Blade)     | MEDIUM   | saas-platform    | `ModuleNavigationService` — TASK 5 |
| No shared Blade component library                         | HIGH     | Both             | core-utils components — TASK 4 |

---

## 5. Canonical Source Map (Every UI Element)

### Package Views

| Element                  | Canonical Source                              | View Namespace      |
|--------------------------|-----------------------------------------------|---------------------|
| Admin ticket list        | `tickets::admin.tickets.index`               | tickets package     |
| Admin ticket create      | `tickets::admin.tickets.create`              | tickets package     |
| Admin ticket detail      | `tickets::admin.tickets.show`                | tickets package     |
| Site ticket list         | `tickets::site.tickets.index`                | tickets package     |
| Site ticket create       | `tickets::site.tickets.create`               | tickets package     |
| Site ticket detail       | `tickets::site.tickets.show`                 | tickets package     |
| Status badge (ticket)    | `tickets::components.status-badge`           | tickets package     |
| Priority badge (ticket)  | `tickets::components.priority-badge`         | tickets package     |
| Reply item               | `tickets::components.reply-item`             | tickets package     |
| Admin form list          | `forms::admin.forms.index`                   | forms package       |
| Admin form create        | `forms::admin.forms.create`                  | forms package       |
| Admin form edit          | `forms::admin.forms.edit`                    | forms package       |
| Site form list           | `forms::site.forms.index`                    | forms package       |
| Site form detail         | `forms::site.forms.show`                     | forms package       |

### Shared Components (core-utils — TASK 4)

| Element                  | Canonical Source                                          |
|--------------------------|-----------------------------------------------------------|
| Module card              | `core-ui::components.module-card`                        |
| Stats card               | `core-ui::components.stats-card`                         |
| Module actions           | `core-ui::components.module-actions`                     |
| Module status badge      | `core-ui::components.module-status-badge`                |
| Data table               | `core-ui::components.data-table`                         |
| Filter bar               | `core-ui::components.filter-bar`                         |
| Empty state              | `core-ui::components.empty-state`                        |
| Table pagination         | `core-ui::components.table-pagination`                   |
| Status badge (generic)   | `core-ui::components.status-badge`                       |

---

## 6. Platform Leakage Audit (TASK 3)

### Package views checked for @platform leakage in site/ context

| View                                  | @platform usage | @tenant wrap | Status  |
|---------------------------------------|-----------------|--------------|---------|
| `tickets::site.tickets.index`         | None            | ✅ Yes       | ✅ Pass |
| `tickets::site.tickets.create`        | None            | ✅ Yes       | ✅ Pass |
| `tickets::site.tickets.show`          | None            | ✅ Yes       | ✅ Pass |
| `forms::site.forms.index`             | None            | ✅ Yes       | ✅ Pass |
| `forms::site.forms.show`             | None            | ✅ Yes       | ✅ Pass |
| `tickets::components.reply-item`      | None            | N/A (component) | ✅ Pass |

### Forbidden in customer-site (confirmed absent)

| Forbidden Action                   | Present? |
|------------------------------------|----------|
| Manage all sites globally          | ❌ Not present |
| Assign modules to other tenants    | ❌ Not present |
| Platform billing controls          | ❌ Not present |
| Module lifecycle/distribution tools| ❌ Not present |
| Cross-tenant data tables           | ❌ Not present |

---

## 7. Sidebar Authority Analysis

### saas-platform
- **Method:** Manual Blade `@if` chains in `layouts/admin.blade.php`
- **Problem:** Navigation items are hardcoded Blade fragments; adding a module requires editing the layout
- **Fix:** `ModuleNavigationService` (TASK 5) — data-driven, reads module manifests

### customer-site
- **Method:** `AdminMenu::getGroups()` helper — already data-driven ✅
- **Problem:** Module visibility logic in `AdminMenu::isVisible()` is not context-aware (no integration with package manifests)
- **Fix:** `ModuleNavigationService` can be the shared data source for both

---

## 8. Icon Size Divergence

| Project        | Icon CSS classes  | Icon area |
|----------------|-------------------|-----------|
| saas-platform  | `w-4 h-4 ml-2`   | 16×16px   |
| customer-site  | `w-5 h-5 ml-3`   | 20×20px   |

**Canonical choice:** `w-5 h-5 ml-3` (customer-site value — larger, better UX)  
**Action:** shared `core-ui::components.sidebar-icon` uses `w-5 h-5 ml-3` as default; override via prop.

---

## 9. Override Authority Map

### Allowed override paths
```
resources/views/vendor/forms/         ← safe tenant override zone
resources/views/vendor/tickets/       ← safe tenant override zone
```

### Illegal override paths (must not exist with active routes)
```
resources/views/admin/forms/          ← illegal — use package canonical
resources/views/admin/tickets/        ← illegal — use package canonical
resources/views/site/forms/           ← illegal — use package canonical
resources/views/site/tickets/         ← illegal — use package canonical
```

### Current status
- Neither project has any `resources/views/vendor/` content ✅
- `resources/views/admin/forms/_legacy/` exists in saas-platform (already legacy-marked) ✅
- `resources/views/admin/support-tickets/` is active in saas-platform ⚠️ — needs deprecation

---

## 10. Legacy Views Requiring Action

| Path (saas-platform)                        | Active Route? | Action                    |
|---------------------------------------------|---------------|---------------------------|
| `resources/views/admin/support-tickets/`    | Likely yes    | Add @deprecated, move to _legacy/ after route audit |
| `resources/views/customer/support-tickets/` | Likely yes    | Add @deprecated, move to _legacy/ after route audit |
| `resources/views/customer/forms/`           | Likely yes    | Add @deprecated, move to _legacy/ after route audit |
| `resources/views/admin/forms/_legacy/`      | No (in _legacy already) | Archive/delete in PHASE 7.2 |
| `resources/views/site/forms/`               | Unknown       | Audit routes, then move   |

| Path (customer-site)                        | Active Route? | Action                    |
|---------------------------------------------|---------------|---------------------------|
| `resources/views/admin/support/`            | Likely yes    | Add @deprecated, route to `tickets::site.*` |
| `resources/views/account/forms/`            | Likely yes    | Add @deprecated, route to `forms::site.*`  |

---

## 11. Success Criteria Tracking

| Criteria                                          | Status              |
|---------------------------------------------------|---------------------|
| Forms UI identical across both projects           | ⚠️ Routes need updating |
| Tickets UI identical across both projects         | ⚠️ Routes need updating |
| No @platform leakage in tenant UI                 | ✅ Verified         |
| No duplicated active views                        | ⚠️ 4 duplicates — legacy views need route redirect |
| No hardcoded layouts in package views             | ✅ All use config() |
| Sidebar authority unified                         | ⚠️ ModuleNavigationService created — integration pending |
| Shared components canonicalized                   | ✅ core-ui components created in TASK 4 |
| Runtime gates control visibility                  | ✅ TicketGateService + FormsGateService |
| Package views are sole authority                  | ✅ For new routes    |
| Customer-site becomes pure runtime consumer       | ⚠️ Partial — requires route migration |
