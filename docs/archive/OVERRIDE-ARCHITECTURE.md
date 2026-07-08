# PHASE 6.6 — LEGACY RUNTIME PURGE & OVERRIDE ARCHITECTURE

## INVESTIGATION RESULTS

### 1. Legacy Files Status

**saas-platform:**
- `resources/views/admin/forms/_legacy/` — 7 archived files (create, edit, guide, index, preview, sites, submissions-index)
- `resources/views/admin/forms/submissions/` — empty directory
- `app/Http/Controllers/Admin/FormController.php` — **ACTIVE** (uses `view('admin.forms.*')` — references legacy views)
- `app/Http/Controllers/Admin/FormSubmissionController.php` — **ACTIVE** (uses `view('admin.forms.submissions.*')`)
- `app/Http/Controllers/Admin/FormFieldController.php` — **ACTIVE**
- `app/Http/Controllers/Admin/FormSiteController.php` — **ACTIVE**

**customer-site:**
- No legacy forms views directory
- `app/Http/Controllers/Admin/FormController.php` — **ACTIVE** (uses `view('forms::admin.forms.*')` — references package views)
- `app/Http/Controllers/Admin/FormSubmissionController.php` — **ACTIVE** (uses `view('forms::admin.forms.submissions.*')`)

### 2. Route Analysis

**Both projects** — routes/web.php:
- All Form routes use `SaaSPlatform\Modules\Forms\Http\Controllers\*` (package controllers)
- No legacy `App\Http\Controllers\Admin\FormController` references in routes
- Frontend routes use `FormFrontendController` from package

### 3. Package Authority

**Package controllers** (`packages/saas-platform/modules/forms/src/Http/Controllers/`):
- `FormController.php` — uses `view('forms::admin.forms.*')` and `FormServiceInterface`
- `FormFrontendController.php` — frontend form display
- `FormSiteController.php` — site assignment
- `FormSubmissionController.php` — submission management

**Package views** (`packages/.../resources/views/`):
- `admin/forms/` — create, edit, guide, index, preview, sites (6 files)
- `admin/forms/submissions/` — index (1 file)
- `site/forms/` — index, show (2 files)

### 4. Key Finding: Dual Controller Architecture

Both projects have **active** `App\Http\Controllers\Admin\FormController` that:
- **saas-platform**: Uses `view('admin.forms.*')` — references **legacy archived views** (NOT package views)
- **customer-site**: Uses `view('forms::admin.forms.*')` — references **package views** (correct)

The saas-platform's local FormController is **not routed** (routes point to package controllers), but it still exists as dead code.

---

## OVERRIDE ARCHITECTURE

### LEVEL 1: Package Defaults (Authoritative Source)

```
packages/saas-platform/modules/forms/
├── config/
│   ├── forms.php              ← Layout config, field types, pagination
│   └── module-manifest.php    ← Canonical metadata
├── database/migrations/       ← Schema (authoritative)
├── resources/views/
│   ├── admin/forms/           ← Admin CRUD views (6 files)
│   ├── admin/forms/submissions/ ← Submissions list
│   └── site/forms/            ← Public form views (2 files)
├── routes/web.php             ← All routes (authoritative)
└── src/
    ├── Http/Controllers/      ← All controllers (authoritative)
    ├── Http/Requests/         ← Form requests
    ├── Models/                ← Eloquent models
    ├── Services/              ← Business logic
    └── Repositories/          ← Data access
```

**STATUS: PROTECTED** — Never modify in consuming projects.

### LEVEL 2: Published Safe Overrides (resources/views/vendor/forms)

Published via `php artisan vendor:publish --tag=forms-views`

```
resources/views/vendor/forms/
├── admin/forms/
│   ├── index.blade.php        ← Overrideable
│   ├── create.blade.php       ← Overrideable
│   ├── edit.blade.php         ← Overrideable
│   ├── preview.blade.php      ← Overrideable
│   ├── sites.blade.php        ← Overrideable
│   └── guide.blade.php        ← Overrideable
├── admin/forms/submissions/
│   └── index.blade.php        ← Overrideable
└── site/forms/
    ├── index.blade.php        ← Overrideable
    └── show.blade.php         ← Overrideable
```

