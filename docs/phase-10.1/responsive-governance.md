# Responsive Governance — Phase 10.1

## CANONICAL BREAKPOINTS

```css
--ui-breakpoint-xs:  480px;
--ui-breakpoint-sm:  640px;
--ui-breakpoint-md:  768px;
--ui-breakpoint-lg:  1024px;
--ui-breakpoint-xl:  1280px;
--ui-breakpoint-2xl: 1536px;
```

Tailwind config should reflect these via `theme.screens`.

---

## TOUCH TARGET MINIMUM

All interactive elements (buttons, links, form controls) MUST meet:

```css
min-height: var(--ui-touch-target-min);  /* 44px */
min-width:  var(--ui-touch-target-min);  /* 44px */
```

Use the `.ui-touch-target` utility class for icon-only elements.

---

## TABLE RESPONSIVENESS

**All tables MUST be wrapped:**
```blade
<div class="overflow-x-auto">
    <table>...</table>
</div>
```

On mobile (< 640px), consider:
- Showing fewer columns (hide with `hidden sm:table-cell`)
- Collapsing rows to card layout
- Using `x-core-ui::data-table` which handles this automatically

---

## MODAL MOBILE BEHAVIOR

`x-core-ui::modal` handles mobile automatically:
- Desktop: centered dialog, max-width constrained
- Mobile (< 640px): full-width with `inset-x-4` positioning

Custom modals MUST replicate this behavior.

---

## SIDEBAR MOBILE BEHAVIOR

Admin sidebar on mobile MUST:
1. Be hidden by default (`hidden lg:flex`)
2. Toggle via an Alpine `x-show` controlled by a hamburger button
3. Render as an overlay (full height, z-index: `--ui-z-drawer`)
4. Close on backdrop tap or Escape key

---

## REDUCED MOTION SUPPORT

`design-tokens.css` includes:
```css
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

This is **automatic** for all canonical token-based animations.
Custom `@keyframes` in Blade views must NOT bypass this.

---

## RTL LAYOUT RULES

Platform uses Persian (RTL). Rules:
1. Use `--ui-text-align: right` for text alignment
2. Avoid `margin-left/right` directly — use `margin-inline-start/end`
3. Arrow icons must flip direction in RTL: `[dir=rtl] .arrow { transform: scaleX(-1); }`
4. Test all new views with `dir="rtl"` attribute

---

## VALIDATION

Run after every frontend change:
```bash
php artisan frontend:validate-responsive
php artisan frontend:validate-responsive --strict
php artisan frontend:validate-responsive --json
```
