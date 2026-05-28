# Future Module Governance
## Phase 10.2.5

---

## The Zero-Setup Guarantee

Any new module added to `packages/saas-platform/modules/` automatically inherits the
complete enterprise frontend architecture **without any manual setup**. This is
enforced structurally at the service provider level.

---

## How Inheritance Works

### Boot-Time (automatic)

On every request, `CoreUtilsServiceProvider::boot()` calls:

```php
$this->app->make(ModuleFrontendInheritanceService::class)->bootInheritance();
```

This automatically:
1. Scans `packages/saas-platform/modules/*/resources/views`
2. Registers every discovered module's `components/` directory as an anonymous component namespace
3. Ensures `x-core-ui::*` always takes priority over any module-local component of the same name

### Design Token Inheritance

All admin views extend `layouts.admin`, which imports `design-tokens.css`.
No per-module CSS setup is needed.

### Component Namespace Inheritance

`x-core-ui::*` components are registered globally in `CoreUtilsServiceProvider`.
Every module has access to every canonical component without importing anything.

---

## Module Scaffolding

Use `ModuleFrontendInheritanceBootstrapper` when creating a new module:

```php
$bootstrapper = app(ModuleFrontendInheritanceBootstrapper::class);

// Get governance checklist
$checklist = $bootstrapper->generateChecklist('invoices');

// Generate scaffold views
$indexView  = $bootstrapper->scaffoldView('invoices', 'index');
$showView   = $bootstrapper->scaffoldView('invoices', 'show');
$createView = $bootstrapper->scaffoldView('invoices', 'create');

// Validate compliance after creation
$validation = $bootstrapper->validateModuleInheritance(
    base_path('packages/saas-platform/modules/invoices/resources/views')
);
```

---

## What New Modules Inherit Automatically

| Feature | Inherited from | Manual setup needed |
|---------|---------------|---------------------|
| Design tokens | `layouts.admin` imports tokens | None |
| `x-core-ui::*` components | `CoreUtilsServiceProvider` | None |
| RTL support | Token layer + logical CSS | None |
| Reduced motion | Token layer | None |
| Dark mode tokens | Token layer | None |
| Responsive breakpoints | Token layer | None |
| Governance validators | `ModuleFrontendInheritanceService` | None |
| CI enforcement | Module path covered by workflow | None |
| Accessibility defaults | Core-ui components enforce WCAG AA | Use canonical components |

---

## Module Governance Validation

After creating a module, validate its governance compliance:

```bash
php artisan frontend:validate-enterprise-governance --fail-below=95
php artisan frontend:validate-components --strict --module=invoices
php artisan frontend:lockdown-report
```

---

## Required View Structure

Every new module MUST follow this structure for admin views:

```blade
{{-- resources/views/admin/invoices/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Invoices')

@section('content')
    <x-core-ui::page-header title="Invoices" />

    <x-core-ui::content-container>
        <x-core-ui::data-table
            :columns="$columns"
            :rows="$invoices"
        />
    </x-core-ui::content-container>
@endsection
```

**Never:**
- `@extends('layouts.custom')` — must extend canonical admin layout
- Inline `<style>` blocks — use token utilities
- Raw `<button>`, `<input>`, etc. — use `x-core-ui::*`
- Hardcoded `#hex` colors — use `var(--ui-*)`
- Custom `z-index` values — use `var(--ui-z-*)`

---

## Future Module Checklist

Before opening a PR for a new module:

- [ ] All admin views extend `layouts.admin`
- [ ] No inline `<style>` blocks
- [ ] No raw HTML form controls
- [ ] No hardcoded hex colors in style attributes
- [ ] `php artisan frontend:validate-components --strict` passes
- [ ] `php artisan frontend:validate-enterprise-governance --fail-below=95` passes
- [ ] `php artisan frontend:lockdown-report` shows 0 CRITICAL items
