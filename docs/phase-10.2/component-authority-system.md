# Component Authority System

**Phase:** 10.2  
**Service:** `CanonicalComponentAuthorityService`  
**Command:** `php artisan frontend:validate-component-authority`

---

## What it Enforces

The component authority system prevents any raw HTML control from appearing in Blade views that are outside the canonical component definitions.

### Forbidden Raw Controls

| Raw Tag | Required Replacement |
|---------|---------------------|
| `<button>` | `<x-core-ui::button>` |
| `<input>` | `<x-core-ui::input>` |
| `<select>` | `<x-core-ui::select>` |
| `<textarea>` | `<x-core-ui::textarea>` |

### Why Raw Controls Are Forbidden

Raw controls:
1. Do not inherit design tokens automatically
2. Do not have consistent ARIA attributes
3. Do not have consistent focus-visible styling
4. Must be manually updated in N places if a design decision changes
5. Break accessibility consistency

`x-core-ui::*` components:
1. Inherit all `--ui-*` tokens automatically
2. Include proper ARIA attributes
3. Use `focus-visible:` instead of `focus:`
4. Are updated in ONE place (the component file)
5. Are accessibility-certified

---

## Architectural Exception: Alpine Template Controls

Controls inside `<template x-for>` blocks are rendered by the JavaScript runtime, not by Blade. Laravel Blade components cannot be used inside Alpine.js templates.

These are the **only** accepted exception to the raw-control rule.

Documentation required:
```blade
{{--
  GOVERNANCE EXCEPTION: controls inside this x-for template are Alpine-rendered.
  x-core-ui Blade components cannot be used in client-side Alpine templates.
--}}
<template x-for="...">
  <button @click="...">...</button>
</template>
```

---

## Command Usage

```bash
# Basic validation
php artisan frontend:validate-component-authority

# CI mode — fails if any violation exists
php artisan frontend:validate-component-authority --strict --fail-on-drift

# JSON output for CI pipelines
php artisan frontend:validate-component-authority --json

# Treat warnings as errors
php artisan frontend:validate-component-authority --fail-on-warning
```

---

## Scan Scope

The service audits all `.blade.php` files in:
- `packages/saas-platform/core-utils/resources/views/` (except `/components/`)
- `packages/saas-platform/modules/forms/resources/views/`
- `packages/saas-platform/modules/tickets/Resources/views/`
- `resources/views/`
- `../customer-site/resources/views/` (if present)

---

## Violation Categories

| Category | Weight | Description |
|----------|--------|-------------|
| `raw_control` | Critical | Raw `<button>`, `<input>`, etc. outside Alpine templates |
| `inline_style` | High | `style=""` attribute on any element |
| `hardcoded_color` | High | `#hex`, `rgba()` in style= context |
| `forbidden_tw` | Warning | Hardcoded Tailwind color utilities like `bg-blue-500` |
| `duplicate_modal` | Warning | Custom `role="dialog"` outside `x-core-ui::modal` |
