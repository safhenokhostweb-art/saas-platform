# Forms Module — Frontend Runtime Trace
## Phase 9.1 Audit | 2026-05-27

---

## 1. Authority Baseline

**Canonical view authority:** `packages/saas-platform/modules/forms/resources/views/`
**Blade namespace:** `forms::`
**Config key:** `config('forms.layouts.*')`
**View composer:** `FormsServiceProvider@boot` — resolves layout per context

---

## 2. Render Chain Map

### 2.1 Platform Admin (saas-platform)

```
Request → web middleware → auth → admin → FormController / FormBuilderController / ...
       ↓
view('forms::admin.forms.INDEX')
  @extends(config('forms.layouts.admin'))
      → resolves to: env('FORMS_LAYOUT_ADMIN', 'layouts.admin')
      → saas-platform layout: resources/views/layouts/admin.blade.php

COMPONENTS USED:
  @include('forms::admin.forms.guide')      ← package partial
  core-ui::components.form-status-pill      ← core-ui namespace (Phase 9.1+)
  core-ui::components.empty-state          ← core-ui namespace (Phase 9.1+)
  <x-persian-date-input>                   ← host app component
```

### 2.2 Tenant Admin (customer-site)

```
Request → web middleware → auth → admin → (same FormsModule controllers)
       ↓
ViewComposer fires (FormsServiceProvider@boot):
  PlatformContextService::isTenantAdmin() === true
  → config(['forms.layouts.admin' => config('forms.layouts.admin_tenant')])
  → resolves to: env('FORMS_LAYOUT_ADMIN_TENANT', 'admin.layout')
  → customer-site layout: customer-site/resources/views/layouts/admin.blade.php (or equivalent)

SAME package views render — only layout shell differs.
SAME components render.
DIFFERENCE: @platform blocks hidden, @tenant blocks visible.
```

### 2.3 Public Frontend

```
Request → web middleware → module:forms → FormFrontendController@show
       ↓
view('forms::site.forms.show')
  @extends(config('forms.layouts.site'))
      → resolves to: env('FORMS_LAYOUT_SITE', 'themes.default.layout')
      → host theme layout

PARTIALS:
  @include('forms::site.forms._field', ...)   ← package partial
  <x-persian-captcha-challenge>               ← host app component
  <x-persian-captcha-math>                    ← host app component
  <x-persian-date-input>                      ← host app component
```

---

## 3. Complete File Inventory

### 3.1 Admin Views (Canonical Package)

| File | Purpose | Layout |
|------|---------|--------|
| `admin/forms/index.blade.php` | Forms grid + empty state | `forms.layouts.admin` |
| `admin/forms/create.blade.php` | Creation wizard + template picker | `forms.layouts.admin` |
| `admin/forms/edit.blade.php` | Redirect to builder | `forms.layouts.admin` |
| `admin/forms/preview.blade.php` | Iframe preview | `forms.layouts.admin` |
| `admin/forms/builder.blade.php` | 3-panel enterprise builder | `forms.layouts.admin` (fullscreen) |
| `admin/forms/sites.blade.php` | Form↔site assignment | `forms.layouts.admin` |
| `admin/forms/guide.blade.php` | Help modal partial | included |
| `admin/forms/_partials/field-icon.blade.php` | SVG field-type icon | partial |
| `admin/forms/submissions/index.blade.php` | Submissions table + drawer | `forms.layouts.admin` |
| `admin/forms/analytics.blade.php` | Chart.js analytics | `forms.layouts.admin` |
| `admin/forms/automation/index.blade.php` | Automation rules | `forms.layouts.admin` |
| `admin/forms/templates/index.blade.php` | Template gallery | `forms.layouts.admin` |

### 3.2 Site Views (Canonical Package)

| File | Purpose | Layout |
|------|---------|--------|
| `site/forms/index.blade.php` | Public forms listing | `forms.layouts.site` |
| `site/forms/show.blade.php` | Multi-step form renderer | `forms.layouts.site` |
| `site/forms/_field.blade.php` | Field type renderer partial | included |

### 3.3 Core-UI Components (Phase 9.1, registered as `core-ui::`)

| File | Component Tag | Purpose |
|------|--------------|---------|
| `core-ui/components/form-status-pill.blade.php` | `<x-core-ui::form-status-pill>` | Active/inactive status badge |
| `core-ui/components/autosave-indicator.blade.php` | `<x-core-ui::autosave-indicator>` | Save state display |
| `core-ui/components/empty-state.blade.php` | `<x-core-ui::empty-state>` | Empty state card |
| `core-ui/components/validation-badge.blade.php` | `<x-core-ui::validation-badge>` | Field validation indicator |
| `core-ui/components/analytics-card.blade.php` | `<x-core-ui::analytics-card>` | Metric card |
| `core-ui/components/section-header.blade.php` | `<x-core-ui::section-header>` | Section divider with label |
| `core-ui/components/draggable-handle.blade.php` | `<x-core-ui::draggable-handle>` | Drag handle SVG |
| `core-ui/components/toast.blade.php` | `<x-core-ui::toast>` | Toast notification |
| `core-ui/components/skeleton-card.blade.php` | `<x-core-ui::skeleton-card>` | Loading skeleton |
| `core-ui/components/field-card.blade.php` | `<x-core-ui::field-card>` | Builder field card wrapper |

