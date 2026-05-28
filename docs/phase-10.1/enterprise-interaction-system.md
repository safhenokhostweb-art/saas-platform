# Enterprise Interaction System — Phase 10.1

## INTERACTION TOKEN SYSTEM

All interactive states are governed by canonical tokens:

```css
/* Focus */
--ui-focus-ring:        0 0 0 3px rgb(99 102 241 / 0.25);
--ui-focus-ring-color:  #6366f1;
--ui-focus-ring-width:  3px;
--ui-focus-ring-offset: 2px;

/* Hover */
--ui-hover-overlay:  rgba(99, 102, 241, 0.04);
--ui-hover-bg:       rgba(99, 102, 241, 0.06);

/* Active */
--ui-active-overlay: rgba(99, 102, 241, 0.08);
--ui-active-bg:      rgba(99, 102, 241, 0.10);

/* Selected */
--ui-selected-bg:     rgba(99, 102, 241, 0.08);
--ui-selected-border: var(--ui-indigo);

/* Disabled */
--ui-disabled-opacity: 0.50;
--ui-disabled-cursor:  not-allowed;
```

---

## ALPINE.JS INTERACTION PATTERNS

### Loading State Pattern
```javascript
x-data="{
    loading: false,
    async submit(url, data) {
        this.loading = true;
        try {
            const res = await fetch(url, { method: 'POST', body: JSON.stringify(data) });
            if (!res.ok) throw new Error(await res.text());
            this.$dispatch('ui-toast', { message: 'ذخیره شد', variant: 'success' });
        } catch(e) {
            this.$dispatch('ui-toast', { message: 'خطا: ' + e.message, variant: 'danger' });
        } finally {
            this.loading = false;
        }
    }
}"
```

### Optimistic UI Pattern
```javascript
x-data="{
    items: @json($items),
    remove(id) {
        const backup = [...this.items];
        this.items = this.items.filter(i => i.id !== id);
        fetch(`/api/items/${id}`, { method: 'DELETE' })
            .then(res => { if (!res.ok) throw new Error(); this.$dispatch('ui-toast', { message: 'حذف شد', variant: 'success' }); })
            .catch(() => { this.items = backup; this.$dispatch('ui-toast', { message: 'خطا در حذف', variant: 'danger' }); });
    }
}"
```

### Autosave Pattern
```javascript
x-data="{
    dirty: false,
    saving: false,
    saved: false,
    init() {
        this.$watch('formData', () => { this.dirty = true; this.autosave(); });
    },
    async autosave() {
        if (this.saving) return;
        await new Promise(r => setTimeout(r, 800)); // debounce
        this.saving = true;
        try {
            await fetch('/api/draft', { method: 'POST', body: JSON.stringify(this.formData) });
            this.saved = true;
            this.dirty = false;
            setTimeout(() => this.saved = false, 3000);
        } finally { this.saving = false; }
    }
}"
```

---

## KEYBOARD NAVIGATION REQUIREMENTS

| Component | Required Keys |
|-----------|--------------|
| Button | `Enter`, `Space` |
| Modal | `Escape` to close, focus trapping |
| Dropdown | `Arrow Down/Up`, `Escape`, `Enter` |
| Table rows | `Arrow Up/Down` for row navigation |
| Switch | `Space` to toggle, `Enter` to toggle |
| Tabs | `Arrow Left/Right` to switch |

---

## ANIMATION TIMING STANDARDS

| Interaction | Duration | Easing |
|-------------|---------|--------|
| Hover state | `--ui-duration-fast` (100ms) | default |
| Focus ring | `--ui-duration-fast` (100ms) | default |
| Button press | `--ui-duration-fast` (100ms) | default |
| Dropdown open | `--ui-duration-moderate` (250ms) | ease-out |
| Modal enter | `--ui-duration-moderate` (250ms) | spring |
| Page transition | `--ui-duration-slow` (300ms) | ease-out |
| Toast slide-in | `--ui-duration-moderate` (250ms) | spring |
| Skeleton wave | 1400ms | ease infinite |

---

## MICROINTERACTION CATALOG

### Button Press
```css
/* Applied via x-core-ui::button */
active:scale-[var(--ui-btn-active-scale)]  /* 0.97 */
transition: var(--ui-btn-transition)
```

### Card Hover
```css
/* Applied via .ui-card */
transform: translateY(-2px);
box-shadow: var(--ui-shadow-card-hover);
```

### Input Focus
```css
/* Applied via x-core-ui::input */
box-shadow: var(--ui-focus-ring);
border-color: var(--ui-indigo);
```

### Switch Toggle
Spring animation: `cubic-bezier(0.34, 1.56, 0.64, 1)` — creates satisfying overshoot.
