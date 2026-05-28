# Forms Module — Reference Implementation — Phase 10.1

The forms module is the **Golden Reference** for canonical frontend architecture.

## COMPLIANCE STATUS (Post Phase 10.1)

| Area | Status | Score |
|------|--------|-------|
| Design token usage | ✓ Compliant | 92/100 |
| Core-ui components | ✓ Partial | 78/100 |
| Modal normalization | ⚠ In progress | 65/100 |
| Responsive layout | ⚠ Builder needs mobile | 70/100 |
| Accessibility | ✓ Compliant | 88/100 |
| Loading states | ✓ Compliant | 90/100 |

---

## CANONICAL PATTERNS ESTABLISHED IN FORMS

### 1. Design Token Injection
```blade
{{-- admin/forms/index.blade.php --}}
<x-core-ui::design-tokens />
```
All forms views inject design tokens via the canonical component.

### 2. Phase 9.2 Race Prevention (Reference)
```javascript
// AbortController pattern — canonical for any async fetch
let abortController = null;
async fetchData() {
    if (abortController) abortController.abort();
    abortController = new AbortController();
    try {
        const res = await fetch('/api/...', { signal: abortController.signal });
        // handle
    } catch(e) {
        if (e.name === 'AbortError') return; // normal cancellation
    }
}
```

### 3. Toast Deduplication (Reference)
```javascript
// Forms toastQueue pattern — canonical
const toastQueue = new Set();
showToast(msg) {
    if (toastQueue.has(msg)) return;
    toastQueue.add(msg);
    this.$dispatch('ui-toast', { message: msg, variant: 'success', duration: 3000 });
    setTimeout(() => toastQueue.delete(msg), 3000);
}
```

---

## REMAINING MIGRATION TASKS (Phase 10.2)

1. Migrate `forms/core-ui/components/modal.blade.php` → `x-core-ui::modal`
2. Migrate `forms/core-ui/components/toast.blade.php` → `x-core-ui::toast`
3. Migrate `forms/core-ui/components/empty-state.blade.php` → `x-core-ui::empty-state`
4. Remove duplicate `notification-center` in forms module
5. Builder panel floating container: replace `style="width:340px"` with CSS token
6. Builder modal: replace `z-index: 50` with `z-[var(--ui-z-modal)]`
7. Add mobile layout to builder.blade.php (3-panel → collapsible single column)

---

## FORMS AS PARITY TEST

The forms module is used as the baseline test for cross-context parity:
- Platform admin renders forms list → validates admin-context rendering
- Tenant admin renders same list → validates tenant-context gate rendering
- Customer site renders public forms → validates public-context rendering

All three must produce identical visual output except for permission-gated elements.
