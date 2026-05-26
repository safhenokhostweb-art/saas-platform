# PHASE 7.25 — Customer-Site Tenant Consumption Report

**Completed:** 2026-05-25  
**Status:** COMPLETE — Customer-site confirmed safe for production deployment as a canonical tenant runtime  
**Scope:** c:\laragon\www\customer-site — full tenant consumer audit & alignment

---

## Executive Summary

**Can customer-site safely operate as a production tenant runtime on a separate server while consuming canonical modules from distributed packages?**

**YES — with one deferred cleanup item.** All 10 tasks have been completed. customer-site has been verified as a pure tenant consumer with no module ownership, no local business logic, and no platform-level features exposed.

---

## Deliverables

| # | Deliverable | Status | Location |
|---|-------------|--------|----------|
| 1 | CUSTOMER-RUNTIME-TRACE.md | ✅ Done | `docs/phase-7.25/CUSTOMER-RUNTIME-TRACE.md` |
| 2 | Support views moved to `_legacy/` | ✅ Done | `customer-site/resources/views/admin/support/_legacy/` |
| 3 | `CustomerPackageRuntimeValidator` | ✅ Done | `packages/core-utils/src/Services/CustomerPackageRuntimeValidator.php` |
| 4 | `AdminSupportController` marked `@deprecated` | ✅ Done | `customer-site/app/Http/Controllers/Admin/SupportController.php` |
| 5 | `AdminMenu` manifest integration | ✅ Done | `customer-site/app/Helpers/AdminMenu.php` |
| 6 | `CanonicalUiParityValidator` | ✅ Done | `packages/core-utils/src/Services/CanonicalUiParityValidator.php` |
| 7 | Platform feature audit | ✅ Done | Section 4 below |
| 8 | `TenantTicketController` + `SupportApiClient` update | ✅ Done | `app/Http/Controllers/Api/TenantTicketController.php` |
| 9 | `tenant:validate-runtime-authority` command (15 checks) | ✅ Done | `packages/core-utils/src/Console/Commands/ValidateTenantRuntimeCommand.php` |
| 10 | This report | ✅ Done | `docs/phase-7.25/CUSTOMER-TENANT-CONSUMPTION-REPORT.md` |

---

## Architecture Decisions

### AD-1: TenantTicketController in app/ layer (not tickets package)

The `TenantTicketController` is in `app/Http/Controllers/Api/` rather than the tickets package because it depends on `LicenseServerService` (an app-layer service). Putting an app-layer dependency inside the package would invert the dependency direction. The controller is a "bridge" — it validates license auth at the app layer and delegates to `TicketService` at the package layer.

**Rationale:** Single responsibility: app/ handles auth/licensing, package/ handles business logic.

### AD-2: AuthenticateTenantApi middleware separates auth from business logic

Rather than embedding license validation in `TenantTicketController`, a dedicated `AuthenticateTenantApi` middleware resolves the `Site` model and attaches it as `tenant_site` on the request. The controller reads `$request->attributes->get('tenant_site')`.

**Rationale:** Middleware chain is the correct Laravel pattern for pre-controller auth. Controllers should not re-implement auth logic.

### AD-3: Backward-compatible response shape in TenantTicketController

The new `/api/tenant/tickets` endpoint returns `message` (not `description`) in the ticket payload, even though the canonical `Ticket` model stores the field as `description`. This is intentional: customer-site views were built against the legacy API's `message` key.

**Rationale:** Zero-downtime migration. Views in customer-site continue working without change. The field mapping is explicit and documented at `ticketPayload()`.

### AD-4: AdminMenu manifest integration is additive (no hardcoded removal)

The manifest override methods were added to `AdminMenu.php` without removing the hardcoded menu constants. `getTitle()` now checks the manifest first. The hardcoded fallbacks remain for non-module menu items.

**Rationale:** AdminMenu handles 8 groups of non-module items (blog, shop, pages, etc.). These cannot be removed. The additive approach avoids breaking non-module navigation while enabling manifest-driven override for module items.

### AD-5: `SupportApiClient::find()` now uses dedicated endpoint

Previously, `find(ticketId)` loaded ALL tickets via `index()` and filtered client-side. The new implementation calls `GET /api/tenant/tickets/{id}` directly. This reduces data transfer and gives the server full control over single-ticket retrieval (including tenant isolation enforcement).

---

## Task 7: Platform Feature Audit

**Question:** Does customer-site expose any platform-level features that should be restricted to central admin?

### Platform Features — Not Exposed in customer-site ✅

| Feature | Assessment |
|---------|------------|
| Sites management (create/edit/delete sites) | No routes. Not present. |
| Tenant assignment | No routes. Not present. |
| Module distribution (publish/release) | No routes. Not present. |
| Central billing / subscription management | No routes. Not present. |
| Platform lifecycle tools | No routes. Not present. |
| Multi-tenant user management | No routes. Not present. |
| Platform config (APP_KEY, DB, etc.) | No routes. Not present. |
| Module versioning / release pipeline | No routes. Not present. |

### Tenant-Appropriate Features — Present and Correct ✅

