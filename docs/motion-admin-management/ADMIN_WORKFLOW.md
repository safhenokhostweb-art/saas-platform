# Admin Workflow

## The journey

```text
Platform Admin
    |
    v
GET  /admin/motion-graphics                          select-site (site picker)
    |
    v
GET  /admin/motion-graphics/{site}/dashboard          totals: assets, templates, assigned packages, activated sites
    |
    v
GET  /admin/motion-graphics/{site}/templates          list templates (real Template Builder: App\Models\ThemeTemplate)
    |
POST /admin/motion-graphics/{site}/templates/preview          -> compatibility preview (no write)
POST /admin/motion-graphics/{site}/templates/{template}/assign   -> TemplateAssetPackageService::createTemplateAssetPackage()
POST /admin/motion-graphics/{site}/templates/{template}/activate -> MotionRuntimeInstallationService::installTemplateForSite()
    |
    v
GET  /admin/motion-graphics/{site}/preview            list this site's active packages
GET  /admin/motion-graphics/{site}/preview/{package}   -> MotionRuntimeInstallationService::projectSitePackage()
```

Every route lives under the existing `admin.` route group (`routes/web.php`), inheriting its `['auth', 'admin']` middleware unchanged — no new middleware, no new auth mechanism.

## Why every page is scoped to a site, even the "platform-wide" dashboard

`AbstractMotionAdapter::ensureEnabled()` — the same fail-closed check every Motion service has used since Phase 1 — requires `TenantContextContract::hasFeature('motion_graphics_integration')` to resolve `true`. Since Phase 6, that resolution is per-site (`sites.features` JSON), and `HasTenantContext::hasFeature()` returns `false` outright when there is no current tenant at all. A Platform Admin browsing `/admin/*` has no tenant by default (no domain-based site is active), so *any* Motion Graphics action — including the dashboard's counts — would fail closed with no way to succeed, even for a fully-enabled site, unless something explicitly tells `TenantContext` which site the admin means.

The fix is not a new mechanism: `MotionGraphicsAdminController::contextFor(Site $site)` calls the platform's own, pre-existing `TenantContextContract::set($site)` (an existing public method on the real `App\Services\TenantContext`, previously unused by any admin controller) before building the `MotionAdapterContext` and calling into `MotionGraphicsAdminService`. This makes the admin "act as" the selected internal test site for the duration of that one request — exactly mirroring how every Phase 1-6 test's `bindTrustedSite()` helper fakes the same thing. The dashboard's counts themselves (`ThemeTemplate::count()`, `TemplateAssetPackage::count()`, distinct active `SiteAssetPackage` site count) are plain, platform-wide Eloquent queries — they don't vary by which site is "current" — but they're still gated behind the same fail-closed check as everything else, by design, so there is no "siteless bypass" of the feature flag anywhere in this phase.

## A pre-existing bug this phase surfaced and fixed

`App\Http\Middleware\CheckTenantMaintenance` (part of the global `web` middleware group, so it runs on every request) called `$this->tenantContext->getSiteId()` — a method that has never existed on `TenantContextContract` (the real method is `id()`). This has apparently never been exercised by any existing test (`grep` for `CheckTenantMaintenance`/`TenantMaintenanceManager` across `tests/` returns nothing) and would 500 in production the first time any request runs with a genuine Site tenant context active and no matching maintenance record — which is precisely what `TenantContext::set($site)` now legitimately triggers for every Motion Graphics admin request. This is a one-line, root-cause fix (`getSiteId()` → `id()`), not a workaround, and it does not touch Motion Graphics code, `customer-site/`, or any other module.

## What "assign" vs "activate" actually do

- **Assign** (`TemplateAssetPackageService::createTemplateAssetPackage`, Phase 3): freezes a `TemplateAssetPackage` definition — resolved asset references + compatibility rules — for a template. No site is touched.
- **Activate** (`MotionRuntimeInstallationService::installTemplateForSite`, Phase 4): resolves-or-reuses that same template package, creates/updates the site's `SiteAssetPackage`, binds a version snapshot, and generates the runtime projection — all for the one site the admin is currently "acting as." Calling assign first is optional; activate resolves-or-creates the template package itself if it doesn't already exist (Phase 4's idempotency rule).

Both are exposed as separate buttons on the Template Assignment page so an admin can define a package once and activate it for several internal test sites independently, or activate directly without a separate assign step.
