# Enterprise UX Standards — Phase 9.5-B

## Mandatory Requirements

Every frontend component in this platform MUST meet these standards.

### Accessibility (WCAG 2.1 AA)

- All interactive elements have `focus-visible` states (ring-2 indigo-500)
- All modals trap focus and restore on close
- Escape key closes any open modal/popover/panel
- ARIA roles: `role="dialog"`, `role="listitem"`, `role="alert"`, `aria-label`, `aria-expanded`, `aria-busy`
- Live regions: `aria-live="polite"` for dynamic updates, `assertive` for critical alerts
- Color is never the only indicator (always accompanied by text/icon)
- All icons have `aria-hidden="true"` with visible text labels

### RTL & Persian Typography

- All views use `dir="rtl"` when Persian locale active
- Tailwind RTL utilities: `rtl:space-x-reverse`, `rtl:mr-*`, `rtl:ml-*`
- Font: Vazirmatn (Persian-optimized, Unicode-safe)
- Number display: Persian numerals via `NumberHelper::toFarsi()` where required
- Design token: `--font-farsi: 'Vazirmatn', sans-serif`

### Loading States

Every async operation must show:
1. Skeleton loader OR animated spinner
2. `aria-busy="true"` on the loading container
3. Disabled state on action buttons during loading
4. Clear error state on failure (not just console log)

### Error States

- User-friendly Persian error messages
- Retry mechanism for transient failures
- Graceful degraded mode (AI fallback text)
- Toast notification for all background operation results

### Empty States

Every list/table MUST have an empty state:
- Meaningful icon
- Short descriptive text
- Call-to-action button (where applicable)
- Use `core-ui::empty-state` component

### Keyboard Navigation

- All interactive elements reachable by Tab
- Dropdown menus navigable by Arrow keys
- Modal opened with Enter, closed with Escape
- Form builder fields moveable by keyboard

### Mobile & Responsive

- All views tested at 320px, 768px, 1024px, 1440px
- Tailwind responsive prefixes: `sm:`, `md:`, `lg:`
- No horizontal overflow at any breakpoint
- Touch targets minimum 44×44px
- Modals full-screen on mobile (< md)

## Component Standards

### Tailwind Patterns (canonical)

```html
<!-- Focus ring -->
focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500

<!-- Button primary -->
inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white
hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed
focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500
transition-colors font-medium text-sm

<!-- Input field -->
w-full px-3 py-2 border border-gray-300 rounded-lg text-sm
focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent
placeholder-gray-400 disabled:bg-gray-50 disabled:text-gray-500

<!-- Badge severity -->
critical: bg-red-100 text-red-700 border-red-200
warning:  bg-yellow-100 text-yellow-700 border-yellow-200
success:  bg-green-100 text-green-700 border-green-200
info:     bg-blue-100 text-blue-700 border-blue-200
```

### Design Tokens

Canonical design tokens defined in `core-ui::design-tokens`:
- `--color-primary-*` — indigo scale
- `--color-success-*`, `--color-warning-*`, `--color-danger-*`
- `--radius-*` — border radius scale
- `--shadow-*` — shadow scale
- `--spacing-*` — 4px base grid
- `--z-modal`, `--z-toast`, `--z-dropdown`, `--z-overlay`
- `--font-body`, `--font-farsi`, `--font-mono`

## Commands

```bash
php artisan ux:validate-enterprise-readiness
php artisan ux:validate-enterprise-readiness --json --strict
```
