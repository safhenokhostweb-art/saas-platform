# Phase 10.2.8 — Tailwind Runtime Audit
## Frontend Engine Comparison: saas-platform vs customer-site

**Audit Date:** 2026-05-29  
**Auditor:** Autonomous Phase 10.2.8 Engine  

---

## 1. Runtime Version Matrix

| Dimension                 | saas-platform (BEFORE)     | customer-site              | Status         |
|---------------------------|----------------------------|----------------------------|----------------|
| tailwindcss               | 3.4.19                     | 4.2.0                      | ❌ DRIFT       |
| @tailwindcss/vite         | ❌ absent                  | 4.2.0                      | ❌ DRIFT       |
| PostCSS                   | 8.5.15 (tailwindcss + autoprefixer) | ❌ absent         | ❌ DRIFT       |
| autoprefixer              | 10.5.0                     | ❌ absent                  | ❌ DRIFT       |
| Vite                      | 5.0.x                      | 7.3.1                      | ❌ DRIFT       |
| laravel-vite-plugin       | 1.0.x                      | 2.1.0                      | ❌ DRIFT       |
| tailwind.config.js        | ✅ present (v3 style)       | ❌ absent (CSS-based)       | ❌ DRIFT       |
| postcss.config.js         | ✅ present                  | ❌ absent                  | ❌ DRIFT       |
| daisyui                   | 5.5.20 (unused)            | ❌ absent                  | ⚠️ ORPHAN      |
| autocss / init / npx      | present (unused)           | ❌ absent                  | ⚠️ ORPHAN      |
| alpinejs                  | 3.15.12                    | 3.15.12                    | ✅ PARITY      |

---

## 2. CSS Entrypoint Comparison

### saas-platform — resources/css/app.css (BEFORE)
```css
@tailwind base;          /* ❌ v3-only — removed in v4 */
@tailwind components;    /* ❌ v3-only — removed in v4 */
@tailwind utilities;     /* ❌ v3-only — removed in v4 */

@import '../../packages/saas-platform/core-utils/resources/css/design-tokens.css';
/* NO @source directives */
/* NO @theme block */
/* NO @utility blocks */
```

### customer-site — resources/css/app.css (CANONICAL v4)
```css
@import 'tailwindcss';   /* ✅ v4 canonical */
@import '../../../saas-platform/packages/saas-platform/core-utils/resources/css/design-tokens.css';
@source '...';           /* ✅ explicit content scanning */
@theme { ... }           /* ✅ CSS-based theme config */
@utility font-vazir { }  /* ✅ v4 utility block */
```

---

## 3. Build Pipeline Comparison

### saas-platform vite.config.js (BEFORE)
```js
import laravel from 'laravel-vite-plugin'
// ❌ No @tailwindcss/vite plugin
// ❌ Tailwind processed via PostCSS (v3 path)
```

### customer-site vite.config.js (CANONICAL v4)
```js
import tailwindcss from '@tailwindcss/vite'
// ✅ @tailwindcss/vite processes CSS directly in Vite
// ✅ No PostCSS chain needed
```

---

## 4. v3-Only Syntax Identified

### In resources/css/app.css (saas-platform only)
| Pattern                      | File                           | Status           |
|------------------------------|--------------------------------|------------------|
| `@tailwind base`             | resources/css/app.css:1        | ❌ v3-only       |
| `@tailwind components`       | resources/css/app.css:2        | ❌ v3-only       |
| `@tailwind utilities`        | resources/css/app.css:3        | ❌ v3-only       |

### In tailwind.config.js (v3 config format)
| Pattern                      | Status                         |
|------------------------------|--------------------------------|
| JS-based content array       | ❌ v3-only (v4 uses @source)   |
| theme.extend.fontFamily      | ❌ v3-only (v4 uses @theme)    |
| theme.extend.colors          | ❌ v3-only (v4 uses @theme)    |
| plugins: []                  | ❌ v3-only (v4 uses @plugin)   |

### In postcss.config.js
| Pattern                      | Status                         |
|------------------------------|--------------------------------|
| tailwindcss: {} plugin       | ❌ Not used in v4 Vite flow    |
| autoprefixer: {} plugin      | ❌ Not needed with @tailwindcss/vite |

---

## 5. Migration Risks

