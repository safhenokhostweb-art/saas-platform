# Forms Module — Frontend Divergence Audit
**Phase 9.0 | Date: 2026-05-27**

---

## Canonical Frontend Authority

The Forms module follows **Single Frontend Authority** (SFA): all Blade views live exclusively in the package at:

```
packages/saas-platform/modules/forms/resources/views/
```

Both projects (saas-platform and customer-site) render from this single source.
Differences between platform admin and tenant admin are handled exclusively via:
- `@platform` / `@tenant` Blade directives (PlatformContextService)
- `@capability('...')` checks
- `config('forms.layouts.*')` for layout resolution
- Gate/Policy checks in controllers

---

## Audit Findings

### 1. Legacy Views in Root Project (VIOLATION → RESOLVED)

**Location:** `resources/views/admin/forms/_legacy/`

**Files found:**
- `create.blade.php`
- `edit.blade.php`
- `guide.blade.php`
- `index.blade.php`
- `preview.blade.php`
- `sites.blade.php`
- `submissions-index.blade.php`

**Issue:** These are outdated copies of package views from before Phase 6.95 (layout resolution was hardcoded to `layouts.admin`, no `@platform`/`@tenant` directives, no config-driven layouts).

**Resolution:** Entire `_legacy/` directory **deleted** as part of Phase 9.0. ✅

---

### 2. Empty Submissions Directory in Root Project (CLEANED)

**Location:** `resources/views/admin/forms/submissions/` (empty)

**Resolution:** Directory **deleted**. ✅

---

### 3. Package Boundary Violation — `\App\Helpers\Jalali` (RESOLVED)

**Location:** `resources/views/admin/forms/submissions/index.blade.php` (package view)
**Line:** `{{ \App\Helpers\Jalali::formatFa($item->created_at, 'Y/m/d H:i') }}`

**Issue:** Package view directly calls an `App\` class, violating module isolation.

**Resolution:** Replaced with `config('forms.date_formatter')` callable pattern + Carbon fallback. ✅

---

### 4. Customer-Site Forms Views (COMPLIANT)

**Location:** `resources/views/account/forms/index.blade.php`

**Content:** This is a tenant account stub that displays a redirect message — it is NOT a Forms module view and does NOT duplicate any package views. This file belongs to the customer-site's own account area, not the Forms module.

**Status:** No action required. ✅

---

### 5. Layout Resolution (COMPLIANT)

The FormsServiceProvider registers a view composer that dynamically resolves `config('forms.layouts.admin')` based on `PlatformContextService::isTenantAdmin()`. Both projects share identical package views — the correct layout is injected at runtime.

| Context | Layout Resolved |
|---------|----------------|
| saas-platform (platform admin) | `layouts.admin` |
| customer-site (tenant admin) | `admin.layout` |
| Public form | `themes.default.layout` |

**Status:** Compliant. ✅

---

### 6. CSS/JS Divergence (RESOLVED)

Previous state: submissions view loaded jQuery + Persian Datepicker from unpkg CDN.
New state: All assets loaded via vendor path or bundled — no external CDN for admin builder views.

---

## Post-Phase-9.0 Frontend Inventory

All views canonical in package:

```
packages/saas-platform/modules/forms/resources/views/
├── admin/
│   └── forms/
│       ├── index.blade.php           ← Enterprise forms listing (REBUILT)
│       ├── create.blade.php          ← Form creation wizard (REBUILT)
│       ├── edit.blade.php            ← Redirects to builder (REBUILT)
│       ├── builder.blade.php         ← Enterprise drag/drop builder (NEW)
│       ├── preview.blade.php         ← Form preview (existing)
│       ├── sites.blade.php           ← Site assignment (existing)
│       ├── guide.blade.php           ← Module guide (existing)
│       ├── analytics.blade.php       ← Analytics dashboard (NEW)
│       ├── automation/
│       │   └── index.blade.php       ← Automation rules UI (NEW)
│       ├── templates/
│       │   └── index.blade.php       ← Template library (NEW)
│       └── submissions/
│           └── index.blade.php       ← Advanced submissions (REBUILT)
└── site/
    └── forms/
        ├── index.blade.php           ← Public forms listing (REBUILT)
        └── show.blade.php            ← Professional renderer (REBUILT)
```

---

## Governance Enforcement

A `FormsFrontendGovernanceValidationService` + `forms:validate-frontend-governance` Artisan command
verifies at runtime:

1. No duplicate views in consuming projects
2. All package views use `config('forms.layouts.*')` for layout resolution
3. No `App\` class references inside package views
4. All admin views use `@platform`/`@tenant` for context-based branching
5. Dark-mode readiness (CSS custom property compliance)
6. No hardcoded external CDN URLs in admin views

---

## Compliance Matrix

| Rule | Status |
|------|--------|
| No duplicated views | ✅ Legacy deleted |
| No platform-only frontend | ✅ All views shared |
| No tenant-only frontend | ✅ All views shared |
| Config-driven layouts | ✅ FormsServiceProvider view composer |
| @platform/@tenant for branching | ✅ Used in all admin views |
| No App\ in package views | ✅ Jalali violation fixed |
| Dark-mode readiness | ✅ CSS custom properties used |
| Accessible HTML | ✅ ARIA labels, sr-only, role attrs |
| Keyboard navigable | ✅ Focus styles, tab order |
| Responsive | ✅ Tailwind responsive prefixes |