---

## 4. Routes → Controllers → Views Chain

```
GET  /admin/forms                     → FormController@index          → forms::admin.forms.index
GET  /admin/forms/create              → FormController@create         → forms::admin.forms.create
POST /admin/forms                     → FormController@store          → redirect to builder
GET  /admin/forms/{form}/builder      → FormBuilderController@show    → forms::admin.forms.builder
GET  /admin/forms/{form}/preview      → FormController@preview        → forms::admin.forms.preview
GET  /admin/forms/{form}/sites        → FormSiteController@edit       → forms::admin.forms.sites
GET  /admin/forms/submissions         → FormSubmissionController@index → forms::admin.forms.submissions.index
GET  /admin/forms/{form}/analytics    → FormAnalyticsController@show  → forms::admin.forms.analytics
GET  /admin/forms/{form}/automation   → FormAutomationController@index → forms::admin.forms.automation.index
GET  /admin/forms/templates           → FormTemplateController@index  → forms::admin.forms.templates.index
GET  /forms                           → FormFrontendController@index  → forms::site.forms.index
GET  /forms/{slug}                    → FormFrontendController@show   → forms::site.forms.show
POST /forms/{slug}                    → FormFrontendController@submit → redirect back
```

---

## 5. Alpine.js Components Inventory

| Component Name | File | Responsibilities |
|----------------|------|-----------------|
| `createForm()` | `create.blade.php` | Slug sync, template select, submit state |
| `formBuilder()` | `builder.blade.php` | Field CRUD, drag-drop, undo/redo, autosave, inspector |
| `submissionsManager()` | `submissions/index.blade.php` | Bulk select, detail drawer, column sort |
| `formRenderer()` | `site/forms/show.blade.php` | Multi-step navigation, submission state |

---

## 6. External JS Assets

| Asset | Where Used | Loaded Via |
|-------|-----------|------------|
| SortableJS 1.15.0 (CDN) | builder, submissions | `@push('scripts')` CDN |
| Chart.js (CDN) | analytics.blade.php | `@push('scripts')` CDN |
| Persian Datepicker | submissions, site/show | `@push('scripts')` local vendor |
| jQuery (for datepicker) | submissions, site/show | `@push('scripts')` local vendor |
| hCaptcha | site/show (optional) | `@push('scripts')` CDN |

---

## 7. Blade Directives (Platform/Tenant Context)

```blade
@platform / @endplatform       — renders ONLY in saas-platform admin context
@tenant / @endtenant           — renders ONLY in customer-site admin context
@capability('name') / @endcapability — renders only when capability granted
@platformfeature('feature')    — renders only when platform feature active
```

Implemented in: `FormsServiceProvider@boot` via `Blade::if()`
Resolved by: `PlatformContextService::isCentralAdmin()` / `isTenantAdmin()`

---

## 8. Divergence Points — AUDIT RESULT

### ✅ CLEAN — No violations found

| Check | Result |
|-------|--------|
| `customer-site/resources/views/admin/forms/` | **ABSENT** — compliant |
| `customer-site/resources/views/site/forms/` | **ABSENT** — compliant |
| `customer-site/resources/views/vendor/forms/` | **ABSENT** — compliant |
| `saas-platform/resources/views/admin/forms/` | **ABSENT** (legacy deleted) — compliant |
| Package views as sole render authority | **CONFIRMED** |
| Layout resolves dynamically per context | **CONFIRMED** |

### ⚠ MINOR — Pre-Phase-9.1 items

| Issue | File | Status |
|-------|------|--------|
| `<style>` blocks in builder head | `admin/forms/builder.blade.php` | Required for SortableJS states — acceptable |
| `account/forms/index.blade.php` in customer-site | customer-site stub | Account-level user view, not admin/forms — acceptable; upgraded in Phase 9.1 |
| CDN SortableJS reference | builder + submissions | Acceptable; should be local in production |

---

## 9. Layout Authority Map

```
Platform Admin:  env('FORMS_LAYOUT_ADMIN')        → saas-platform layouts/admin
Tenant Admin:    env('FORMS_LAYOUT_ADMIN_TENANT')  → customer-site admin.layout
Site Frontend:   env('FORMS_LAYOUT_SITE')          → customer-site themes/default/layout
```

Both admin contexts use **identical** package views.
Tenant context differences are **ONLY** via `@platform` / `@tenant` directives.
No separate blade files for tenant vs platform.

---

## 10. Frontend Parity Score (Pre-9.1)

| Dimension | Score | Notes |
|-----------|-------|-------|
| View authority | 100/100 | All views canonical in package |
| Layout resolution | 100/100 | Dynamic per context, zero hardcoding |
| Component reuse | 70/100 | No core-ui namespace yet |
| UI consistency | 85/100 | Good baseline, missing skeleton/toast system |
| Tenant isolation | 100/100 | @platform/@tenant directives fully enforced |
| Builder UX | 88/100 | Missing field search, conditional logic UI |
| Accessibility | 75/100 | Missing focus-visible, ARIA improvements |
| Documentation | 0/100 | No Phase 9.1 docs yet |

**Overall pre-9.1 parity score: 77/100**

---

*Generated by Phase 9.1 frontend audit — 2026-05-27*
