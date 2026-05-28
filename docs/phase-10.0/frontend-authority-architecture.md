# Frontend Authority Architecture — Phase 10.0

## Overview

Phase 10.0 transforms the platform into a fully-governed enterprise frontend runtime. All visual styling originates from a single canonical authority layer shared by `saas-platform` and `customer-site`.

## Authority Hierarchy

```
core-utils/resources/css/design-tokens.css      ← CANONICAL TOKEN SOURCE
        ↓ imported by
saas-platform/resources/css/app.css              ← Platform CSS entry
customer-site/resources/css/app.css              ← Customer CSS entry
        ↓ consumed by
core-utils/resources/views/components/*.blade.php ← PRIMARY component authority
        + (additive)
forms/resources/views/core-ui/components/*.blade.php ← Module-specific components
        ↓ rendered by
CoreUtilsServiceProvider::boot()                  ← Registers x-core-ui:: namespace FIRST
FormsServiceProvider::boot()                      ← Adds to x-core-ui:: namespace (additive)
TicketsServiceProvider::boot()                    ← Falls back to forms registration
```

## Registration Order (Critical)

`CoreUtilsServiceProvider` is registered before module providers in Laravel's bootstrap sequence. This guarantees:

1. `core-utils/resources/views/components/` is searched FIRST for `x-core-ui::*` components
2. `forms/resources/views/core-ui/components/` is searched SECOND (additive)
3. Any component in core-utils **overrides** the same-named component in forms (if both exist)

This design ensures:
- `x-core-ui::button` always resolves from core-utils (canonical authority)
- `x-core-ui::form-status-pill` resolves from forms (no conflict, forms-specific)
- Future modules can contribute to `core-ui` namespace without conflicts

## Component Authority Table

| Component | Source | Canonical? |
|---|---|---|
| `x-core-ui::button` | core-utils | ✓ CANONICAL |
| `x-core-ui::icon-button` | core-utils | ✓ CANONICAL |
| `x-core-ui::card` | core-utils | ✓ CANONICAL |
| `x-core-ui::input` | core-utils | ✓ CANONICAL |
| `x-core-ui::select` | core-utils | ✓ CANONICAL |
| `x-core-ui::textarea` | core-utils | ✓ CANONICAL |
| `x-core-ui::checkbox` | core-utils | ✓ CANONICAL |
| `x-core-ui::radio-card` | core-utils | ✓ CANONICAL |
| `x-core-ui::dropdown` | core-utils | ✓ CANONICAL |
| `x-core-ui::dropdown-item` | core-utils | ✓ CANONICAL |
| `x-core-ui::action-menu` | core-utils | ✓ CANONICAL |
| `x-core-ui::navigation-item` | core-utils | ✓ CANONICAL |
| `x-core-ui::sidebar-group` | core-utils | ✓ CANONICAL |
| `x-core-ui::modal` | forms module | Module |
| `x-core-ui::confirm-modal` | forms module | Module |
| `x-core-ui::toast` | forms module | Module |
| `x-core-ui::alert` | forms module | Module |
| `x-core-ui::badge` | forms module | Module |
| `x-core-ui::tabs` | forms module | Module |
| `x-core-ui::stat-card` | forms module | Module |
| `x-core-ui::page-header` | forms module | Module |
| `x-core-ui::empty-state` | forms module | Module |
| `x-core-ui::skeleton-card` | forms module | Module |
| `x-core-ui::design-tokens` | forms module | Module |

## Auto-Propagation Mechanism

Any visual change to `design-tokens.css` propagates automatically because:
1. Both `app.css` files import it via CSS `@import`
2. All component styles reference `--ui-*` variables
3. Tailwind build re-compiles CSS on next `npm run build`
4. No manual duplication required

## Visual Difference Policy

**Allowed differences (runtime-controlled):**
- `@platform` / `@endplatform` — platform admin only blocks
- `@tenant` / `@endtenant` — tenant admin only blocks
- `@capability('x')` — capability-gated features
- Policy-filtered navigation items
- Tenant-specific content (not styling)

**Forbidden differences:**
- Module-local CSS files
- Inline `style=` attributes with values (not variables)
- Hardcoded hex colors or Tailwind color utilities in Blade
- Per-project component overrides
- Separate navigation implementations