**STATUS: SAFE TO OVERRIDE** — Blade views only. Laravel's view namespace resolution gives `resources/views/vendor/forms/` priority over package views.

### LEVEL 3: Config Overrides (config/forms.php)

Published via `php artisan vendor:publish --tag=forms-config`

```php
// config/forms.php
'layouts' => [
    'admin' => 'admin.layouts.app',  // Override admin layout
    'site'  => 'themes.custom.layout', // Override site layout
],
'pagination' => 50,  // Override pagination
```

**STATUS: SAFE TO OVERRIDE** — Config values only affect layout selection and settings.

### LEVEL 4: Tenant-Specific Runtime Customizations

For multi-tenant setups, use the config system:

```php
// In tenant service provider or middleware
config(['forms.layouts.site' => 'tenant-'.$tenantId.'.layout']);
config(['forms.pagination' => $tenant->settings['forms_pagination'] ?? 25]);
```

**STATUS: RUNTIME ONLY** — Never persist in config files.

---

## OVERRIDE PRECEDENCE RULES

```
HIGHEST PRIORITY
    ↓
1. Tenant runtime config overrides
2. Published views (resources/views/vendor/forms/)
3. Published config (config/forms.php)
4. Package defaults (packages/saas-platform/modules/forms/)
    ↓
LOWEST PRIORITY (AUTHORITATIVE FOR CODE)
```

**Rule 1:** Views in `resources/views/vendor/forms/` ALWAYS override package views.
**Rule 2:** Config in `config/forms.php` ALWAYS overrides package config.
**Rule 3:** Controllers, Models, Services, Repositories — NEVER override.
**Rule 4:** Routes — NEVER override (package routes are authoritative).
**Rule 5:** Migrations — NEVER override (package migrations are authoritative).

---

## PROTECTED vs OVERRIDEABLE FILES

### MUST NEVER BE OVERRIDDEN (PROTECTED)

| File | Reason |
|------|--------|
| `src/Http/Controllers/*` | Controller forks break updates |
| `src/Models/*` | Model changes break relationships |
| `src/Services/*` | Business logic must be centralized |
| `src/Repositories/*` | Data access must be centralized |
| `src/Http/Requests/*` | Validation must be centralized |
| `routes/web.php` | Route duplication causes 404s |
| `database/migrations/*` | Schema drift breaks deployments |
| `config/module-manifest.php` | Metadata must be canonical |

### SAFE TO OVERRIDE

| File | Method |
|------|--------|
| `resources/views/admin/forms/*` | `vendor:publish --tag=forms-views` |
| `resources/views/site/forms/*` | `vendor:publish --tag=forms-views` |
| `config/forms.php` (layouts only) | `vendor:publish --tag=forms-config` |

---

## DRIFT PROTECTION STRATEGY

### 1. Drift Detection Command

```bash
php artisan forms:drift-scan
```

This command should:
- Compare checksums of published views vs package views
- Report any published views that differ from package originals
- Flag any files in `resources/views/admin/forms/` (outside vendor) as UNSAFE
- Flag any local controllers that duplicate package functionality

### 2. Anti-Drift Rules

1. **Never** create files in `resources/views/admin/forms/` — use `resources/views/vendor/forms/`
2. **Never** create `App\Http\Controllers\Admin\FormController` — package controllers are authoritative
3. **Always** use `forms::` namespace for view references in consuming projects
4. **Always** publish views via `vendor:publish` for customization

### 3. Update-Safe Workflow

```bash
# Step 1: Publish views for customization
php artisan vendor:publish --tag=forms-views

# Step 2: Customize published views in resources/views/vendor/forms/
# (These survive package updates)

# Step 3: After package update, run drift scan
php artisan forms:drift-scan

# Step 4: Review changes and merge if needed
```

---

## LEGACY PURGE STRATEGY

### Current Legacy Files

