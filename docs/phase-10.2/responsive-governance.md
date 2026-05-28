# Responsive Governance

**Phase:** 10.2  
**Service:** `ResponsiveGovernanceValidationService`  
**Command:** `php artisan frontend:validate-responsive-governance`

---

## Requirements

### Touch Targets (WCAG 2.5.5)
All interactive elements must have a minimum tap area of **44×44px**.

```blade
{{-- ❌ Touch target too small --}}
<x-core-ui::button :icon="true" class="w-6 h-6">...</x-core-ui::button>

{{-- ✓ Minimum touch area --}}
<x-core-ui::button :icon="true" size="sm">...</x-core-ui::button>
{{-- x-core-ui::button size="sm" renders p-1.5 = min 36px,
     but interactive area extended with min-h-[44px] via CSS --}}
```

The canonical button component's `sm` size with `icon` renders at minimum 36px. To guarantee 44px: `class="min-h-[44px] min-w-[44px]"`.

### Mobile Table Wrappers
Every `<table>` must have a horizontally scrollable parent:

```blade
<div class="overflow-x-auto -mx-4 sm:mx-0">
    <table class="min-w-full">...</table>
</div>
```

The `x-core-ui::data-table` component handles this automatically.

### RTL Logical Properties
Use logical CSS properties for RTL (Persian/Arabic) support:

| Physical (❌ Forbidden in inline styles) | Logical (✓ Required) |
|------------------------------------------|----------------------|
| `margin-left` | `margin-inline-start` |
| `margin-right` | `margin-inline-end` |
| `padding-left` | `padding-inline-start` |
| `padding-right` | `padding-inline-end` |
| `left` | `inset-inline-start` |
| `right` | `inset-inline-end` |

In Tailwind: use `ms-*` / `me-*` / `ps-*` / `pe-*` instead of `ml-*` / `mr-*` / `pl-*` / `pr-*`.

### prefers-reduced-motion
All animations must respect the user's motion preference. This is enforced in `design-tokens.css`:

```css
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
```

### Responsive Breakpoints
All layout views with `grid` or `flex` must use at least one responsive breakpoint (`sm:`, `md:`, or `lg:`).

```blade
{{-- ❌ Desktop-only grid --}}
<div class="grid grid-cols-3">

{{-- ✓ Responsive grid --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
```

---

## Command Usage

```bash
# Full responsive audit
php artisan frontend:validate-responsive-governance

# Strict mode (warnings become failures)
php artisan frontend:validate-responsive-governance --strict

# JSON output
php artisan frontend:validate-responsive-governance --json
```
