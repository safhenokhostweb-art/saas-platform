# Accessibility Governance

**Phase:** 10.2  
**Service:** `AccessibilityGovernanceValidationService`  
**Target Standard:** WCAG 2.1 Level AA

---

## Checks Implemented

| Check | WCAG Criterion | Severity |
|-------|----------------|----------|
| ARIA labels on icon-only buttons | 1.1.1, 4.1.2 | Warning |
| ARIA labels on unlabeled inputs | 1.3.1, 4.1.2 | Warning |
| `focus-visible:` instead of `focus:` | 2.4.7 | Warning |
| `role="dialog"` + `aria-modal` + `aria-labelledby` | 4.1.2 | Warning |
| `@keydown.escape` on dialogs | 2.1.2 | Warning |
| `alt` attribute on all `<img>` | 1.1.1 | Violation |
| No positive `tabindex` | 2.4.3 | Violation |
| Semantic landmarks (main, nav, header, footer) | 1.3.6, 2.4.6 | Warning |
| Skip-to-content link in admin layout | 2.4.1 | Warning |
| `@media (prefers-reduced-motion)` in CSS | 2.3.3 | Violation |

---

## How to Fix Common Issues

### Icon-only buttons without aria-label
```blade
{{-- ❌ --}}
<x-core-ui::button variant="ghost" :icon="true" @click="deleteItem">
    <svg>...</svg>
</x-core-ui::button>

{{-- ✓ --}}
<x-core-ui::button variant="ghost" :icon="true" aria-label="حذف آیتم" @click="deleteItem">
    <svg aria-hidden="true">...</svg>
</x-core-ui::button>
```

### Modals missing ARIA attributes
The `x-core-ui::modal` component handles all modal accessibility automatically:
- `role="dialog"` on the panel
- `aria-modal="true"` on the panel
- `aria-labelledby` linked to the title
- `@keydown.escape` handler registered
- Focus trapped to dialog on open

Always use `x-core-ui::modal` — never create raw dialog divs.

### focus: vs focus-visible:
```blade
{{-- ❌ Shows focus ring on mouse click (visual noise) --}}
class="focus:ring-2 focus:ring-indigo-500"

{{-- ✓ Only shows focus ring on keyboard navigation --}}
class="focus-visible:ring-2 focus-visible:ring-[var(--ui-focus-ring-color)]"
```

The `x-core-ui::button` and `x-core-ui::input` components already use `focus-visible:` everywhere.

---

## Command Usage

```bash
php artisan frontend:validate-parity --json | jq '.dimensions.accessibility'
```

---

## WCAG AA Score Interpretation

| Score | WCAG Level | Meaning |
|-------|-----------|---------|
| 90+ | AA | Full WCAG AA compliance |
| 75-89 | A | Partial compliance — some issues present |
| < 75 | Fail | Significant accessibility barriers present |