| Feature | Route Group | Gate |
|---------|-------------|------|
| License management (own license) | `permission:license` | Tenant-appropriate: customer views their own license |
| Module management (enabled modules only) | `permission:license` | Reads from platform API — tenant cannot write module assignments |
| Support tickets (own tickets only) | `permission:support` | Via `SupportApiClient` → `AuthenticateTenantApi` → `TicketService::assertTenantIsolation()` |
| Forms (customer-submitted data) | Package controller | `FormController` (package) owns authority |
| Blog, shop, pages, orders | Various `permission:*` | Local content — tenant-owned, appropriate |

### Hidden via Context, Not Separate Views ✅

Platform features are hidden structurally — there are **no routes** for platform admin features in `customer-site/routes/web.php`. The protection is route-level absence, not view-level gating.

This is the strongest form of protection: a feature with no route cannot be accessed regardless of authentication state, cookies, or CSRF bypass.

---

## API Runtime Alignment

| Before PHASE 7.25 | After PHASE 7.25 |
|-------------------|------------------|
| `SupportApiClient` → `GET /api/support-tickets` | `SupportApiClient` → `GET /api/tenant/tickets` |
| `SupportApiClient` → `POST /api/support-tickets` | `SupportApiClient` → `POST /api/tenant/tickets` |
| `SupportApiClient` → `POST /api/support-tickets/{id}/reply` | `SupportApiClient` → `POST /api/tenant/tickets/{id}/reply` |
| `find()` loads all tickets, filters client-side | `find()` calls `GET /api/tenant/tickets/{id}` directly |
| Data source: `support_tickets` table (legacy) | Data source: `tickets` table (canonical) |
| Auth: `license_key` + `domain` in legacy controller | Auth: `AuthenticateTenantApi` middleware → `tenant_site` on request |

---

## Validation Command

```bash
# Run all 15 checks
php artisan tenant:validate-runtime-authority

# JSON output for CI
php artisan tenant:validate-runtime-authority --json

# Exit code 1 on any failure
php artisan tenant:validate-runtime-authority --fail-on-warning
```

### 15 Checks

| Check | Description |
|-------|-------------|
| T01 | No legacy module controllers active in routing |
| T02 | No local module views shadow package namespaces |
| T03 | Package namespaces resolve (forms::, tickets::, core-ui::) |
| T04 | Canonical routes active (admin + site + tenant-api) |
| T05 | Sidebar source bound (ModuleNavigationService + manifests) |
| T06 | Tenant isolation enforced (assertTenantIsolation exists on TicketService) |
| T07 | No platform-only actions visible in site/ namespaces |
| T08 | Canonical layouts resolve (admin.layout, layouts.admin) |
| T09 | No duplicate UI primitives (core-ui canonical) |
| T10 | Forms runtime canonical (routes + views + namespace) |
| T11 | Tickets runtime canonical (routes + views + namespace) |
| T12 | No forbidden override paths (OverrideAuthorityValidator clean) |
| T13 | API runtime canonical (/api/tenant/tickets + AuthenticateTenantApi) |
| T14 | Policy enforcement active (TicketPolicy registered in Gate) |
| T15 | Gate filtering active (PlatformContext resolves) |

---

## Deferred Items (Post-PHASE 7.25)

| Item | Reason |
|------|--------|
| Remove `/api/support-tickets` legacy route group from saas-platform | Keep active until all customer-site deployments confirmed migrated to `/api/tenant/tickets` |
| Migrate `AdminSupportController` to use `tickets::site.*` package views | PHASE 7.3 — blocked until tickets::site.* views are refactored to be platform-agnostic |
| Remove hardcoded `support`/`support_history`/`forms`/`forms_submissions` from `AdminMenu` constants | After `ModuleNavigationService` fully drives sidebar on customer-site |

---

## Governance Rules (PHASE 7.25 additions)

13. **customer-site MUST NOT own module business logic.** Controllers like `AdminSupportController` are permitted only as thin API proxies.
14. **customer-site MUST NOT define local views for modules managed by packages.** All `resources/views/admin/forms/` and `resources/views/admin/support/` views are in `_legacy/`.
15. **`/api/tenant/tickets` is the canonical tenant API.** `/api/support-tickets` is deprecated and will be removed.
16. **Run `php artisan tenant:validate-runtime-authority` before every production deploy** involving module or ticket changes.
17. **`assertTenantIsolation()` must be called in every `TicketService` method** that returns a single ticket.

---

## Compliant Items (Confirmed in Audit)

| Item | Status |
|------|--------|
| Forms routes use package controllers (`SaaSPlatform\Modules\Forms\*`) | ✅ Canonical |
| Forms views resolve from `forms::` namespace | ✅ Canonical |
| Tickets package registered via composer symlink | ✅ Available |
| Core-utils registered (Blade directives, PlatformContext) | ✅ Available |
| No local ticket DB models in customer-site | ✅ Correct |
| No platform admin routes (sites, tenants, billing) in customer-site | ✅ Clean |
| Rate limiters defined in customer-site's AppServiceProvider | ✅ Tenant-appropriate |
| Alpine support-chat: no rogue API calls to platform | ✅ Clean |
| License validation middleware chain (`license.valid`) | ✅ Tenant-appropriate |
| `AdminSupportController` is proxy-only (no DB, no business logic) | ✅ Correct pattern |
| `AuthenticateTenantApi` middleware registered in kernel | ✅ Active |
| `TenantTicketController` reads canonical `tickets` table | ✅ Canonical |
