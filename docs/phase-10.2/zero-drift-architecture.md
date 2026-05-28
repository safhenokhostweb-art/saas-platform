# Zero-Drift Frontend Architecture

**Phase:** 10.2 — Canonical Frontend Enforcement  
**Date:** 2026-05-28  
**Status:** Implemented

---

## What is Zero Drift?

Zero drift means: any change made to the canonical design system in `saas-platform` automatically appears in `customer-site` and all modules — with no manual synchronization required.

Drift is defined as: any visual or behavioral difference between `saas-platform` and `customer-site` that was NOT introduced through a deliberate authorization mechanism (gate, policy, feature flag).

---

## The Problem This Solves

Before Phase 10.2, the platform had:
- **3,253** component violations across views (hardcoded hex, raw buttons, bg-white hardcodes)
- **62** responsive violations (touch targets, missing breakpoints, RTL logical props)
- Divergent module-specific design systems
- Manual synchronization required between `saas-platform` and `customer-site`
- No enforcement — a developer could add `style="color: #ff0000"` and nothing stopped them

---

## Architecture Pillars

### 1. Single Source of Truth: design-tokens.css

All design values — colors, spacing, shadows, z-index, animations, typography, radius — are defined ONCE in:
```
packages/saas-platform/core-utils/resources/css/design-tokens.css
```

Both `saas-platform/resources/css/app.css` and `customer-site/resources/css/app.css` import this file.

**Result:** Any token change propagates to both projects at build time.

### 2. Canonical Component Namespace: `x-core-ui::*`

All UI primitives are implemented ONCE in:
```
packages/saas-platform/core-utils/resources/views/components/
```

The `CoreUtilsServiceProvider` registers `Blade::anonymousComponentPath(...)` for the `core-ui` namespace FIRST, giving it authority priority over all module registrations.

**Result:** Any component change propagates to all modules and both projects automatically.

### 3. No Module-Specific Frontend

Modules (forms, tickets) provide views that:
- Extend `layouts.admin` (canonical layout)
- Use only `x-core-ui::*` components
- Use only `var(--ui-*)` token values
- Have zero inline styles
- Have zero hardcoded hex values

**Result:** Modules are visually governed by the canonical system. A new module gets the full enterprise design system for free.

### 4. Self-Enforcement Through CI

The GitHub Actions pipeline in `.github/workflows/frontend-governance.yml` BLOCKS any merge that:
- Introduces raw `<button>`, `<input>`, `<select>`, or `<textarea>` elements (outside canonical components and Alpine templates)
- Introduces hardcoded hex colors in inline styles
- Drops the enterprise governance score below 95/100

**Result:** Drift is structurally impossible to introduce through normal pull requests.

---

## Propagation Flow

```
design-tokens.css
    ↓ @import
saas-platform/app.css          customer-site/app.css
    ↓                              ↓
All module views               All tenant views
    ↓                              ↓
x-core-ui::button              x-core-ui::button
x-core-ui::input               x-core-ui::input
x-core-ui::modal               x-core-ui::modal
    ↓                              ↓
Identical visual output        Identical visual output
```

Any change at any level propagates down. No manual sync needed.

---

## Authorized Divergence Points

The ONLY mechanisms through which `customer-site` can legitimately look different from the admin panel are:

| Mechanism | Example |
|-----------|---------|
| Gates | `@can('view-admin-toolbar')` |
| Policies | `$this->authorize('manage-branding')` |
| Feature flags | `@if(feature('custom-tenant-theme'))` |
| Runtime capability checks | `CapabilityResolver::has('white-label')` |
| CSS custom property overrides | Tenant injects `--ui-primary: #brand-color` per-tenant |

**No Blade file duplication is ever an authorized divergence mechanism.**

---

## Services Introduced in Phase 10.2

| Service | Responsibility |
|---------|----------------|
| `CanonicalComponentAuthorityService` | Detects raw controls, inline styles, hardcoded colors |
| `DesignTokenEnforcementService` | Validates token coverage across all CSS and Blade |
| `ResponsiveGovernanceValidationService` | Touch targets, breakpoints, RTL, prefers-reduced-motion |
| `AccessibilityGovernanceValidationService` | WCAG AA: ARIA, focus-visible, landmarks, alt text |
| `FrontendDriftAutoRecoveryService` | Detects and repairs runtime governance failures |
| `ModuleFrontendInheritanceService` | Auto-registers module namespaces; validates inheritance |
| `ZeroDriftFrontendValidationService` | Master orchestrator — 10-dimension weighted score |
