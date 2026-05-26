# FRONTEND-GOVERNANCE-AUDIT.md
## Phase 7.3 — Frontend Governance Audit
**Date:** 2026-05-25

---

## 1. BLADE COMPONENT DUPLICATION

### Canonical Components (core-utils)
Located at: `packages/saas-platform/core-utils/resources/views/components/`
- `data-table.blade.php`
- `empty-state.blade.php`
- `filter-bar.blade.php`
- `module-actions.blade.php`
- `module-card.blade.php`
- `module-status-badge.blade.php`
- `stats-card.blade.php`
- `status-badge.blade.php`
- `table-pagination.blade.php`

**Status:** EXISTS but may not be used by consuming views.

### Duplication Evidence in App Views
The `resources/views/admin/support-tickets/index.blade.php` defines ALL its own inline CSS:
```html
<style>
.ticket-stat-card { ... }
.glass-filter-bar { ... }
.status-badge { ... }
.priority-dot { ... }
.btn-gradient { ... }
```
This view creates its OWN status-badge and stats-card styles — duplicating the canonical components from core-utils.

**Impact:** If canonical `<x-core-ui::status-badge>` exists, this view ignores it and re-implements inline. Every module view that does this creates visual inconsistency and doubles the maintenance surface.

---

## 2. LAYOUT CONSISTENCY

### Admin Layout
- Platform uses `layouts.admin` (Blade `@extends('layouts.admin')`)
- Canonical package tickets module uses `tickets::admin.tickets.index` which extends... the same `layouts.admin`
- `TicketsServiceProvider` has a view composer that conditionally changes the layout to `tickets.layouts.admin_tenant` in tenant context

**Problem:** The layout for the SAME component changes based on runtime context detection. This means the tickets admin can look different depending on whether `PlatformContext::isTenantAdmin()` returns true or false. This context detection depends on `config('app.role')` — if not set, it may produce wrong layout.

### Layout Resolver Services
Core-utils provides:
- `LayoutResolver` — registered as singleton
- `SharedLayoutResolver` — registered as singleton (introduced Phase 7.15 to replace LayoutResolver)

TWO layout resolver singletons are registered simultaneously. It is unclear which one is actually used by views.

### Hardcoded Layout in Package Views
The deprecated view `resources/views/admin/support-tickets/index.blade.php` uses:
```blade
@extends('layouts.admin')
```
This is hardcoded to the platform's `layouts.admin`. If the package is used in a different context (e.g., a tenant admin panel with a different layout), this breaks.

---

## 3. CANONICAL COMPONENTS ADOPTION

Evidence of canonical component adoption: **LOW**

The `core-ui` component namespace is registered (`loadViewsFrom`, namespace `core-ui`). But searching the admin views reveals they largely use raw HTML + inline Tailwind rather than `<x-core-ui::stats-card>` etc.

This means:
- Every view re-implements its own stats cards, badges, filter bars
- No visual consistency guarantee across modules
- Adding a module requires rebuilding all UI primitives from scratch

---

## 4. TAILWIND CONSISTENCY

### Approach
Platform appears to use Tailwind CSS utility classes throughout. No evidence of a design system enforcing a token set.

### Risks
- Tailwind JIT purging: if any module view is not in the `content` array of `tailwind.config.js`, its classes get purged in production
- Package views are in `packages/*/Resources/views/` — this path must be explicitly included in Tailwind's `content` array
- If not included, package views will have unstyled UI in production builds

---

## 5. OVERRIDE CHAIN AUDIT

### Core-Utils Override Authority
`OverrideAuthorityValidator` service exists (registered as singleton). It validates overrides — but:
- What constitutes a valid override is not documented in the audit scope
- The service is registered but whether it's called at runtime is unclear

### Published Views Override Risk
The tickets module has:
```php
$this->publishes([
    __DIR__ . '/../../Resources/views' => resource_path('views/vendor/tickets'),
], 'tickets-views');
```
If views are published and then the package is updated, the published views become STALE. Laravel will serve the published (old) views, not the package's (new) views. This is a known Laravel pitfall and creates an update management problem.

---

## 6. SIDEBAR/NAVIGATION CHAIN

### Services
- `MenuResolver` — singleton in core-utils
- `ModuleNavigationService` — singleton in core-utils
- Navigation items presumably loaded from DB (`menus` table) or config

### Gaps
- No audit of whether navigation items respect module enablement state
- If Forms module is disabled for a site, does the Forms menu item disappear? This requires `ModuleNavigationService` to check `site_modules` pivot — not confirmed.
- No evidence of navigation caching with per-site cache keys (cross-site contamination risk)

---

## 7. PAGINATION CONSISTENCY

`<x-core-ui::table-pagination>` exists in core-utils. Whether it's used by module views or whether each module implements its own pagination is not confirmed. The `resources/views/admin/support-tickets/index.blade.php` is ~200+ lines of inline UI — if pagination is done inline there too, it's duplicated.

---

## 8. EMPTY STATES

`<x-core-ui::empty-state>` exists. Same question: is it used or does each view re-implement empty state messaging?

---

## 9. THEME BOUNDARIES

### Platform Theme
- `resources/views/layouts/admin.blade.php` — platform admin layout
- Tailwind-based, glassmorphism design tokens visible in inline CSS

### Tenant Branding Isolation
- `Theme` model exists (`app/Models/Theme.php`)
- `ThemeRevision` model exists
- `DesignSystemService` exists
- No evidence that per-reseller theme branding is applied via middleware (no `SetThemeContext` middleware found)

**Risk:** If tenant branding is applied only in views (via conditional checks), missing a check in any view exposes platform branding to tenant users.

---

## 10. RESPONSIVE CONSISTENCY

No audit performed on responsive breakpoints. Tailwind's responsive utilities are presumably used, but without a component library enforcing consistent breakpoints, each view may implement its own responsive behavior.

---

## FRONTEND GOVERNANCE SCORE

| Category | Score | Notes |
|----------|-------|-------|
| Component reuse | 25/100 | Canonical components exist but not consistently used |
| Layout consistency | 40/100 | Layout resolvers exist but multiple versions registered |
| Override safety | 50/100 | OverrideAuthorityValidator exists but coverage unclear |
| Tailwind purge safety | UNKNOWN | Package view paths may not be in tailwind.config.js |
| Navigation governance | 45/100 | ModuleNavigationService exists, module-awareness unconfirmed |
| Theme isolation | 40/100 | Theme models exist, middleware application not confirmed |
| Legacy view cleanup | 20/100 | Multiple `_legacy/` directories still present |

**Overall Frontend Governance Score: 37/100**
