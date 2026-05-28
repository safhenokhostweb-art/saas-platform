# Frontend Governance Rules — Phase 10.1

## THE FRONTEND AUTHORITY LAW

This platform operates under a **single canonical frontend authority** originating from `packages/saas-platform/core-utils`. No module, plugin, theme, or tenant may deviate from this authority.

---

## RULE 1 — SINGLE SOURCE OF TRUTH

All visual primitives MUST come from:
- `packages/saas-platform/core-utils/resources/css/design-tokens.css` — CSS custom properties
- `packages/saas-platform/core-utils/resources/views/components/` — Blade components (`x-core-ui::*`)

**Violation:** Any module defining its own color palette, spacing scale, or component from scratch is a governance violation.

---

## RULE 2 — DESIGN TOKEN MANDATE

All CSS values for color, spacing, typography, shadow, radius, animation, and z-index MUST use `--ui-*` CSS custom properties.

### Forbidden
```css
color: #374151;           /* ✗ hardcoded */
margin: 16px;             /* ✗ hardcoded */
z-index: 50;              /* ✗ hardcoded */
transition: all 0.2s;     /* ✗ non-canonical */
```

### Required
```css
color: var(--ui-text-primary);
margin: var(--ui-space-4);
z-index: var(--ui-z-modal);
transition: var(--ui-transition);
```

---

## RULE 3 — CANONICAL COMPONENT MANDATE

All UI primitives MUST use canonical `x-core-ui::*` components.

### Forbidden
```blade
<button class="bg-blue-600 text-white px-4 py-2 rounded">Submit</button>
<input type="text" class="border rounded px-3 py-2">
<select>...</select>
```

### Required
```blade
<x-core-ui::button>Submit</x-core-ui::button>
<x-core-ui::input name="field" label="Label" />
<x-core-ui::select name="choice" :options="$opts" />
```

---

## RULE 4 — MODAL GOVERNANCE

All dialogs, alerts, and confirmation flows MUST use canonical modal components.

### Forbidden
```javascript
confirm('Are you sure?')                  // ✗ native confirm()
window.confirm('Delete this item?')       // ✗
// custom Alpine modal with inline HTML   // ✗
```

### Required
```blade
<x-core-ui::confirm-modal
    name="delete-item"
    title="حذف آیتم"
    @confirmed="deleteItem()"
/>
```

---

## RULE 5 — STATE MANAGEMENT MANDATE

All loading, error, success, and confirmation states MUST use canonical patterns.

| State | Canonical Pattern |
|-------|-------------------|
| Loading | `x-core-ui::button :loading="loading"` or `x-core-ui::skeleton` |
| Success | `$dispatch('ui-toast', { message: '...', variant: 'success' })` |
| Error | `$dispatch('ui-toast', { message: '...', variant: 'danger' })` |
| Confirm | `x-core-ui::confirm-modal` |

---

## RULE 6 — RESPONSIVE MANDATE

All views MUST be responsive. Forbidden practices:

- Fixed pixel widths: `style="width: 500px"` → use `max-w-*` or tokens
- No mobile breakpoints: every layout MUST have `sm:` or `md:` variants
- Touch targets below 44px: use `min-h-[var(--ui-touch-target-min)]`
- Non-scrollable tables: all `<table>` must be inside `overflow-x-auto`

---

## RULE 7 — ACCESSIBILITY MANDATE

- All interactive elements MUST have `focus-visible` ring styles
- All icons MUST have `aria-hidden="true"` or descriptive `aria-label`
- All dialogs MUST have `role="dialog"` and `aria-modal="true"`
- All form inputs MUST have associated `<label>` via `for` attribute
- Color alone must NOT convey meaning (always pair with text/icon)

---

## RULE 8 — ANIMATION MANDATE

All animations MUST:
- Use `--ui-duration-*` and `--ui-ease-*` tokens
- Support `prefers-reduced-motion` (via the canonical CSS media query)
- Use canonical `@keyframes` defined in `design-tokens.css`

**Forbidden:** Inline `@keyframes` in Blade views, `transition: all 0.2s ease` without tokens.

---

## RULE 9 — NO INLINE STYLES

`style="..."` attributes are forbidden in all module views EXCEPT:
- CSS custom property overrides: `style="--my-var: value;"` — ALLOWED
- Dynamic computed values that cannot be expressed in CSS classes — requires team review

---

## RULE 10 — CROSS-CONTEXT PARITY

Platform admin, tenant admin, and customer-site MUST render identically for all shared components. The ONLY allowed differences are:
- Gates and permissions
- Feature flags
- Tenant-specific content
- Licensing restrictions

---

## ENFORCEMENT

Run validation commands after every module change:
```bash
php artisan frontend:validate-enterprise-governance
php artisan frontend:validate-components
php artisan frontend:validate-responsive
```

A PR that reduces the governance score below 80/100 MUST NOT be merged.
