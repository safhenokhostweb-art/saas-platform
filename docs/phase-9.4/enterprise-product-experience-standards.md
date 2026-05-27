# Enterprise Product Experience Standards — Phase 9.4

## EnterpriseProductExperienceService

**Namespace:** `SaaSPlatform\CoreUtils\Enterprise\EnterpriseProductExperienceService`

Validates platform-wide visual and interaction consistency against 11 quality dimensions.

## Validation Dimensions

| Dimension | Check |
|---|---|
| `spacing` | No raw `px` margin/padding in inline styles |
| `typography` | No raw `font-size: Npx` in inline styles |
| `animation` | Tailwind `animate-*` utilities used |
| `components` | Core-UI component usage count across admin views |
| `accessibility` | Images with alt text, interactive divs with role |
| `keyboard` | No excessive `tabindex=-1` usage |
| `responsive` | Tailwind responsive breakpoints (`md:`, `lg:`) present |
| `skeleton_loading` | Skeleton loaders used for async content |
| `dark_mode` | `dark:` CSS classes present (readiness check) |
| `rtl` | `rtl:` classes or `dir="rtl"` present |
| `interaction_timing` | Tailwind `transition`, `duration-*` utilities present |

## Scoring

```
Start at 100
  -10 per error finding
   -5 per warning finding
   -1 per info finding
= final score (clamped 0–100)
```

Grade map: A+ (95+), A (90+), B (80+), C (70+), D (60+), F (<60)

## Commands

```bash
# Standard validation
php artisan product:validate-experience

# Strict mode — warnings fail the check
php artisan product:validate-experience --strict

# JSON output for CI integration
php artisan product:validate-experience --json

# Validate a single view file
php artisan product:validate-experience --file=path/to/view.blade.php
```

## CI Integration

```yaml
- name: Product experience gate
  run: php artisan product:validate-experience --json --strict
  continue-on-error: false
```

Exit code 0 = passed, 1 = failed.

## Standards Checklist

### Spacing
- Use Tailwind spacing utilities (`p-4`, `m-2`, `gap-3`)
- Never use `style="margin: 16px"` in Blade views
- Sections use `section-header` core-UI component

### Typography
- Use Tailwind text utilities (`text-sm`, `font-semibold`, `text-gray-600`)
- Never use `style="font-size: 14px"`
- Display numbers use `font-mono` or `tabular-nums`

### Animation
- Use `transition`, `duration-200`, `ease-in-out` Tailwind classes
- No raw CSS `@keyframes` except design-token animations in `design-tokens.blade.php`

### Accessibility (Mandatory)
- All `<img>` must have `alt` attribute
- All `<button>` must have descriptive text or `aria-label`
- Modal dialogs must have `role="dialog"` and `aria-modal="true"`
- Color contrast ratio ≥ 4.5:1 for body text

### RTL (Mandatory)
- Use `rtl:` prefix for directional utilities: `rtl:space-x-reverse`, `rtl:mr-0 rtl:ml-2`
- Persian text renders natively; do not use `direction: ltr` on user content
- Icons with directional meaning must be mirrored with `rtl:scale-x-[-1]`