| Risk                                      | Severity | Mitigation                             |
|-------------------------------------------|----------|----------------------------------------|
| `@tailwind` directives cause build failure | HIGH     | Replace with `@import "tailwindcss"`   |
| tailwind.config.js ignored in v4          | HIGH     | Convert to CSS @theme + @source        |
| content scanning lost without @source     | HIGH     | Add explicit @source paths             |
| ring default changed: 3px → 1px           | MEDIUM   | Blade files use ring-2 (explicit) ✅   |
| Default border color changed              | LOW      | app.css overrides in @layer base ✅    |
| daisyui not registered as plugin          | LOW      | Remove from package.json (unused)      |
| laravel-vite-plugin v1 → v2 API changes   | LOW      | Same input/refresh API, compatible     |
| Vite 5 → 7 config API changes            | LOW      | defineConfig API unchanged             |

---

## 6. Core-UI Component v4 Compatibility

All 35+ `x-core-ui::*` components have been audited. Since customer-site (Tailwind v4.2.0) 
shares the SAME package views and operates correctly, all utilities used are v4-compatible:

| Component           | v4 Utilities Used              | Status       |
|---------------------|--------------------------------|--------------|
| button.blade.php    | focus-visible:ring-2, shadow-sm| ✅ Compatible |
| modal.blade.php     | z-50, overflow-hidden          | ✅ Compatible |
| dropdown.blade.php  | shadow-[var(...)], z-[var(...)]| ✅ Compatible |
| input.blade.php     | focus-visible:ring-2           | ✅ Compatible |
| alert.blade.php     | bg-black/10, ring-2            | ✅ Compatible |
| data-table.blade.php| shadow-[var(...)]              | ✅ Compatible |
| All others          | CSS-var-driven, no v3-specific | ✅ Compatible |

Conclusion: Zero core-ui component changes required for v4 compatibility.

---

## 7. Forms Builder v4 Compatibility

Forms builder (builder.blade.php) uses:
- SortableJS (external CDN, not Tailwind-dependent)  
- Alpine.js x-data, x-bind, x-show (not Tailwind-dependent)
- Custom CSS classes from app.css (.field-card, .builder-palette, etc.)  
- Tailwind utilities: flex, gap-*, p-*, text-*, bg-*, border-*, rounded-*

All are v4-compatible. The `@layer components` custom CSS in app.css is still valid in v4.

---

## 8. Design Token System Compatibility

design-tokens.css uses only CSS custom properties (`:root { --ui-* }`) and `@keyframes`.
This is pure CSS — 100% compatible with any Tailwind version.

---

## 9. Drift-Producing Patterns Summary

1. **PRIMARY ROOT CAUSE**: `tailwindcss@3.4.19` vs `tailwindcss@4.2.0` — different runtime = different utility output
2. **SECONDARY**: PostCSS chain vs Vite plugin — different build paths, different output optimization
3. **TERTIARY**: Missing `@source` paths in saas-platform — potential utility purging divergence
4. **TERTIARY**: Missing `@theme` block — font/color utilities generated differently in v3 vs v4

---

## 10. Migration Plan (Phase 10.2.8)

| Step | Action                                                    | Files                            |
|------|-----------------------------------------------------------|----------------------------------|
| 1    | Update package.json: tw@4.2, @tailwindcss/vite, vite@7   | package.json                     |
| 2    | Update vite.config.js: add @tailwindcss/vite plugin       | vite.config.js                   |
| 3    | Delete postcss.config.js (Vite plugin replaces PostCSS)  | postcss.config.js                |
| 4    | Delete tailwind.config.js (CSS-based config replaces)    | tailwind.config.js               |
| 5    | Rewrite app.css: @import, @source, @theme                 | resources/css/app.css            |
| 6    | Create governance services (3 services, 3 commands)       | packages/.../Services/*.php      |
| 7    | Register in providers                                     | CoreUtilsServiceProvider.php     |
| 8    | Expand CI workflows                                       | .github/workflows/               |
| 9    | Run npm install + build                                   | —                                |

---

## 11. Post-Migration Expected State

| Dimension           | saas-platform (AFTER) | customer-site     | Status      |
|---------------------|-----------------------|-------------------|-------------|
| tailwindcss         | 4.2.x                 | 4.2.x             | ✅ PARITY   |
| @tailwindcss/vite   | 4.2.x                 | 4.2.x             | ✅ PARITY   |
| vite                | 7.x                   | 7.3.1             | ✅ PARITY   |
| laravel-vite-plugin | 2.1.x                 | 2.1.x             | ✅ PARITY   |
| CSS entrypoint      | @import "tailwindcss" | @import "tailwindcss" | ✅ PARITY |
| Config format       | CSS @theme            | CSS @theme        | ✅ PARITY   |
| Content scanning    | CSS @source           | CSS @source       | ✅ PARITY   |
| PostCSS chain       | absent                | absent            | ✅ PARITY   |
| tailwind.config.js  | absent                | absent            | ✅ PARITY   |
| postcss.config.js   | absent                | absent            | ✅ PARITY   |
