# Canonical UI Runtime — Phase 10.0

## Runtime Stack

```
Browser
  └─ app.css (compiled by Vite)
       ├─ @import design-tokens.css  ← canonical variables + keyframes
       ├─ @tailwind base/components/utilities  (or @import 'tailwindcss' in v4)
       └─ @layer components { ... }  ← canonical utility classes

  └─ app.js (compiled by Vite)
       ├─ Alpine.js (shared Alpine bootstrapping)
       └─ helpers.js

  └─ Blade views
       ├─ x-core-ui::design-tokens   ← injects module-specific overrides per-page
       ├─ x-core-ui::button          ← registered from core-utils (canonical)
       ├─ x-core-ui::modal           ← registered from forms module
       └─ ... all other x-core-ui::* components
```

## Vite Pipeline

### saas-platform (Tailwind v3)
```js
// vite.config.js
import laravel from 'laravel-vite-plugin'
export default defineConfig({
    plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'] })]
})
```

### customer-site (Tailwind v4)
```js
// vite.config.js
import tailwindcss from '@tailwindcss/vite'
import laravel from 'laravel-vite-plugin'
export default defineConfig({
    plugins: [tailwindcss(), laravel({ input: ['resources/css/app.css', 'resources/js/app.js'] })]
})
```

Both pipelines import the same `design-tokens.css`. CSS custom properties work identically in both Tailwind versions because they operate at the browser level, not the compiler level.

## Alpine.js Runtime

Both projects bootstrap Alpine.js via `app.js`. No per-module Alpine initialization.

All `x-core-ui::*` interactive components use Alpine directives (`x-show`, `x-transition`, `x-data`, `@click`, `@keydown.escape`). No jQuery, no custom event system.

## Component Namespace Resolution

```php
// CoreUtilsServiceProvider::boot() — runs FIRST
Blade::anonymousComponentPath(
    __DIR__ . '/../../resources/views/components',  // core-utils canonical
    'core-ui'
);

// FormsServiceProvider::boot() — runs SECOND (additive)
Blade::anonymousComponentPath(
    __DIR__ . '/../../resources/views/core-ui/components',  // forms module
    'core-ui'
);
```

Blade resolves `x-core-ui::button` by searching registered directories in order. First match wins.

## Icon System

Icons use inline SVG throughout. No icon font dependencies. All icons are 16×16 or 20×20 viewBox SVGs using `currentColor` for token-driven coloring.

## Toast Runtime

```blade
{{-- Include once per layout --}}
<x-core-ui::toast />

{{-- Dispatch from anywhere --}}
<button @click="$dispatch('toast', { message: 'Saved!', type: 'success' })">Save</button>
```

## Modal Runtime

```blade
{{-- Include once per layout --}}
<x-core-ui::modal title="Confirm" show="showModal" @close="showModal = false">
    Modal content
    <x-slot name="footer">
        <x-core-ui::button @click="showModal = false" variant="secondary">Cancel</x-core-ui::button>
        <x-core-ui::button variant="danger" @click="deleteItem()">Delete</x-core-ui::button>
    </x-slot>
</x-core-ui::modal>
```

## Table Runtime

All tables use `x-core-ui::data-table` wrapper with `x-core-ui::table-pagination`. Action menus use `x-core-ui::action-menu` with `x-core-ui::dropdown-item`.

## Future Module Runtime Inheritance

Any new module automatically inherits the full UI runtime by:
1. Not registering any module-local CSS file
2. Using `x-core-ui::*` components exclusively
3. Referencing `--ui-*` tokens in any module-local `<style>` blocks
4. Injecting `<x-core-ui::design-tokens />` once in the layout or first content section
