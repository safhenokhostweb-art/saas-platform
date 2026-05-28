# Design Token Governance — Phase 10.0

## Token File Location
```
packages/saas-platform/core-utils/resources/css/design-tokens.css
```

## Import Pattern
```css
/* saas-platform/resources/css/app.css */
@import '../../packages/saas-platform/core-utils/resources/css/design-tokens.css';

/* customer-site/resources/css/app.css */
@import '../../../saas-platform/packages/saas-platform/core-utils/resources/css/design-tokens.css';
```

## Token Namespace: `--ui-*`

All canonical tokens use the `--ui-` prefix. This is the ONLY approved namespace for shared tokens.

### Token Groups

| Group | Prefix | Example |
|---|---|---|
| Primary color | `--ui-primary-*` | `--ui-primary-600: #2563eb` |
| Action/Indigo | `--ui-indigo*` | `--ui-indigo: #4f46e5` |
| Accent/Purple | `--ui-purple*` | `--ui-purple: #a855f7` |
| Status | `--ui-success/danger/warning/info*` | `--ui-success: #16a34a` |
| Surface | `--ui-bg/surface/border/text-*` | `--ui-surface: #ffffff` |
| Radius | `--ui-radius-*` | `--ui-radius-xl: 1rem` |
| Shadow | `--ui-shadow-*` | `--ui-shadow-card: ...` |
| Spacing | `--ui-space-*` | `--ui-space-4: 1rem` |
| Typography | `--ui-font/text/weight/leading/tracking-*` | `--ui-text-sm: 0.875rem` |
| Motion | `--ui-duration/ease/transition*` | `--ui-duration-normal: 150ms` |
| Z-index | `--ui-z-*` | `--ui-z-modal: 300` |
| Icon | `--ui-icon-*` | `--ui-icon-md: 1.25rem` |
| Glass | `--ui-glass-*` | `--ui-glass-bg: rgba(255,255,255,0.84)` |
| Admin/Sidebar | `--ui-admin-*/--ui-sidebar-*` | `--ui-admin-primary: var(--ui-indigo)` |

## Backward Compatibility Aliases

Phase 10.0 maintains backward compatibility aliases for existing code:

```css
/* Forms module aliases — point to canonical tokens */
--forms-primary:     var(--ui-indigo);
--forms-shadow-md:   var(--ui-shadow-md);
--forms-radius-lg:   var(--ui-radius-2xl);
--forms-z-modal:     var(--ui-z-modal);

/* Admin layout aliases */
--admin-primary:     var(--ui-admin-primary);
--admin-bg:          var(--ui-admin-bg);
--admin-border:      var(--ui-admin-border);
```

These aliases ensure existing views work without modification.

## Canonical Keyframes

All animations are defined in `design-tokens.css`:

| Name | Usage |
|---|---|
| `ui-fade-in` | Modal/panel appearances |
| `ui-slide-up` | Toast notifications |
| `ui-slide-down` | Dropdown panels |
| `ui-scale-in` | Confirm dialogs |
| `ui-skeleton-wave` | Loading skeletons (replaces `.skeleton` inline) |
| `ui-pulse-dot` | Priority dots, live indicators (replaces `.pdot`) |
| `ui-spin` | Loading spinners |
| `ui-bounce-in` | Success confirmations |
| `ui-alert-pulse` | Escalation alerts |

**FORBIDDEN:** Defining `@keyframes` inside `@push('head')` style blocks in any Blade view.

## Token Usage Rules

### ✓ Allowed
```css
background: var(--ui-indigo);
border-radius: var(--ui-radius-xl);
box-shadow: var(--ui-shadow-card);
transition: var(--ui-transition);
animation: ui-skeleton-wave 1.4s ease infinite;
```

### ✗ Forbidden
```css
background: #4f46e5;          /* hardcoded hex */
background: blue;             /* named color */
border-radius: 16px;          /* hardcoded value without token */
animation-duration: 300ms;    /* use var(--ui-duration-slow) */
```

### ✗ Forbidden Tailwind Utilities
```html
class="bg-blue-500"    <!-- use token-driven class or x-core-ui::button variant -->
class="text-red-600"   <!-- use text-[var(--ui-danger)] or status component -->
class="border-green-500" <!-- use border-[var(--ui-success)] -->
```

## Token Validation

```bash
php artisan frontend:validate-authority
php artisan frontend:validate-authority --json
php artisan frontend:validate-authority --strict --fail-on-warning
```
