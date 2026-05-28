# Enterprise UX Standards — Phase 10.0

## Interaction Model

All interactive elements MUST conform to these UX standards.

### Hover States
```css
/* All interactive elements use canonical hover tokens */
transition: var(--ui-transition);          /* 150ms ease */
&:hover { transform: translateY(-2px); }   /* lift pattern */
&:hover { box-shadow: var(--ui-shadow-card-hover); }
```

### Focus States (Accessibility)
```html
<!-- All focusable elements use canonical focus ring -->
class="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ui-focus-ring-color)]"
```

No element may suppress focus styles (`outline: none`) without providing an equivalent `box-shadow` focus ring.

### Loading States
```blade
{{-- Skeleton loading --}}
<div class="ui-skeleton h-6 w-32"></div>  <!-- canonical skeleton class -->
<div class="ui-skeleton h-4 w-full"></div>

{{-- Button loading --}}
<x-core-ui::button :loading="true" variant="primary">در حال ذخیره...</x-core-ui::button>
```

### Transitions
```css
/* Page transitions */
animation: ui-fade-in var(--ui-duration-normal) var(--ui-ease-out);

/* Modal appearance */
animation: ui-scale-in var(--ui-duration-normal) var(--ui-ease-out);

/* Toast slide */
animation: ui-slide-up var(--ui-duration-normal) var(--ui-ease-out);
```

## RTL Support

All views render in Right-to-Left mode for Persian language:

```html
<html lang="fa" dir="rtl">
```

CSS variables provide RTL context:
```css
--ui-text-align: right;
--ui-dir:        rtl;
```

Icon and layout mirroring is handled by `dir="rtl"` at the document level — no per-component RTL fixes needed.

## Persian Typography

```css
/* All text uses canonical Persian font stack */
font-family: var(--ui-font-persian);
/* = 'Vazirmatn', 'Tahoma', ui-sans-serif, system-ui, sans-serif */
```

Font weight scale:
- Labels: `var(--ui-weight-semibold)` (600)
- Body: `var(--ui-weight-normal)` (400)
- Headings: `var(--ui-weight-bold)` (700)
- Stats/numbers: `var(--ui-weight-extrabold)` (800)

## Keyboard Navigation

All interactive components support:
- `Tab` / `Shift+Tab` — focus navigation
- `Enter` / `Space` — activate buttons and links
- `Escape` — close modals, dropdowns, dialogs
- `Arrow keys` — navigate tabs and menus (where implemented)

## Modal Accessibility

```blade
<x-core-ui::modal title="Dialog Title" ...>
    <!-- Blade modal component handles:
         - aria-modal="true"
         - role="dialog"
         - aria-labelledby
         - Escape key close
         - Focus trap (via Alpine's x-trap if available)
    -->
</x-core-ui::modal>
```

## Toast Behavior

- Auto-dismiss after 4 seconds (success/info)
- Stays until dismissed manually (error/warning)
- Maximum 3 visible simultaneously
- RTL positioning: top-right corner
- Uses `ui-slide-up` animation

## Empty States

All empty list/table states use `x-core-ui::empty-state`:

```blade
<x-core-ui::empty-state
    title="هیچ موردی یافت نشد"
    description="برای شروع، یک مورد جدید اضافه کنید."
    action-label="افزودن"
    action-href="{{ route(...) }}"
/>
```

## Sticky Toolbars

Filter/action toolbars that require scrolling use:
```blade
<div class="sticky top-0 z-[var(--ui-z-sticky)] ...">
    <!-- toolbar content -->
</div>
```

## Mobile Responsiveness

All module views must work on screens ≥ 320px. Table views collapse to card view on mobile via `sm:hidden` / `hidden sm:block` pattern.

## Data Table Interactions

```blade
{{-- Hover highlights row --}}
<tr class="ticket-row">  <!-- canonical .ticket-row hover styles from design-tokens -->

{{-- Checkbox selection --}}
<x-core-ui::checkbox name="select[]" :value="$item->id" ... />

{{-- Bulk toolbar appears on selection --}}
<x-core-ui::bulk-toolbar :count="bulkSelected.length" x-show="bulkSelected.length > 0" />
```
