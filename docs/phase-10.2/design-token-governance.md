# Design Token Governance

**Phase:** 10.2  
**Service:** `DesignTokenEnforcementService`  
**Token File:** `packages/saas-platform/core-utils/resources/css/design-tokens.css`

---

## Token System

All design values are expressed as CSS custom properties with the `--ui-*` prefix.

### Token Categories

| Prefix | Examples | Purpose |
|--------|----------|---------|
| `--ui-primary-*` | `--ui-indigo`, `--ui-indigo-hover` | Brand/primary colors |
| `--ui-bg-*` | `--ui-bg`, `--ui-surface`, `--ui-surface-soft` | Background surfaces |
| `--ui-text-*` | `--ui-text-primary`, `--ui-text-subtle` | Typography colors |
| `--ui-border-*` | `--ui-border`, `--ui-border-strong` | Border colors |
| `--ui-shadow-*` | `--ui-shadow-sm`, `--ui-shadow-modal` | Box shadows |
| `--ui-radius-*` | `--ui-radius-md`, `--ui-radius-xl` | Border radii |
| `--ui-space-*` | `--ui-space-1` through `--ui-space-16` | Spacing scale |
| `--ui-duration-*` | `--ui-duration-fast`, `--ui-duration-moderate` | Animation durations |
| `--ui-ease-*` | `--ui-ease-out`, `--ui-ease-spring` | Easing functions |
| `--ui-z-*` | `--ui-z-modal`, `--ui-z-dropdown` | Z-index hierarchy |
| `--ui-modal-*` | `--ui-modal-max-width-md` | Modal dimensions |
| `--ui-success-*` | `--ui-success`, `--ui-success-text` | Semantic success |
| `--ui-danger-*` | `--ui-danger`, `--ui-danger-bg` | Semantic danger/error |
| `--ui-warning-*` | `--ui-warning`, `--ui-warning-bg` | Semantic warning |
| `--ui-info-*` | `--ui-info`, `--ui-info-bg` | Semantic info |

---

## Enforcement Rules

### Forbidden in Blade Files

```blade
{{-- ❌ FORBIDDEN --}}
<div style="color: #4F46E5">...</div>
<div style="margin-left: 16px">...</div>
<div style="font-size: 14px">...</div>
<div style="transition: all 0.3s ease">...</div>
<div style="box-shadow: 0 4px 6px rgba(0,0,0,0.1)">...</div>
```

### Required Pattern

```blade
{{-- ✓ REQUIRED --}}
<div class="text-[var(--ui-indigo)]">...</div>
<div class="ml-[var(--ui-space-4)]">...</div>
<div class="text-[var(--ui-text-sm)]">...</div>
```

Or use canonical components which apply tokens automatically:
```blade
<x-core-ui::button variant="primary">...</x-core-ui::button>
```

---

## How to Add a New Token

1. Open `packages/saas-platform/core-utils/resources/css/design-tokens.css`
2. Add the token in the `:root` block under the appropriate category section
3. Use it in component files or app.css
4. Never hardcode the value again anywhere

```css
/* In design-tokens.css */
:root {
  --ui-my-new-token: #value;
}
```

---

## Validation Command

```bash
# Run token enforcement validation
php artisan frontend:validate-parity --json

# Auto-detect and report token violations
php artisan frontend:validate-component-authority --json | jq '.summary'
```

---

## Dark Mode / Theme Extension

To support dark mode or tenant-specific themes, override tokens in a scoped block:

```css
/* Tenant theme override — no new tokens, just override values */
[data-theme="dark"] {
  --ui-bg: #0f172a;
  --ui-surface: #1e293b;
  --ui-text-primary: #f8fafc;
  --ui-border: #334155;
}
```

The `--ui-*` namespace is reserved for the canonical system. Never create `--forms-*` or `--tickets-*` custom tokens.
