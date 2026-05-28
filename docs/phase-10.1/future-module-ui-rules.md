# Future Module UI Rules — Phase 10.1

## CHECKLIST FOR NEW MODULES

Before a new module can be merged:

### Architecture
- [ ] Module has NO `resources/css/` directory (use canonical tokens)
- [ ] Module has NO custom color palette
- [ ] Module registers views but NOT `x-core-ui::` component path
- [ ] Module service provider uses `$this->loadViewsFrom()` only for module-specific views

### Views
- [ ] All admin pages use `x-core-ui::page-header`
- [ ] All lists use `x-core-ui::data-table`
- [ ] All forms use `x-core-ui::input`, `select`, `textarea`, `checkbox`, `switch`
- [ ] All buttons use `x-core-ui::button`
- [ ] All dialogs use `x-core-ui::modal` or `x-core-ui::confirm-modal`
- [ ] All status indicators use `x-core-ui::badge`
- [ ] All inline messages use `x-core-ui::alert`
- [ ] All notifications use `$dispatch('ui-toast', {...})`
- [ ] All loading states use `x-core-ui::skeleton` or `x-core-ui::button :loading="loading"`
- [ ] All pages inject `<x-core-ui::design-tokens />` if not in shared layout

### Tokens
- [ ] No inline `style="..."` attributes with fixed values
- [ ] No hardcoded hex colors
- [ ] No hardcoded pixel spacing
- [ ] No hardcoded z-index values
- [ ] All animations use `--ui-duration-*` and `--ui-ease-*` tokens

### Accessibility
- [ ] All interactive elements have `focus-visible` ring styles
- [ ] All icons have `aria-hidden="true"` or `aria-label`
- [ ] All form fields have associated labels
- [ ] Keyboard navigation works without mouse

### Responsive
- [ ] All views tested at 375px (mobile), 768px (tablet), 1280px (desktop)
- [ ] Tables wrapped in `overflow-x-auto`
- [ ] No fixed widths that prevent responsive layout
- [ ] Touch targets ≥ 44px

### Validation
- [ ] `php artisan frontend:validate-enterprise-governance` passes (≥80/100)
- [ ] `php artisan frontend:validate-components` passes (0 CRITICAL)
- [ ] `php artisan frontend:validate-responsive` passes (0 CRITICAL)

---

## PERMITTED MODULE-SPECIFIC UI PATTERNS

Modules MAY have:
- Module-specific component wrappers (extending but not replacing canonical)
- Module-specific Alpine data stores
- Module-specific route-driven page layouts
- Module-specific empty state illustrations
- Module-specific color themes via CSS overrides on `--ui-*` at component scope (NOT global `:root`)

---

## FORBIDDEN MODULE PATTERNS

Modules MUST NOT have:
- Custom button components
- Custom input components
- Custom modal systems
- Custom toast systems
- Custom table components
- CSS that overrides `:root` design tokens
- `window.confirm()` or `alert()`
- `style="..."` with hardcoded color/spacing values
- `@keyframes` in Blade views
