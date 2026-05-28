# Module Frontend Inheritance Architecture

**Phase:** 10.2  
**Service:** `ModuleFrontendInheritanceService`

---

## Overview

Every module — whether built today or added two years from now — automatically inherits the full canonical frontend system. No manual frontend setup is required.

This is enforced at **boot time** in `CoreUtilsServiceProvider::boot()`:

```php
$this->app->make(ModuleFrontendInheritanceService::class)->bootInheritance();
```

This call:
1. Discovers all module view directories matching `packages/saas-platform/modules/*/resources/views`
2. Registers each module's `components/` subdirectory as a Blade anonymous component namespace
3. Ensures `core-ui` is always registered FIRST (highest authority priority)

---

## What a New Module Gets for Free

When a new module is installed and its view directory is discovered:

| Feature | How |
|---------|-----|
| Design tokens | `app.css` imports `design-tokens.css` globally |
| Canonical components | `x-core-ui::*` available in all Blade templates |
| Governance validation | `frontend:validate-parity` scans the new module automatically |
| CI enforcement | The governance workflow validates all `*.blade.php` files including new modules |
| Responsive rules | Governed by `ResponsiveGovernanceValidationService` |
| Accessibility | Governed by `AccessibilityGovernanceValidationService` |

---

## Compliance Requirements for Module Views

Every module admin view must:

```blade
@extends('layouts.admin')              ← REQUIRED: canonical layout

@section('content')
<x-core-ui::design-tokens />           ← Token injection if module has overrides
<x-core-ui::page-header title="..." /> ← Use canonical page header
...
@endsection
```

### Forbidden in Module Views

```blade
{{-- ❌ Module-specific inline styles --}}
@push('head')
<style>
  .my-module-button { color: #4F46E5; }
</style>
@endpush

{{-- ❌ Raw controls --}}
<button class="bg-indigo-600 text-white px-4 py-2">...</button>

{{-- ❌ Module-specific token definitions --}}
--forms-primary: #4F46E5;

{{-- ❌ Hardcoded colors --}}
<div style="background: #f8f9fa">...</div>
```

---

## Generating Compliance Checklist for a New Module

```bash
# Via service (from PHP code)
$checklist = app(ModuleFrontendInheritanceService::class)
    ->generateInheritanceChecklist('my-new-module');
```

Returns a complete checklist of:
- Required file structure
- Required `@extends` declarations
- Required component inclusions
- Forbidden patterns
- Canonical component mapping

---

## Validation

```bash
# Validate all module inheritance
php artisan frontend:validate-parity --json | jq '.dimensions.module_inheritance'

# Full parity audit
php artisan frontend:validate-parity
```
