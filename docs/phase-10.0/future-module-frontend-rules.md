# Future Module Frontend Rules — Phase 10.0

## Mandatory Rules for All Future Modules

Every module created after Phase 10.0 MUST follow these rules without exception.

### Rule 1: No Module-Local CSS Files
```
packages/saas-platform/modules/my-module/resources/css/  ← DOES NOT EXIST
```
All styling via `--ui-*` tokens and `x-core-ui::*` components.

### Rule 2: No Inline `@keyframes`
Any animation MUST use canonical keyframes from `design-tokens.css`:
```css
/* ✓ Allowed */
animation: ui-skeleton-wave 1.4s ease infinite;
animation: ui-pulse-dot 2s ease-in-out infinite;
animation: ui-fade-in 150ms ease-out;

/* ✗ Forbidden */
@keyframes myModuleAnimation { ... }
```

### Rule 3: `<x-core-ui::design-tokens />` in Every View
```blade
@section('content')
<x-core-ui::design-tokens />
{{-- content --}}
@endsection
```

### Rule 4: Use `x-core-ui::button` for All Actions
```blade
{{-- ✓ Correct --}}
<x-core-ui::button variant="primary" href="{{ route(...) }}">اضافه کن</x-core-ui::button>
<x-core-ui::button variant="danger" @click="confirmDelete()">حذف</x-core-ui::button>

{{-- ✗ Forbidden --}}
<a class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white ...">اضافه کن</a>
```

### Rule 5: Use `x-core-ui::confirm-modal` for Destructive Actions
```blade
{{-- ✓ Correct --}}
<x-core-ui::confirm-modal title="حذف" message="..." variant="danger" @confirmed="doDelete()" />

{{-- ✗ Forbidden --}}
<div x-show="showDel" class="fixed inset-0 z-50 bg-black/40">...</div>
```

### Rule 6: Style Blocks ONLY Reference `--ui-*` Variables
```blade
@push('head')
<style>
/* ✓ Allowed — references canonical tokens */
.my-module-widget { border-radius: var(--ui-radius-xl); box-shadow: var(--ui-shadow-card); }

/* ✗ Forbidden — hardcoded values */
.my-module-widget { border-radius: 16px; box-shadow: 0 8px 32px rgba(15,23,42,.06); }
</style>
@endpush
```

### Rule 7: No Color Utility Classes from Tailwind
```blade
{{-- ✗ Forbidden --}}
<span class="bg-blue-500 text-white">...</span>

{{-- ✓ Allowed — use status components or token-driven classes --}}
<x-core-ui::badge variant="info">Active</x-core-ui::badge>
<span class="bg-[var(--ui-info)] text-[var(--ui-text-inverse)]">...</span>
```

### Rule 8: Navigation via Canonical Components
```blade
{{-- In module's navigation registration --}}
<x-core-ui::sidebar-group label="مدیریت">
    <x-core-ui::navigation-item href="/admin/my-module" :active="request()->is('admin/my-module*')">
        <x-slot name="icon"><!-- SVG --></x-slot>
        ماژول من
    </x-core-ui::navigation-item>
</x-core-ui::sidebar-group>
```

### Rule 9: Run Authority Validation Before Release
```bash
php artisan frontend:validate-authority --strict
# Must return score ≥ 90 and grade A- or above
```

### Rule 10: No Published Views That Override Core-UI
```php
// ✗ Forbidden
$this->publishes([
    __DIR__ . '/resources/views' => resource_path('views/vendor/my-module'),
], 'my-module-views');
// This would allow operators to override module views with non-canonical implementations.

// ✓ If publishing is required, document that overrides must maintain token compliance.
```

## Template ServiceProvider Boot Method

```php
public function boot(): void
{
    $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'my-module');

    // Do NOT re-register core-ui namespace — CoreUtilsServiceProvider owns it.
    // Only register if adding NEW components not in core-utils or forms.

    $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
}
```