**saas-platform:**
- `resources/views/admin/forms/_legacy/` — 7 archived files
- `app/Http/Controllers/Admin/FormController.php` — dead code (not routed)
- `app/Http/Controllers/Admin/FormSubmissionController.php` — dead code (not routed)
- `app/Http/Controllers/Admin/FormFieldController.php` — dead code (not routed)
- `app/Http/Controllers/Admin/FormSiteController.php` — dead code (not routed)

**customer-site:**
- `app/Http/Controllers/Admin/FormController.php` — **ACTIVE** (uses `forms::` views, but duplicates package logic)
- `app/Http/Controllers/Admin/FormSubmissionController.php` — **ACTIVE** (uses `forms::` views, but duplicates package logic)

### Purge Decision

| File | Action | Timeline |
|------|--------|----------|
| `resources/views/admin/forms/_legacy/*` | **DELETE** after stabilization | After 1 release cycle |
| `app/Http/Controllers/Admin/FormController.php` (saas-platform) | **DELETE** — dead code | Immediate |
| `app/Http/Controllers/Admin/FormSubmissionController.php` (saas-platform) | **DELETE** — dead code | Immediate |
| `app/Http/Controllers/Admin/FormFieldController.php` (saas-platform) | **DELETE** — dead code | Immediate |
| `app/Http/Controllers/Admin/FormSiteController.php` (saas-platform) | **DELETE** — dead code | Immediate |
| `app/Http/Controllers/Admin/FormController.php` (customer-site) | **REFACTOR** to extend or delegate to package | After audit |
| `app/Http/Controllers/Admin/FormSubmissionController.php` (customer-site) | **REFACTOR** to extend or delegate to package | After audit |

### Migration Snapshot Strategy

Before deleting legacy files, create a git tag:

```bash
git tag forms-legacy-snapshot-2026-05-24
```

This ensures legacy code is recoverable without polluting the runtime.

---

## FINAL PRODUCTION MODULAR RUNTIME MODEL

```
┌─────────────────────────────────────────────────────────────┐
│                    CONSUMING PROJECT                         │
│  (saas-platform / customer-site)                             │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  LEVEL 4: Tenant Runtime Config                      │   │
│  │  config(['forms.layouts.site' => 'tenant.layout'])   │   │
│  └──────────────────────────────────────────────────────┘   │
│                           ↓                                  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  LEVEL 3: Published Config                           │   │
│  │  config/forms.php (layouts, pagination, etc.)        │   │
│  └──────────────────────────────────────────────────────┘   │
│                           ↓                                  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  LEVEL 2: Published Views (Overrideable)             │   │
│  │  resources/views/vendor/forms/                       │   │
│  └──────────────────────────────────────────────────────┘   │
│                           ↓                                  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  LEVEL 1: Package Runtime (Authoritative)            │   │
│  │  packages/saas-platform/modules/forms/               │   │
│  │                                                      │   │
│  │  ┌─────────────┐  ┌──────────────┐  ┌───────────┐  │   │
│  │  │ Controllers  │  │   Services   │  │   Views   │  │   │
│  │  │ (routed)     │→ │ (business    │→ │ (default) │  │   │
│  │  └─────────────┘  │  logic)      │  └───────────┘  │   │
│  │                    └──────────────┘                  │   │
│  │  ┌─────────────┐  ┌──────────────┐  ┌───────────┐  │   │
│  │  │ Models      │  │ Repositories │  │  Routes   │  │   │
│  │  │ (SoftDeletes│← │ (data access)│← │ (web.php) │  │   │
│  │  │  HasUuid)   │  └──────────────┘  └───────────┘  │   │
│  │  └─────────────┘                                    │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Key Principles

1. **Package is the single source of truth** for all runtime code
2. **Views are the only safe override surface** via `vendor:publish`
3. **Config controls layout selection** without code changes
4. **Tenant customizations** happen at runtime, never in files
5. **Legacy code must be deleted** — not archived in runtime tree
6. **Drift detection** ensures overrides stay in sync with package updates
7. **No controller forks** — consuming projects must not duplicate controllers
