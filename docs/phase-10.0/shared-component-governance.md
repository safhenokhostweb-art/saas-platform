# Shared Component Governance — Phase 10.0

## Component Registry

All `x-core-ui::*` components are governed by `CanonicalComponentRegistry`.

```bash
# Inspect registry at runtime
php artisan frontend:validate-authority --json | jq '.components'
```

## Adding a New Canonical Component

1. Create file in `core-utils/resources/views/components/<name>.blade.php`
2. Use `@props([...])` for all configurable values
3. Reference only `--ui-*` CSS variables for styling
4. Document props in the file's docblock comment
5. Update `REQUIRED_COMPONENTS` in `FrontendAuthorityValidationService.php`

```blade
{{--
  x-core-ui::my-component — Description.
  Props:
    variant : primary | secondary (default: primary)
    size    : sm | md | lg (default: md)
--}}
@props(['variant' => 'primary', 'size' => 'md'])

<div {{ $attributes->class(['...canonical classes using var(--ui-*) tokens...']) }}>
    {{ $slot }}
</div>
```

## Modifying Existing Canonical Components

1. Edit the file in `core-utils/resources/views/components/`
2. Ensure backward compatibility (new props should have defaults)
3. Run `php artisan frontend:validate-authority`
4. Test in both saas-platform and customer-site contexts

**Critical:** All changes to canonical components propagate instantly to ALL modules and ALL tenants. Test carefully.

## Component Versioning

Components do not have individual version numbers. The core-utils package version governs them. Breaking changes require a major version bump of core-utils.

## Module-Level Components

Modules may add components to the `core-ui` namespace for module-specific UI needs:

```php
// In MyModuleServiceProvider::boot()
Blade::anonymousComponentPath(
    __DIR__ . '/../../resources/views/core-ui/components',
    'core-ui'
);
```

**Rules for module-level components:**
- Must not duplicate a component already in core-utils
- Must follow the same `--ui-*` token rules
- Must have `x-core-ui::module-name-*` naming convention to avoid conflicts
- Example: `x-core-ui::forms-status-pill` not `x-core-ui::status-pill`

## Component API Contract

All canonical components MUST:
- Use `@props([...])` with sensible defaults
- Accept `$attributes` merge for extra classes/attributes
- Use `{{ $slot }}` for content slots
- Reference only canonical CSS variables
- Not embed module-specific business logic
- Work identically in saas-platform and customer-site

## Deprecated Components

When a component in `core-utils/resources/views/components/` from earlier phases becomes obsolete:
1. Mark with `{{-- @deprecated Phase X.X — use x-core-ui::new-component --}}`
2. Keep file for one release cycle
3. Remove in subsequent cleanup phase
