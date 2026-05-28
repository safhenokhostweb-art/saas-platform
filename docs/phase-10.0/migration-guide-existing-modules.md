# Migration Guide — Existing Modules → Canonical Frontend Authority

## Step 1: Remove Inline Style Blocks

**Before (FORBIDDEN):**
```blade
@push('head')
<style>
:root { --c-indigo: 99,102,241; }
.my-card { background: rgba(255,255,255,.84); border-radius: 1.5rem; box-shadow: 0 8px 32px rgba(15,23,42,.06); }
.btn-gradient { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; }
@keyframes myPulse { 0%,100%{ opacity:1 } 50%{ opacity:.3 } }
</style>
@endpush
```

**After (REQUIRED):**
```blade
@push('head')
<style>
/* Only component-local structural overrides — NO hardcoded values */
.my-card { background: var(--ui-glass-bg); border-radius: var(--ui-radius-3xl); box-shadow: var(--ui-shadow-card); }
.btn-gradient { background: linear-gradient(135deg, var(--ui-indigo), var(--ui-purple)); color: var(--ui-text-inverse); }
/* Use canonical keyframe: animation: ui-pulse-dot 2s ease-in-out infinite; */
</style>
@endpush
```

## Step 2: Inject Design Tokens

Add once at the top of `@section('content')`:
```blade
@section('content')
<x-core-ui::design-tokens />
{{-- rest of content --}}
```

## Step 3: Replace Local Buttons

**Before:**
```blade
<a href="{{ route('admin.items.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm font-semibold shadow-sm">
    افزودن جدید
</a>
```

**After:**
```blade
<x-core-ui::button href="{{ route('admin.items.create') }}" variant="gradient">
    افزودن جدید
</x-core-ui::button>
```

## Step 4: Replace Local Cards

**Before:**
```blade
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
```

**After:**
```blade
<x-core-ui::card>
```

## Step 5: Replace Local Modals

**Before:**
```blade
<div x-show="showDelete" class="fixed inset-0 z-[9998] bg-black/40" @keydown.escape.window="showDelete=false">
    <div class="...modal panel...">
        <button class="bg-red-600 text-white ...">Confirm</button>
    </div>
</div>
```

**After:**
```blade
<x-core-ui::confirm-modal
    title="حذف آیتم"
    message="آیا مطمئن هستید؟"
    variant="danger"
    @confirmed="deleteItem()"
/>
```

## Step 6: Replace Action Menus

**Before:**
```blade
<div x-data="{open:false}" class="relative">
    <button @click="open=!open">⋮</button>
    <div x-show="open" class="absolute ...">
        <a href="/edit">Edit</a>
        <button class="text-red-600" @click="del()">Delete</button>
    </div>
</div>
```

**After:**
```blade
<x-core-ui::action-menu>
    <x-core-ui::dropdown-item href="{{ route('admin.items.edit', $item) }}">ویرایش</x-core-ui::dropdown-item>
    <x-core-ui::dropdown-item variant="danger" @click.prevent="deleteItem({{ $item->id }})">حذف</x-core-ui::dropdown-item>
</x-core-ui::action-menu>
```

## Step 7: Replace Navigation Items

**Before:**
```blade
<a href="/admin/forms" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-white/5 hover:text-white {{ request()->is('admin/forms*') ? 'bg-indigo-500/15 text-white' : '' }}">
    Forms
</a>
```

**After:**
```blade
<x-core-ui::navigation-item href="/admin/forms" :active="request()->is('admin/forms*')">
    <x-slot name="icon"><!-- SVG --></x-slot>
    فرم‌ها
</x-core-ui::navigation-item>
```

## Step 8: Run Validation

```bash
php artisan frontend:validate-authority
# Score should be 90+ before marking retrofit complete
```

## Checklist

- [ ] `@push('head')` style block uses only `--ui-*` variables
- [ ] `<x-core-ui::design-tokens />` injected at top of content section
- [ ] All primary action buttons use `x-core-ui::button`
- [ ] All confirmation dialogs use `x-core-ui::confirm-modal`
- [ ] All table action menus use `x-core-ui::action-menu`
- [ ] All cards use `x-core-ui::card`
- [ ] No `@keyframes` defined locally (use `ui-*` canonical keyframes)
- [ ] No hardcoded hex colors in Blade views
- [ ] `php artisan frontend:validate-authority` passes
