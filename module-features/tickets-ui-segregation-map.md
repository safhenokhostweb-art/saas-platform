# Tickets Module — UI Segregation Map

**PHASE 7.1 — TASK 6**  
**Date:** 2026-05-25  
**Status:** Enforced

---

## Authority Principle

Every view in the Tickets module must know its **exact runtime context** and render only what that context permits. No view may bleed platform controls into tenant context or vice versa.

**Rule:** site/ views → tenant only. admin/ views → platform or tenant-admin. Never mixed.

---

## Context Detection

The `@tenant` / `@platform` Blade directives are provided by `FormsServiceProvider` via `PlatformContextService`, and `PlatformContext` from `SaaSPlatform\CoreUtils\Services\PlatformContext`.

| Directive       | Resolves To                          | Used In          |
|-----------------|--------------------------------------|------------------|
| `@tenant`       | `PlatformContext::isTenant()`        | site/ views      |
| `@platform`     | `PlatformContext::isCentralAdmin()`  | admin/ views     |
| `@capability`   | `CapabilityResolver::check()`        | conditional UI   |

---

## View Authority Matrix

### Admin Views (`tickets::admin.*`)

| View                             | Context     | Platform Controls | Tenant Controls | Notes                        |
|----------------------------------|-------------|-------------------|-----------------|------------------------------|
| `admin/tickets/index`            | Both        | Site filter, SLA badge | —          | site_id filter shown only to platform admin |
| `admin/tickets/create`           | Both        | Site selector     | —               | Site selector hidden for tenant-admin |
| `admin/tickets/show`             | Both        | Assignment, status change, priority | Reply | Full sidebar to platform admin only |
| `admin/tickets/sla`              | Platform    | SLA metrics       | —               | Gate-protected: `canViewSlaMetrics()` |

### Site Views (`tickets::site.*`)

| View                        | Context | Platform Controls | Tenant Controls | Notes                           |
|-----------------------------|---------|-------------------|-----------------|---------------------------------|
| `site/tickets/index`        | Tenant  | None              | View own tickets | Wrapped in `@tenant`/`@endtenant` |
| `site/tickets/create`       | Tenant  | None              | Submit ticket   | No site selector exposed        |
| `site/tickets/show`         | Tenant  | None              | Read + reply    | Closed tickets → no reply form  |

### Components (context-neutral)

| Component                      | Used In          | Platform Leak Risk | Mitigation                          |
|--------------------------------|------------------|--------------------|-------------------------------------|
| `components/status-badge`      | Both             | None               | Labels only, no actions             |
| `components/priority-badge`    | Both             | None               | Labels only, no actions             |
| `components/reply-item`        | Both             | Low                | `$isStaff` flag styles only, no controls |

---

## Segregation Rules

### Rule S-1: No `@platform` in `site/` views
Site views must never use the `@platform` directive or reference platform-only routes.

**Checked by:** `TicketCanonicalValidationService::validateNoAdminLeakageInSiteViews()`  
**Drift command:** `php artisan drift:blades`

### Rule S-2: No platform admin routes in site views
Site views use only `tickets.site.*` named routes. Admin routes (`tickets.admin.*`) are forbidden.

### Rule S-3: `@tenant` wrapper on all site views
Every site view's `@section('content')` is wrapped in `@tenant` / `@endtenant` to prevent rendering outside tenant context.

### Rule S-4: Site selector hidden from tenant users
The site `<select>` dropdown in `admin/tickets/create` must be conditionally rendered via `TicketGateService::canViewSlaMetrics()` or similar gate check — never rendered for tenant-admin users.

### Rule S-5: `site_id` always server-side
Portal views never emit `site_id` as a hidden form field. The server-side `TicketPortalService::resolveSiteId()` derives site context from the authenticated user. Frontend cannot spoof this.

### Rule S-6: Reply author type is server-set
`author_type` is never a form field. Admin controllers set `AUTHOR_STAFF`, portal controllers set `AUTHOR_CUSTOMER`. Frontend has zero control over this.

---

## Layout Resolution

| Context       | Config Key                    | Default               |
|---------------|-------------------------------|-----------------------|
| Admin         | `tickets.layouts.admin`       | `admin.layout`        |
| Admin (tenant)| `tickets.layouts.admin_tenant`| `admin.layout`        |
| Site          | `tickets.layouts.site`        | `layouts.app`         |

Consuming apps override via `config/tickets.php` after `vendor:publish --tag=tickets-config`.

---

## Anti-Drift Validation Checklist

Run before every release:

```bash
php artisan drift:blades          # No @platform in site/ views
php artisan drift:contracts       # All services have contracts
php artisan drift:authority       # No hardcoded layouts in package views
php artisan platform:validate-authority  # Authority boundary check
```

---

## Known Acceptable Deviations

| Deviation                                      | Reason                              | Ticket |
|------------------------------------------------|-------------------------------------|--------|
| `reply-item` component has no `@tenant` wrap   | Used in both admin and site threads | —      |
| `admin/tickets/show` renders reply form        | Staff reply is an admin action      | —      |
