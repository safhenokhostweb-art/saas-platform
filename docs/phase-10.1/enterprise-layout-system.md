# Enterprise Layout System — Phase 10.1

## CANONICAL LAYOUT TOKENS

```css
--ui-navbar-height:            3.5rem;
--ui-sidebar-width:            260px;
--ui-sidebar-collapsed-width:  72px;
--ui-sidebar-mobile-width:     280px;
--ui-content-max-width:        80rem;
--ui-content-max-width-narrow: 48rem;
--ui-page-padding-x:           var(--ui-space-6);
--ui-page-padding-y:           var(--ui-space-6);
--ui-toolbar-height:           3rem;
--ui-filter-bar-height:        3.5rem;
```

---

## CANONICAL LAYOUT HIERARCHY

```
layouts/admin.blade.php
├── x-core-ui::sidebar-group (sidebar navigation)
│   └── x-core-ui::navigation-item (per nav item)
├── main content area
│   ├── x-core-ui::content-container (max-width wrapper)
│   │   ├── x-core-ui::page-header (title + actions)
│   │   ├── x-core-ui::stats-strip (optional stats)
│   │   ├── x-core-ui::toolbar (search + filters)
│   │   ├── x-core-ui::filter-area (collapsible filters)
│   │   └── x-core-ui::data-table (main content)
│   └── x-core-ui::toast (notification system)
└── x-core-ui::modal (dialogs — portal rendered)
```

---

## STANDARD MODULE PAGE TEMPLATE

```blade
<x-core-ui::page-header
    title="عنوان صفحه"
    subtitle="توضیحات اختیاری"
>
    <x-slot name="actions">
        <x-core-ui::button href="{{ route('...create') }}">
            افزودن جدید
        </x-core-ui::button>
    </x-slot>
</x-core-ui::page-header>

<x-core-ui::stats-strip>
    <x-core-ui::stat-card value="{{ $total }}" label="مجموع" />
    <x-core-ui::stat-card value="{{ $active }}" label="فعال" variant="success" />
</x-core-ui::stats-strip>

<x-core-ui::toolbar>
    <x-slot name="search">
        <x-core-ui::input name="search" placeholder="جستجو..." />
    </x-slot>
    <x-slot name="actions">
        <x-core-ui::button variant="secondary">فیلترها</x-core-ui::button>
    </x-slot>
</x-core-ui::toolbar>

<x-core-ui::data-table
    :headers="[['label'=>'نام','key'=>'name','sortable'=>true], ...]"
    :loading="$loading"
    selectable
>
    @foreach($items as $item)
        <tr class="hover:bg-[var(--ui-table-row-hover-bg)]">
            <td>...</td>
        </tr>
    @endforeach
</x-core-ui::data-table>

{{-- Modals --}}
<x-core-ui::confirm-modal name="delete" @confirmed="deleteItem()" />
<x-core-ui::toast />
```

---

## SIDEBAR LAYOUT RULES

1. Sidebar width: `--ui-sidebar-width` (260px) — never `w-64`
2. Sidebar background: `--ui-sidebar-bg` — never `bg-gray-900`
3. Mobile: sidebar MUST be `x-show` toggled by Alpine with full overlay
4. Collapsed state: `--ui-sidebar-collapsed-width` (72px) — icons only
5. Active item: `--ui-sidebar-item-active` background
6. Nav items: MUST use `x-core-ui::navigation-item`

---

## SECTION SPACING RULES

| Element | Spacing Token |
|---------|---------------|
| Between page sections | `--ui-section-gap` (2rem) |
| Between cards | `--ui-card-gap` (1rem) |
| Inside cards | `--ui-space-6` (1.5rem) padding |
| Between form fields | `--ui-form-gap` (1.25rem) |
| Page horizontal padding | `--ui-page-padding-x` (1.5rem) |

---

## RESPONSIVE LAYOUT BREAKPOINTS

| Breakpoint | Value | Behavior |
|-----------|-------|----------|
| xs (mobile) | < 480px | Single column, full-width modals, collapsed sidebar |
| sm | 640px+ | 2-column grids, sidebar appears |
| md | 768px+ | 3-column grids, full table visibility |
| lg | 1024px+ | Full sidebar expanded, 4-column stats |
| xl | 1280px+ | Max content width reached |
