# Phase 1 — Shared UI Kit Extraction

> **Status**: ✅ Complete  
> **Date**: 2026-05-22  
> **Scope**: Frontend standardization, shared UI ownership, drift prevention, reusable component extraction  
> **Prerequisites**: Phase 0 (Architecture & Rule Audit), Phase 0.5 (Architecture Conflict Resolution)  
> **Next Phase**: Phase 2 — Shared Module Migration

---

## Table of Contents

1. [Task 1 — Shared UI Audit](#task-1--shared-ui-audit)
2. [Task 2 — UI Ownership Definition](#task-2--ui-ownership-definition)
3. [Task 3 — Shared UI Structure](#task-3--shared-ui-structure)
4. [Task 4 — Design Token Standardization](#task-4--design-token-standardization)
5. [Task 5 — Shared Animation System](#task-5--shared-animation-system)
6. [Task 6 — Shared Table/Filter System](#task-6--shared-tablefilter-system)
7. [Task 7 — Override-Safe UI System](#task-7--override-safe-ui-system)
8. [Task 8 — Shared UI Contracts](#task-8--shared-ui-contracts)
9. [Task 9 — Migration Priority Map](#task-9--migration-priority-map)
10. [Task 10 — Frontend Anti-Drift Enforcement](#task-10--frontend-anti-drift-enforcement)

---

## Task 1 — Shared UI Audit

### 1.1 Audit Methodology

Every frontend component in both projects was examined and categorized into one of four drift levels:

| Drift Level | Definition | Action Required |
|---|---|---|
| **Identical** | Same structure, same classes, same behavior | Extract as-is to shared package |
| **Near-Identical** | Same structure, minor class/style differences | Standardize, then extract |
| **Drifted** | Same purpose, different implementation | Reconcile, then extract |
| **Tenant-Specific** | Unique to one project's business logic | Leave in project, create shared contract |

### 1.2 Component Audit Matrix

#### 1.2.1 Cards

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Stat/KPI Cards** | `bg-white rounded-xl shadow-sm p-4` inline classes | `.admin-kpi-card` with glass morphism, `.ticket-stat-card` with backdrop-filter | **Drifted** |
| **Module Cards** | Simple `bg-white rounded-lg shadow-sm p-6` | `.admin-pro-module-card` with gradient borders, glass effects | **Drifted** |
| **Form Cards** | `bg-white rounded-xl shadow-sm p-6` | `.admin-card` with `.admin-card__body` pattern | **Drifted** |
| **Empty State** | Inline patterns (varied) | `.admin-empty-state` with SVG + title + text | **Drifted** |

#### 1.2.2 Tables

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Table Wrapper** | `bg-white rounded shadow overflow-hidden` | `.admin-table-wrap` with `.card` class | **Near-Identical** |
| **Table Header** | `bg-gray-50 border-b` | `.admin-table thead th` with custom CSS | **Near-Identical** |
| **Table Row** | `hover:bg-gray-50 border-b` | `.admin-table tbody tr` with hover effect | **Near-Identical** |
| **Bulk Actions Bar** | `bg-gray-50 px-4 py-3 flex items-center gap-3` | Inline in each view | **Drifted** |
| **Pagination** | `mt-4 flex justify-center` | `.admin-pagination` with custom styling | **Drifted** |

#### 1.2.3 Filters

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Filter Bar** | Simple form with `grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4` | `.admin-filter-bar`, `.glass-filter-bar`, `.admin-sticky-filters` | **Drifted** |
| **Search Input** | `px-4 py-3 rounded-xl border-gray-300` | Same pattern, wrapped in filter bar | **Near-Identical** |
| **Select Filters** | `rounded-xl border-gray-300` | Same pattern | **Identical** |
| **Date Range** | Two date inputs side by side | Same pattern | **Identical** |
| **Reset Button** | `bg-gray-100 text-gray-700 hover:bg-gray-200` | Same pattern | **Identical** |

#### 1.2.4 Badges

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Status Badge** | Inline classes: `bg-green-100 text-green-800 px-2 py-0.5 rounded-full text-xs` | `.badge-published`, `.badge-draft`, `.badge-scheduled`, `.badge-pending`, `.badge-approved`, `.badge-rejected` | **Drifted** |
| **Priority Badge** | Inline classes | `.priority-dot` with pulse animation | **Drifted** |
| **Type Badge** | `bg-blue-100 text-blue-700`, `bg-green-100 text-green-700`, `bg-gray-100 text-gray-700` | `.badge-success`, `.badge-danger`, `.badge-warning` | **Drifted** |
| **Role Badge** | `bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full` | Inline classes | **Near-Identical** |

#### 1.2.5 Buttons

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Primary Button** | `bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-3 rounded-xl` | `.btn-gradient` with same gradient | **Identical** |
| **Secondary Button** | `bg-white border border-gray-300 text-gray-700 px-4 py-3 rounded-xl` | Same pattern | **Identical** |
| **Danger Button** | `bg-red-600 text-white px-4 py-3 rounded-xl` | Same pattern | **Identical** |
| **Icon Button** | `p-2 text-gray-400 hover:text-gray-600` | Same pattern | **Identical** |
| **Action Group** | Inline flex container | `.admin-table-actions` | **Near-Identical** |

#### 1.2.6 Forms

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Text Input** | `px-4 py-3 rounded-xl border-gray-300 focus:ring-indigo-500` | Same pattern | **Identical** |
| **Select** | `rounded-xl border-gray-300` | Same pattern | **Identical** |
| **Textarea** | `px-4 py-3 rounded-xl border-gray-300` | Same pattern | **Identical** |
| **Checkbox** | `rounded border-gray-300 text-indigo-600` | Same pattern | **Identical** |
| **Form Group** | `mb-4` or `mb-6` spacing | Same pattern | **Identical** |
| **Label** | `block text-sm font-medium text-gray-700 mb-1` | Same pattern | **Identical** |
| **Error Message** | `text-red-500 text-sm mt-1` | Same pattern | **Identical** |

#### 1.2.7 Alerts & Flash Messages

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Success Flash** | `alert alert-success` with SVG icon | `.admin-flash.admin-flash--success` with SVG icon | **Drifted** |
| **Error Flash** | `alert alert-danger` with SVG icon | `.admin-flash.admin-flash--error` with SVG icon | **Drifted** |
| **Warning Flash** | `alert alert-warning` (inline) | `.admin-flash.admin-flash--warning` with SVG icon | **Drifted** |
| **Info Flash** | `alert alert-info` (inline) | `.admin-flash` default | **Drifted** |
| **Validation Errors** | `bg-red-100 border border-red-300 text-red-800 rounded-lg p-4` | `.admin-errors` with list | **Drifted** |

#### 1.2.8 Dropdowns & Modals

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Row Actions Dropdown** | Alpine.js x-show with transitions, `origin-top-right absolute right-0 mt-2 w-48` | Same pattern | **Identical** |
| **Confirmation Modal** | Alpine.js x-show with backdrop blur, icon + text + confirm/cancel | Same pattern | **Identical** |
| **Bulk Action Modal** | Alpine.js x-show with backdrop blur | Same pattern | **Identical** |

#### 1.2.9 Layout Components

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Sidebar** | Glass effect with `backdrop-filter blur(24px)`, collapsible (w-64 → w-5rem) | Glass effect with `backdrop-filter`, collapsible (localStorage key: customer-admin-sidebar-collapsed) | **Near-Identical** |
| **Sidebar Icons** | `w-4 h-4 ml-2 shrink-0` (20+ icons) | `w-5 h-5 ml-3 shrink-0` (50+ icons) | **Drifted** |
| **Sidebar Groups** | Accordion toggle with localStorage persistence | Accordion toggle with localStorage persistence | **Identical** |
| **Top Bar** | Sidebar toggle + mobile hamburger | Sidebar toggle + mobile hamburger | **Identical** |
| **Page Header** | `@section('header')` | `.admin-page-head` with `.admin-page-title` + `.admin-page-tools` | **Drifted** |
| **Breadcrumbs** | Not used consistently | `.admin-breadcrumb` pattern | **Drifted** |
| **Guide Widget** | `@include('admin.partials.guide')` | `@include('admin.partials.guide')` | **Identical** |

#### 1.2.10 Loaders & Animations

| Component | saas-platform | customer-site | Drift Level |
|---|---|---|---|
| **Loading Spinner** | Inline SVG with `animate-spin` | Same pattern | **Identical** |
| **Pulse Indicator** | Not used | `.priority-dot` with `@keyframes pulse` | **Tenant-Specific** |
| **Skeleton Loader** | Not used | Not used | **N/A** |
| **Transition Effects** | `transition-all duration-200` | Same pattern | **Identical** |
| **Hover Effects** | `hover:bg-gray-50`, `hover:text-indigo-600` | Same pattern | **Identical** |

### 1.3 Audit Summary Statistics

| Category | Identical | Near-Identical | Drifted | Tenant-Specific | Total |
|---|---|---|---|---|---|
| Cards | 0 | 0 | 4 | 0 | 4 |
| Tables | 0 | 4 | 2 | 0 | 6 |
| Filters | 3 | 2 | 1 | 0 | 6 |
| Badges | 0 | 1 | 3 | 0 | 4 |
| Buttons | 4 | 1 | 0 | 0 | 5 |
| Forms | 7 | 0 | 0 | 0 | 7 |
| Alerts & Flash | 0 | 0 | 4 | 0 | 4 |
| Dropdowns & Modals | 3 | 0 | 0 | 0 | 3 |
| Layout | 3 | 2 | 3 | 0 | 8 |
| Loaders & Animations | 3 | 0 | 0 | 1 | 4 |
| **Total** | **23** | **10** | **17** | **1** | **51** |

**Key Insight**: 23 components (45%) are identical and ready for immediate extraction. 10 components (20%) are near-identical and need minor standardization. 17 components (33%) have drifted significantly and require reconciliation. Only 1 component (2%) is truly tenant-specific.

---

## Task 2 — UI Ownership Definition

### 2.1 Ownership Principles

1. **Canonical Source**: Every UI component has exactly one canonical source of truth
2. **Tenant Override**: Tenant-specific visual customization is allowed ONLY through CSS variables and Tailwind config overrides
3. **No Duplication**: Never copy a Blade file from one project to another
4. **Contract First**: Every shared component has a defined contract (props, slots, variants)
5. **Backward Compatibility**: Shared components must maintain backward compatibility for 2 minor versions

### 2.2 Ownership Matrix

| Component | Owner | Location | Override Mechanism |
|---|---|---|---|
| **Stat/KPI Cards** | Shared UI Kit | `ui-kit/src/resources/views/cards/` | CSS variables for colors, border-radius |
| **Module Cards** | Shared UI Kit | `ui-kit/src/resources/views/cards/` | Slot-based content injection |
| **Empty State** | Shared UI Kit | `ui-kit/src/resources/views/` | Props for icon, title, description, action |
| **Table Wrapper** | Shared UI Kit | `ui-kit/src/resources/views/tables/` | CSS variables for spacing, borders |
| **Table Header** | Shared UI Kit | `ui-kit/src/resources/views/tables/` | Column configuration array |
| **Table Row** | Shared UI Kit | `ui-kit/src/resources/views/tables/` | Row data + action slots |
| **Bulk Actions Bar** | Shared UI Kit | `ui-kit/src/resources/views/tables/` | Action configuration array |
| **Pagination** | Shared UI Kit | `ui-kit/src/resources/views/tables/` | Tailwind config overrides |
| **Filter Bar** | Shared UI Kit | `ui-kit/src/resources/views/filters/` | Filter configuration array |
| **Search Input** | Shared UI Kit | `ui-kit/src/resources/views/filters/` | Props for placeholder, value, wire:model |
| **Select Filter** | Shared UI Kit | `ui-kit/src/resources/views/filters/` | Options array + value binding |
| **Date Range** | Shared UI Kit | `ui-kit/src/resources/views/filters/` | Start/end value binding |
| **Status Badge** | Shared UI Kit | `ui-kit/src/resources/views/badges/` | Variant prop (published, draft, pending, etc.) |
| **Priority Badge** | Shared UI Kit | `ui-kit/src/resources/views/badges/` | Variant prop (low, medium, high, critical) |
| **Type Badge** | Shared UI Kit | `ui-kit/src/resources/views/badges/` | Variant prop + label |
| **Primary Button** | Shared UI Kit | `ui-kit/src/resources/views/buttons/` | Props for size, disabled, loading |
| **Secondary Button** | Shared UI Kit | `ui-kit/src/resources/views/buttons/` | Props for size, disabled, loading |
| **Danger Button** | Shared UI Kit | `ui-kit/src/resources/views/buttons/` | Props for size, disabled, loading |
| **Icon Button** | Shared UI Kit | `ui-kit/src/resources/views/buttons/` | Icon name + size props |
| **Action Group** | Shared UI Kit | `ui-kit/src/resources/views/buttons/` | Action configuration array |
| **Text Input** | Shared UI Kit | `ui-kit/src/resources/views/forms/` | Props for type, placeholder, value, error |
| **Select** | Shared UI Kit | `ui-kit/src/resources/views/forms/` | Options array + value |
| **Textarea** | Shared UI Kit | `ui-kit/src/resources/views/forms/` | Props for rows, placeholder, value |
| **Checkbox** | Shared UI Kit | `ui-kit/src/resources/views/forms/` | Props for checked, label, value |
| **Form Group** | Shared UI Kit | `ui-kit/src/resources/views/forms/` | Label + input slot + error slot |
| **Flash Messages** | Shared UI Kit | `ui-kit/src/resources/views/` | Type prop (success, error, warning, info) |
| **Validation Errors** | Shared UI Kit | `ui-kit/src/resources/views/` | Errors array prop |
| **Confirmation Modal** | Shared UI Kit | `ui-kit/src/resources/views/modals/` | Props for title, message, confirm/cancel text |
| **Sidebar** | Shared UI Kit | `ui-kit/src/resources/views/layouts/` | Menu items array + active state |
| **Sidebar Icons** | Shared UI Kit | `ui-kit/src/resources/views/icons/` | Icon name prop |
| **Page Header** | Shared UI Kit | `ui-kit/src/resources/views/layouts/` | Title + breadcrumbs + action slots |
| **Breadcrumbs** | Shared UI Kit | `ui-kit/src/resources/views/layouts/` | Items array with label + url |
| **Loading Spinner** | Shared UI Kit | `ui-kit/src/resources/views/` | Size prop |
| **Pulse Indicator** | Shared UI Kit | `ui-kit/src/resources/views/indicators/` | Color + size props |

### 2.3 Tenant-Specific Components (NOT Shared)

These components remain in their respective projects because they contain business logic unique to that project:

| Component | Project | Reason |
|---|---|---|
| **Module Features Grid** | saas-platform | Platform-specific module catalog display |
| **License Status Section** | customer-site | Customer-specific license management UI |
| **SLA Breach Card** | customer-site | Customer-specific support SLA tracking |
| **Notification Preferences** | customer-site | Customer-specific notification settings |
| **Theme Builder** | saas-platform | Platform-specific theme customization |

---

## Task 3 — Shared UI Structure

### 3.1 Directory Architecture

```
packages/saas-platform/ui-kit/
├── composer.json
├── README.md
├── src/
│   ├── UiKitServiceProvider.php
│   ├── Components/
│   │   ├── Card/
│   │   │   ├── StatCard.php
│   │   │   ├── ModuleCard.php
│   │   │   └── EmptyState.php
│   │   ├── Table/
│   │   │   ├── Table.php
│   │   │   ├── TableColumn.php
│   │   │   ├── BulkActions.php
│   │   │   └── Pagination.php
│   │   ├── Filter/
│   │   │   ├── FilterBar.php
│   │   │   ├── SearchInput.php
│   │   │   ├── SelectFilter.php
│   │   │   └── DateRangeFilter.php
│   │   ├── Badge/
│   │   │   ├── StatusBadge.php
│   │   │   ├── PriorityBadge.php
│   │   │   └── TypeBadge.php
│   │   ├── Button/
│   │   │   ├── PrimaryButton.php
│   │   │   ├── SecondaryButton.php
│   │   │   ├── DangerButton.php
│   │   │   ├── IconButton.php
│   │   │   └── ActionGroup.php
│   │   ├── Form/
│   │   │   ├── TextInput.php
│   │   │   ├── SelectInput.php
│   │   │   ├── TextareaInput.php
│   │   │   ├── CheckboxInput.php
│   │   │   └── FormGroup.php
│   │   ├── Layout/
│   │   │   ├── Sidebar.php
│   │   │   ├── SidebarGroup.php
│   │   │   ├── PageHeader.php
│   │   │   └── Breadcrumbs.php
│   │   ├── Modal/
│   │   │   └── ConfirmModal.php
│   │   ├── Alert/
│   │   │   ├── FlashMessage.php
│   │   │   └── ValidationErrors.php
│   │   └── Indicator/
│   │       ├── LoadingSpinner.php
│   │       └── PulseIndicator.php
│   ├── Contracts/
│   │   ├── ComponentContract.php
│   │   ├── HasVariants.php
│   │   └── HasSlots.php
│   ├── Traits/
│   │   ├── HasSize.php
│   │   ├── HasVariant.php
│   │   └── HasTheme.php
│   └── resources/
│       └── views/
│           ├── components/
│           │   ├── card/
│           │   │   ├── stat-card.blade.php
│           │   │   ├── module-card.blade.php
│           │   │   └── empty-state.blade.php
│           │   ├── table/
│           │   │   ├── table.blade.php
│           │   │   ├── bulk-actions.blade.php
│           │   │   └── pagination.blade.php
│           │   ├── filter/
│           │   │   ├── filter-bar.blade.php
│           │   │   ├── search-input.blade.php
│           │   │   ├── select-filter.blade.php
│           │   │   └── date-range.blade.php
│           │   ├── badge/
│           │   │   ├── status-badge.blade.php
│           │   │   ├── priority-badge.blade.php
│           │   │   └── type-badge.blade.php
│           │   ├── button/
│           │   │   ├── primary-button.blade.php
│           │   │   ├── secondary-button.blade.php
│           │   │   ├── danger-button.blade.php
│           │   │   ├── icon-button.blade.php
│           │   │   └── action-group.blade.php
│           │   ├── form/
│           │   │   ├── text-input.blade.php
│           │   │   ├── select-input.blade.php
│           │   │   ├── textarea-input.blade.php
│           │   │   ├── checkbox-input.blade.php
│           │   │   └── form-group.blade.php
│           │   ├── layout/
│           │   │   ├── sidebar.blade.php
│           │   │   ├── sidebar-group.blade.php
│           │   │   ├── page-header.blade.php
│           │   │   └── breadcrumbs.blade.php
│           │   ├── modal/
│           │   │   └── confirm-modal.blade.php
│           │   ├── alert/
│           │   │   ├── flash-message.blade.php
│           │   │   └── validation-errors.blade.php
│           │   └── indicator/
│           │       ├── loading-spinner.blade.php
│           │       └── pulse-indicator.blade.php
│           └── layouts/
│               └── app.blade.php
├── dist/
│   └── css/
│       └── ui-kit.css
└── tailwind.config.js
```

### 3.2 Namespace & Registration

```php
// composer.json
{
    "name": "saas-platform/ui-kit",
    "description": "Shared UI Kit for SaaS Platform ecosystem",
    "type": "library",
    "autoload": {
        "psr-4": {
            "SaasPlatform\\UiKit\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "SaasPlatform\\UiKit\\UiKitServiceProvider"
            ]
        }
    }
}
```

```php
// UiKitServiceProvider.php
namespace SaasPlatform\UiKit;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class UiKitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load views with namespace
        $this->loadViewsFrom(__DIR__.'/resources/views', 'ui-kit');

        // Register Blade components
        Blade::component('ui-kit::components.card.stat-card', 'ui-stat-card');
        Blade::component('ui-kit::components.card.empty-state', 'ui-empty-state');
        Blade::component('ui-kit::components.table.table', 'ui-table');
        Blade::component('ui-kit::components.table.bulk-actions', 'ui-bulk-actions');
        Blade::component('ui-kit::components.table.pagination', 'ui-pagination');
        Blade::component('ui-kit::components.filter.filter-bar', 'ui-filter-bar');
        Blade::component('ui-kit::components.filter.search-input', 'ui-search-input');
        Blade::component('ui-kit::components.filter.select-filter', 'ui-select-filter');
        Blade::component('ui-kit::components.filter.date-range', 'ui-date-range');
        Blade::component('ui-kit::components.badge.status-badge', 'ui-status-badge');
        Blade::component('ui-kit::components.badge.priority-badge', 'ui-priority-badge');
        Blade::component('ui-kit::components.badge.type-badge', 'ui-type-badge');
        Blade::component('ui-kit::components.button.primary-button', 'ui-button');
        Blade::component('ui-kit::components.button.secondary-button', 'ui-secondary-button');
        Blade::component('ui-kit::components.button.danger-button', 'ui-danger-button');
        Blade::component('ui-kit::components.button.icon-button', 'ui-icon-button');
        Blade::component('ui-kit::components.button.action-group', 'ui-action-group');
        Blade::component('ui-kit::components.form.text-input', 'ui-input');
        Blade::component('ui-kit::components.form.select-input', 'ui-select');
        Blade::component('ui-kit::components.form.textarea-input', 'ui-textarea');
        Blade::component('ui-kit::components.form.checkbox-input', 'ui-checkbox');
        Blade::component('ui-kit::components.form.form-group', 'ui-form-group');
        Blade::component('ui-kit::components.layout.sidebar', 'ui-sidebar');
        Blade::component('ui-kit::components.layout.sidebar-group', 'ui-sidebar-group');
        Blade::component('ui-kit::components.layout.page-header', 'ui-page-header');
        Blade::component('ui-kit::components.layout.breadcrumbs', 'ui-breadcrumbs');
        Blade::component('ui-kit::components.modal.confirm-modal', 'ui-confirm-modal');
        Blade::component('ui-kit::components.alert.flash-message', 'ui-flash');
        Blade::component('ui-kit::components.alert.validation-errors', 'ui-validation-errors');
        Blade::component('ui-kit::components.indicator.loading-spinner', 'ui-spinner');
        Blade::component('ui-kit::components.indicator.pulse-indicator', 'ui-pulse');

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/ui-kit.php' => config_path('ui-kit.php'),
        ], 'ui-kit-config');

        // Publish assets
        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/ui-kit'),
        ], 'ui-kit-assets');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ui-kit.php', 'ui-kit');
    }
}
```

### 3.3 Integration Path

**Phase 3.1 — Package Creation** (Current Phase)
- Create the package directory structure
- Implement all Blade components
- Register all components in ServiceProvider
- Create configuration file

**Phase 3.2 — saas-platform Adoption**
- Add `saas-platform/ui-kit` to saas-platform's composer.json
- Replace inline components with `x-ui-*` components
- Remove duplicated Blade files

**Phase 3.3 — customer-site Adoption**
- Add `saas-platform/ui-kit` to customer-site's composer.json
- Replace inline components with `x-ui-*` components
- Remove duplicated Blade files

---

## Task 4 — Design Token Standardization

### 4.1 Design Token Categories

All design tokens are defined in a single source of truth: `config/ui-kit.php` and `tailwind.config.js` in the shared package.

#### 4.1.1 Spacing Scale

| Token | Value | Usage |
|---|---|---|
| `spacing-xs` | `0.25rem` (4px) | Icon gaps, inline padding |
| `spacing-sm` | `0.5rem` (8px) | Small gaps, badge padding |
| `spacing-md` | `0.75rem` (12px) | Button padding, small cards |
| `spacing-lg` | `1rem` (16px) | Default padding, card padding |
| `spacing-xl` | `1.5rem` (24px) | Section gaps, modal padding |
| `spacing-2xl` | `2rem` (32px) | Large section gaps |
| `spacing-3xl` | `3rem` (48px) | Page section gaps |

**Standardized Values**:
- Field padding: `px-4 py-3` (spacing-lg horizontal, spacing-md vertical)
- Card padding: `p-6` (spacing-xl)
- Section gap: `mb-8` (spacing-2xl)
- Grid gap: `gap-4` or `gap-6`
- Filter gap: `gap-4`

#### 4.1.2 Border Radius

| Token | Value | Usage |
|---|---|---|
| `radius-sm` | `0.5rem` (8px) | Small badges, small indicators |
| `radius-md` | `0.75rem` (12px) | Buttons, inputs, selects |
| `radius-lg` | `1rem` (16px) | Cards, modals, dropdowns |
| `radius-xl` | `1.25rem` (20px) | Large cards, containers |
| `radius-full` | `9999px` | Pills, avatars, badges |

**Standardized Values**:
- Fields: `rounded-xl` (radius-md)
- Cards: `rounded-2xl` (radius-lg)
- Buttons: `rounded-xl` (radius-md)
- Badges: `rounded-full` (radius-full)
- Modals: `rounded-2xl` (radius-lg)

#### 4.1.3 Shadows

| Token | Value | Usage |
|---|---|---|
| `shadow-sm` | `0 1px 2px 0 rgb(0 0 0 / 0.05)` | Cards, containers |
| `shadow-md` | `0 4px 6px -1px rgb(0 0 0 / 0.1)` | Dropdowns, popovers |
| `shadow-lg` | `0 10px 15px -3px rgb(0 0 0 / 0.1)` | Modals |
| `shadow-xl` | `0 20px 25px -5px rgb(0 0 0 / 0.1)` | Large modals, drawers |

**Standardized Values**:
- Cards: `shadow-sm`
- Dropdowns: `shadow-md`
- Modals: `shadow-xl`

#### 4.1.4 Colors

| Token | Tailwind Class | Hex (Default) | Usage |
|---|---|---|---|
| `primary` | `indigo-600` | `#4f46e5` | Primary actions, links |
| `primary-gradient-from` | `indigo-600` | `#4f46e5` | Gradient start |
| `primary-gradient-to` | `purple-600` | `#9333ea` | Gradient end |
| `secondary` | `purple-600` | `#9333ea` | Secondary actions |
| `success` | `emerald-600` | `#059669` | Success states |
| `danger` | `red-600` | `#dc2626` | Error/danger states |
| `warning` | `amber-500` | `#f59e0b` | Warning states |
| `info` | `blue-500` | `#3b82f6` | Info states |
| `surface` | `white` | `#ffffff` | Card backgrounds |
| `background` | `gray-50` | `#f9fafb` | Page backgrounds |
| `text-primary` | `gray-900` | `#111827` | Primary text |
| `text-secondary` | `gray-700` | `#374151` | Secondary text |
| `text-muted` | `gray-500` | `#6b7280` | Muted text |
| `border` | `gray-300` | `#d1d5db` | Borders |
| `border-light` | `gray-200` | `#e5e7eb` | Light borders |

#### 4.1.5 Typography

| Token | Class | Usage |
|---|---|---|
| `font-sans` | `font-sans` | Default font family |
| `text-xs` | `text-xs` | Badges, small labels |
| `text-sm` | `text-sm` | Body text, table cells |
| `text-base` | `text-base` | Default text |
| `text-lg` | `text-lg` | Section titles |
| `text-xl` | `text-xl` | Page titles |
| `text-2xl` | `text-2xl` | Large headings |
| `font-normal` | `font-normal` | Body text |
| `font-medium` | `font-medium` | Labels, buttons |
| `font-semibold` | `font-semibold` | Section titles |
| `font-bold` | `font-bold` | Page titles |

**Standardized Values**:
- Page title: `text-2xl font-bold text-gray-900`
- Section title: `text-lg font-semibold text-gray-900`
- Card title: `text-base font-semibold text-gray-900`
- Table header: `text-xs font-medium text-gray-500 uppercase tracking-wider`
- Table cell: `text-sm text-gray-900`
- Label: `text-sm font-medium text-gray-700`
- Badge text: `text-xs font-medium`
- Button text: `text-sm font-medium`

#### 4.1.6 Icon Sizing

| Token | Size Class | Usage |
|---|---|---|
| `icon-sm` | `w-4 h-4` | Inside small buttons, inline with text |
| `icon-md` | `w-5 h-5` | **Default** — 90% of use cases |
| `icon-lg` | `w-6 h-6` | Section headers, stat card icons |
| `icon-xl` | `w-8 h-8` | Avatars, profile images |

**Standardized Values**:
- Sidebar icons: `w-5 h-5 ml-3 shrink-0` (standardized from saas-platform's `w-4 h-4 ml-2`)
- Action icons: `w-5 h-5 flex-shrink-0`
- Stat card icons: `w-6 h-6`
- Button icons: `w-5 h-5`
- Alert icons: `w-5 h-5`

#### 4.1.7 Transitions & Animations

| Token | Value | Usage |
|---|---|---|
| `transition-fast` | `duration-150` | Hover effects |
| `transition-normal` | `duration-200` | Default transitions |
| `transition-slow` | `duration-300` | Modal open
| `transition-slow` | `duration-300` | Modal open, sidebar collapse |
| `transition-ease` | `ease-in-out` | Default easing |
| `transition-ease-in` | `ease-in` | Enter animations |
| `transition-ease-out` | `ease-out` | Exit animations |

**Standardized Values**:
- Hover effects: `transition-all duration-150 ease-in-out`
- Default transitions: `transition-all duration-200 ease-in-out`
- Modal open/close: `transition-all duration-300 ease-in-out`
- Sidebar collapse: `transition-all duration-300 ease-in-out`
- Dropdown open: `transition-all duration-200 ease-out`
- Dropdown close: `transition-all duration-150 ease-in`

### 4.2 Configuration File

```php
<?php
// config/ui-kit.php

return [
    /*
    |--------------------------------------------------------------------------
    | Design Tokens
    |--------------------------------------------------------------------------
    |
    | Central source of truth for all UI design tokens.
    | Both projects MUST reference this config rather than hardcoding values.
    |
    */
    'spacing' => [
        'field-padding' => 'px-4 py-3',
        'card-padding' => 'p-6',
        'section-gap' => 'mb-8',
        'grid-gap' => 'gap-4',
        'filter-gap' => 'gap-4',
    ],

    'radius' => [
        'field' => 'rounded-xl',
        'card' => 'rounded-2xl',
        'button' => 'rounded-xl',
        'badge' => 'rounded-full',
        'modal' => 'rounded-2xl',
    ],

    'shadow' => [
        'card' => 'shadow-sm',
        'dropdown' => 'shadow-md',
        'modal' => 'shadow-xl',
    ],

    'colors' => [
        'primary' => 'indigo-600',
        'primary-gradient-from' => 'indigo-600',
        'primary-gradient-to' => 'purple-600',
        'secondary' => 'purple-600',
        'success' => 'emerald-600',
        'danger' => 'red-600',
        'warning' => 'amber-500',
        'info' => 'blue-500',
    ],

    'typography' => [
        'page-title' => 'text-2xl font-bold text-gray-900',
        'section-title' => 'text-lg font-semibold text-gray-900',
        'card-title' => 'text-base font-semibold text-gray-900',
        'table-header' => 'text-xs font-medium text-gray-500 uppercase tracking-wider',
        'table-cell' => 'text-sm text-gray-900',
        'label' => 'text-sm font-medium text-gray-700',
        'badge-text' => 'text-xs font-medium',
        'button-text' => 'text-sm font-medium',
    ],

    'icons' => [
        'default' => 'w-5 h-5',
        'sidebar' => 'w-5 h-5 ml-3 shrink-0',
        'action' => 'w-5 h-5 flex-shrink-0',
        'stat-card' => 'w-6 h-6',
        'button' => 'w-5 h-5',
        'alert' => 'w-5 h-5',
        'small' => 'w-4 h-4',
        'large' => 'w-6 h-6',
        'avatar' => 'w-8 h-8',
    ],

    'transitions' => [
        'hover' => 'transition-all duration-150 ease-in-out',
        'default' => 'transition-all duration-200 ease-in-out',
        'modal' => 'transition-all duration-300 ease-in-out',
        'sidebar' => 'transition-all duration-300 ease-in-out',
        'dropdown-open' => 'transition-all duration-200 ease-out',
        'dropdown-close' => 'transition-all duration-150 ease-in',
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Defaults
    |--------------------------------------------------------------------------
    |
    | Default props for shared components.
    | Projects can override these in their own config.
    |
    */
    'components' => [
        'button' => [
            'default_size' => 'md',
            'sizes' => [
                'sm' => 'px-3 py-2 text-sm',
                'md' => 'px-4 py-3 text-sm',
                'lg' => 'px-6 py-4 text-base',
            ],
        ],
        'badge' => [
            'default_size' => 'sm',
            'sizes' => [
                'sm' => 'px-2 py-0.5 text-xs',
                'md' => 'px-3 py-1 text-sm',
            ],
        ],
        'input' => [
            'default_size' => 'md',
            'sizes' => [
                'sm' => 'px-3 py-2 text-sm',
                'md' => 'px-4 py-3 text-sm',
                'lg' => 'px-5 py-4 text-base',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Override Configuration
    |--------------------------------------------------------------------------
    |
    | Tenant-specific overrides for the shared UI Kit.
    | Each project can define its own overrides in config/ui-kit-override.php.
    |
    */
    'overrides' => [
        'enabled' => env('UI_KIT_OVERRIDES_ENABLED', true),
        'config_file' => 'ui-kit-override', // config file name without .php
    ],
];
```

### 4.3 Tailwind Config Extension

```js
// tailwind.config.js (in packages/saas-platform/ui-kit/)
const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
    theme: {
        extend: {
            borderRadius: {
                'xl': '0.75rem',    // fields, buttons
                '2xl': '1rem',      // cards, modals
            },
            spacing: {
                '18': '4.5rem',
                '88': '22rem',
                '128': '32rem',
            },
            colors: {
                primary: {
                    DEFAULT: '#4f46e5', // indigo-600
                    from: '#4f46e5',
                    to: '#9333ea',
                },
            },
            animation: {
                'ui-pulse': 'ui-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                'ui-shimmer': 'ui-shimmer 2s linear infinite',
                'ui-fade-in': 'ui-fade-in 0.2s ease-out',
                'ui-slide-up': 'ui-slide-up 0.3s ease-out',
                'ui-slide-down': 'ui-slide-down 0.2s ease-in',
            },
            keyframes: {
                'ui-pulse': {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0.5' },
                },
                'ui-shimmer': {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                'ui-fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'ui-slide-up': {
                    '0%': { transform: 'translateY(10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                'ui-slide-down': {
                    '0%': { transform: 'translateY(0)', opacity: '1' },
                    '100%': { transform: 'translateY(10px)', opacity: '0' },
                },
            },
        },
    },
    plugins: [],
};
```

---

## Task 5 — Shared Animation System

### 5.1 Animation Inventory

The following animations are used across both projects. All are standardized into the shared UI Kit.

| Animation | CSS Definition | Usage |
|---|---|---|
| **ui-pulse** | `@keyframes ui-pulse { 0%,100% { opacity:1 } 50% { opacity:0.5 } }` | Status indicators, priority dots |
| **ui-shimmer** | `@keyframes ui-shimmer { 0% { background-position:-200% 0 } 100% { background-position:200% 0 } }` | Loading skeletons |
| **ui-fade-in** | `@keyframes ui-fade-in { 0% { opacity:0 } 100% { opacity:1 } }` | Modal backdrops, dropdowns |
| **ui-slide-up** | `@keyframes ui-slide-up { 0% { transform:translateY(10px); opacity:0 } 100% { transform:translateY(0); opacity:1 } }` | Modal content, notifications |
| **ui-slide-down** | `@keyframes ui-slide-down { 0% { transform:translateY(0); opacity:1 } 100% { transform:translateY(10px); opacity:0 } }` | Modal close, notification dismiss |
| **spin** | Tailwind built-in `animate-spin` | Loading spinners |
| **ping** | Tailwind built-in `animate-ping` | Notification dots |
| **bounce** | Tailwind built-in `animate-bounce` | Attention indicators |
| **hover-lift** | `transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1)` | Card hover effects |
| **status-dot** | `width: 8px; height: 8px; border-radius: 50%; display: inline-block` | Status indicators |

### 5.2 Centralized Animation CSS

```css
/* dist/css/ui-kit.css */

/* === Shared Animations === */

@keyframes ui-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes ui-shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

@keyframes ui-fade-in {
    0% { opacity: 0; }
    100% { opacity: 1; }
}

@keyframes ui-slide-up {
    0% { transform: translateY(10px); opacity: 0; }
    100% { transform: translateY(0); opacity: 1; }
}

@keyframes ui-slide-down {
    0% { transform: translateY(0); opacity: 1; }
    100% { transform: translateY(10px); opacity: 0; }
}

/* === Utility Classes === */

.ui-pulse {
    animation: ui-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.ui-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.ui-card-hover {
    transition: all 0.15s ease-in-out;
}
.ui-card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

.ui-row-hover {
    transition: background-color 0.15s ease-in-out;
}
.ui-row-hover:hover {
    background-color: rgb(249 250 251);
}

.ui-shimmer {
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: ui-shimmer 2s linear infinite;
}
```

### 5.3 Pulse Indicator Component

```blade
{{-- resources/views/components/indicator/pulse-indicator.blade.php --}}
@props([
    'color' => 'emerald',
    'size' => 'md',
    'pulse' => true,
])

@php
$colors = [
    'emerald' => 'bg-emerald-500',
    'red' => 'bg-red-500',
    'amber' => 'bg-amber-500',
    'blue' => 'bg-blue-500',
    'gray' => 'bg-gray-400',
    'purple' => 'bg-purple-500',
];

$sizes = [
    'sm' => 'w-2 h-2',
    'md' => 'w-2.5 h-2.5',
    'lg' => 'w-3 h-3',
];
@endphp

<span class="ui-status-dot {{ $colors[$color] ?? $colors['emerald'] }} {{ $sizes[$size] ?? $sizes['md'] }} {{ $pulse ? 'ui-pulse' : '' }}"
      {{ $attributes }}>
</span>
```

### 5.4 Usage Guidelines

1. **Prefer Tailwind built-in animations** (`animate-spin`, `animate-ping`, `animate-bounce`) over custom CSS when available
2. **Use `ui-pulse`** for status indicators that need attention (priority dots, active status)
3. **Use `ui-shimmer`** for loading skeleton screens
4. **Use `ui-fade-in` + `ui-slide-up`** together for modal/dropdown entrance
5. **Use `ui-card-hover`** for interactive cards
6. **Use `ui-row-hover`** for table rows
7. **Never** create duplicate animation definitions — always reference the shared CSS

---

## Task 6 — Shared Table/Filter System

### 6.1 Shared Table Component

```blade
{{-- resources/views/components/table/table.blade.php --}}
@props([
    'columns' => [],
    'rows' => [],
    'selectable' => false,
    'sortable' => false,
    'sortField' => null,
    'sortDirection' => 'asc',
    'bulkActions' => [],
    'emptyMessage' => 'هیچ داده‌ای یافت نشد',
    'emptyIcon' => true,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl shadow-sm overflow-hidden']) }}>
    @if(count($rows) > 0)
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        @if($selectable)
                            <th class="px-4 py-3 text-right">
                                <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                        @endif
                        @foreach($columns as $column)
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                @if($sortable && isset($column['sortable']) && $column['sortable'])
                                    <button class="flex items-center gap-1 hover:text-gray-700"
                                            wire:click="sortBy('{{ $column['key'] }}')">
                                        {{ $column['label'] }}
                                        @if($sortField === $column['key'])
                                            <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        @endif
                                    </button>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                        @if(isset($actions))
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                عملیات
                            </th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($rows as $row)
                        <tr class="ui-row-hover">
                            @if($selectable)
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                           value="{{ $row['id'] }}" wire:model="selected">
                                </td>
                            @endif
                            @foreach($columns as $column)
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $row[$column['key']] ?? '' }}
                                </td>
                            @endforeach
                            @if(isset($actions))
                                <td class="px-4 py-4 whitespace-nowrap text-left text-sm font-medium">
                                    {{ $actions($row) }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(count($bulkActions) > 0)
            <div class="bg-gray-50 px-4 py-3 flex items-center gap-3 border-t border-gray-200">
                <span class="text-sm text-gray-700">
                    <span x-text="selected.length"></span> مورد انتخاب شده
                </span>
                @foreach($bulkActions as $action)
                    <button class="px-3 py-2 text-sm rounded-xl {{ $action['class'] ?? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                            wire:click="{{ $action['action'] }}">
                        {{ $action['label'] }}
                    </button>
                @endforeach
            </div>
        @endif
    @else
        <div class="p-12 text-center">
            @if($emptyIcon)
                <svg class="mx-auto w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
            @endif
            <p class="text-gray-500 text-sm">{{ $emptyMessage }}</p>
        </div>
    @endif
</div>
```

### 6.2 Shared Filter Bar Component

```blade
{{-- resources/views/components/filter/filter-bar.blade.php --}}
@props([
    'filters' => [],
    'resetUrl' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl shadow-sm p-6']) }}>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach($filters as $filter)
            @switch($filter['type'] ?? 'text')
                @case('search')
                    <div>
                        <x-ui-input type="search"
                            placeholder="{{ $filter['placeholder'] ?? 'جستجو...' }}"
                            wire:model.live.debounce.300ms="{{ $filter['model'] }}"
                            class="w-full" />
                    </div>
                @break

                @case('select')
                    <div>
                        <x-ui-select
                            :options="$filter['options']"
                            placeholder="{{ $filter['placeholder'] ?? 'انتخاب کنید' }}"
                            wire:model.live="{{ $filter['model'] }}"
                            class="w-full" />
                    </div>
                @break

                @case('date-range')
                    <div class="flex gap-2">
                        <x-ui-input type="date"
                            wire:model.live="{{ $filter['model_from'] }}"
                            class="w-full" />
                        <x-ui-input type="date"
                            wire:model.live="{{ $filter['model_to'] }}"
                            class="w-full" />
                    </div>
                @break
            @endswitch
        @endforeach
    </div>

    @if($resetUrl)
        <div class="mt-4 flex justify-end">
            <a href="{{ $resetUrl }}"
               class="px-4 py-2 text-sm rounded-xl bg-gray-100 text-gray-700 hover:bg-gray-200 transition-all duration-150">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    پاک کردن فیلترها
                </span>
            </a>
        </div>
    @endif
</div>
```

### 6.3 Shared Status Badge Component

```blade
{{-- resources/views/components/badge/status-badge.blade.php --}}
@props([
    'variant' => 'default',
    'label' => null,
    'dot' => true,
])

@php
$variants = [
    'published' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'dot' => 'bg-emerald-500'],
    'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'dot' => 'bg-gray-400'],
    'pending' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'dot' => 'bg-amber-500'],
    'approved' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'dot' => 'bg-blue-500'],
    'rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'dot' => 'bg-red-500'],
    'scheduled' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'dot' => 'bg-purple-500'],
    'active' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'dot' => 'bg-emerald-500'],
    'inactive' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'dot' => 'bg-gray-400'],
    'success' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'dot' => 'bg-emerald-500'],
    'warning' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'dot' => 'bg-amber-500'],
    'danger' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'dot' => 'bg-red-500'],
    'info' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'dot' => 'bg-blue-500'],
    'default' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'dot' => 'bg-gray-400'],
];

$style = $variants[$variant] ?? $variants['default'];
@endphp

<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $style['bg'] }} {{ $style['text'] }}">
    @if($dot)
        <span class="ui-status-dot {{ $style['dot'] }}"></span>
    @endif
    {{ $label ?? $variant }}
</span>
```

### 6.4 Usage Example

```blade
{{-- Example: Admin Users Index --}}
<div>
    <x-ui-page-header title="مدیریت کاربران" :breadcrumbs="[['label' => 'داشبورد', 'url' => route('admin.dashboard')], ['label' => 'کاربران']]">
        <x-slot:actions>
            <x-ui-button href="{{ route('admin.users.create') }}">
                کاربر جدید
            </x-ui-button>
        </x-slot:actions>
    </x-ui-page-header>

    <div class="space-y-6">
        <x-ui-filter-bar
            :filters="[
                ['type' => 'search', 'model' => 'filters.search', 'placeholder' => 'جستجوی کاربر...'],
                ['type' => 'select', 'model' => 'filters.role', 'placeholder' => 'نقش', 'options' => $roles],
                ['type' => 'select', 'model' => 'filters.status', 'placeholder' => 'وضعیت', 'options' => ['active' => 'فعال', 'inactive' => 'غیرفعال']],
            ]"
            resetUrl="{{ route('admin.users.index') }}" />

        <x-ui-table
            :columns="[
                ['key' => 'name', 'label' => 'نام', 'sortable' => true],
                ['key' => 'email', 'label' => 'ایمیل', 'sortable' => true],
                ['key' => 'role', 'label' => 'نقش'],
                ['key' => 'status', 'label' => 'وضعیت'],
                ['key' => 'created_at', 'label' => 'تاریخ ثبت', 'sortable' => true],
            ]"
            :rows="$users"
            selectable
            sortable
            sortField="filters.sortField"
            sortDirection="filters.sortDirection"
            :bulkActions="[
                ['label' => 'فعال‌سازی', 'action' => 'activateSelected()', 'class' => 'bg-emerald-600 text-white hover:bg-emerald-700'],
                ['label' => 'غیرفعال‌سازی', 'action' => 'deactivateSelected()', 'class' => 'bg-red-600 text-white hover:bg-red-700'],
            ]"
            emptyMessage="هیچ کاربری یافت نشد">

            <x-slot:actions="{ row }">
                <x-ui-action-group
                    :actions="[
                        ['label' => 'ویرایش', 'url' => route('admin.users.edit', $user), 'icon' => 'pencil'],
                        ['label' => 'حذف', 'url' => route('admin.users.destroy', $user), 'icon' => 'trash', 'confirm' => true],
                    ]" />
            </x-slot:actions>
        </x-ui-table>
    </div>
</div>
```

---

## Task 7 — Override-Safe UI System

### 7.1 Three-Level Override Architecture

```
Level 1: CSS Variables (Runtime)
├── Fastest, safest override
├── No rebuild required
├── Scoped to tenant/project
└── Example: --ui-primary-color, --ui-border-radius

Level 2: Tailwind Config (Build Time)
├── Medium effort, project-wide
├── Requires Tailwind rebuild
├── Scoped to project
└── Example: theme.extend.colors.primary

Level 3: Blade Override (Structural)
├── Highest effort, last resort
├── Full component replacement
├── Scoped to specific view
└── Example: resources/views/vendor/ui-kit/...
```

### 7.2 Override Decision Tree

```
Need to customize a shared component?
│
├─ Is it just a color/spacing change?
│   └─ YES → Use CSS Variables (Level 1)
│
├─ Is it a project-wide visual change?
│   └─ YES → Use Tailwind Config (Level 2)
│
├─ Is it a structural/layout change?
│   └─ YES → Use Blade Override (Level 3)
│
└─ Is it a completely different component?
    └─ YES → Create tenant-specific component (NOT shared)
```

### 7.3 Anti-Patterns

| Anti-Pattern | Why It's Wrong | Correct Approach |
|---|---|---|
| Copying a shared component to modify it | Creates drift, defeats purpose of shared UI Kit | Use Blade override with `@extends` or publish |
| Hardcoding colors in Blade | Breaks theming, creates inconsistency | Use CSS variables or config values |
| Adding project-specific logic to shared component | Violates single responsibility | Use slots or create tenant-specific wrapper |
| Modifying shared component for one project's needs | Breaks other project | Use override mechanism |
| Creating duplicate CSS classes | Increases bundle size, creates drift | Extend Tailwind config |

---

## Task 8 — Shared UI Contracts

### 8.1 Component Contract Interface

```php
<?php
// src/Contracts/ComponentContract.php
namespace SaasPlatform\UiKit\Contracts;

interface ComponentContract
{
    /**
     * Get the component's unique identifier.
     */
    public static function getName(): string;

    /**
     * Get the component's default props.
     */
    public static function getDefaultProps(): array;

    /**
     * Get the component's required props.
     */
    public static function getRequiredProps(): array;

    /**
     * Get the component's available slots.
     */
    public static function getSlots(): array;

    /**
     * Get the component's available variants.
     */
    public static function getVariants(): array;

    /**
     * Validate the component's props.
     */
    public function validate(array $props): bool;
}
```

### 8.2 Component Contracts

#### Card Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **StatCard** | `title`, `value` | `icon`, `trend`, `trendValue`, `color`, `size` | `icon` |
| **ModuleCard** | `title`, `description` | `icon`, `status`, `version`, `actions` | `icon`, `actions`, `footer` |
| **EmptyState** | `title` | `description`, `actionLabel`, `actionUrl`, `icon` | `icon`, `actions` |

#### Table Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **Table** | `columns`, `rows` | `selectable`, `sortable`, `sortField`, `sortDirection`, `bulkActions`, `emptyMessage` | `actions`, `bulk-actions` |
| **BulkActions** | `actions` | `selectedCount` | — |
| **Pagination** | — | `links`, `total`, `perPage`, `currentPage` | — |

#### Filter Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **FilterBar** | `filters` | `resetUrl` | — |
| **SearchInput** | — | `placeholder`, `model`, `debounce` | — |
| **SelectFilter** | `options` | `placeholder`, `model` | — |
| **DateRange** | — | `modelFrom`, `modelTo` | — |

#### Badge Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **StatusBadge** | `variant` | `label`, `dot`, `size` | — |
| **PriorityBadge** | `variant` | `label`, `pulse`, `size` | — |
| **TypeBadge** | `variant` | `label`, `size` | — |

#### Button Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **PrimaryButton** | — | `href`, `type`, `size`, `disabled`, `loading`, `icon` | default (label) |
| **SecondaryButton** | — | `href`, `type`, `size`, `disabled`, `loading`, `icon` | default (label) |
| **DangerButton** | — | `href`, `type`, `size`, `disabled`, `loading`, `icon` | default (label) |
| **IconButton** | — | `icon`, `size`, `href`, `disabled`, `tooltip` | — |
| **ActionGroup** | `actions` | `direction`, `size` | — |

#### Form Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **TextInput** | — | `type`, `placeholder`, `model`, `value`, `error`, `disabled`, `size` | — |
| **SelectInput** | `options` | `placeholder`, `model`, `value`, `error`, `disabled` | — |
| **TextareaInput** | — | `rows`, `placeholder`, `model`, `value`, `error` | — |
| **CheckboxInput** | — | `checked`, `label`, `value`, `model`, `disabled` | — |
| **FormGroup** | `label` | `required`, `error`, `helpText` | default (input) |

#### Layout Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **Sidebar** | `menuItems` | `collapsed`, `activeItem`, `logo` | `logo`, `footer`, `menu` |
| **SidebarGroup** | `title`, `items` | `icon`, `collapsed` | `icon` |
| **PageHeader** | `title` | `breadcrumbs`, `description` | `actions`, `breadcrumbs` |
| **Breadcrumbs** | `items` | — | — |

#### Modal Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **ConfirmModal** | — | `title`, `message`, `confirmText`, `cancelText`, `confirmClass`, `icon` | default (message) |

#### Alert Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **FlashMessage** | `type` | `message`, `dismissible`, `icon` | default (message) |
| **ValidationErrors** | `errors` | — | — |

#### Indicator Components

| Component | Required Props | Optional Props | Slots |
|---|---|---|---|
| **LoadingSpinner** | — | `size`, `color`, `label` | — |
| **PulseIndicator** | — | `color`, `size`, `pulse` | — |

### 8.3 Slot Conventions

| Slot Name | Purpose | Example Usage |
|---|---|---|
| `default` | Primary content | Button label, card body |
| `icon` | Icon override | Stat card icon, module card icon |
| `actions` | Action buttons | Page header actions, table row actions |
| `header` | Header content | Card header, modal header |
| `footer` | Footer content | Card footer, modal footer |
| `breadcrumbs` | Breadcrumb override | Page header breadcrumbs |
| `menu` | Menu content | Sidebar menu items |
| `logo` | Logo override | Sidebar logo |

### 8.4 Icon Standards

Every SVG icon in the shared UI Kit MUST follow this exact format:

```html
<svg class="w-5 h-5 flex-shrink-0 text-current" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" d="M..."/>
</svg>
```

**Rules**:
- `w-5 h-5` — default size (use `w-4 h-4` only for inline/small, `w-6 h-6` for stat cards)
- `flex-shrink-0` — prevent icon from shrinking in flex containers
- `text-current` — inherit parent text color
- `fill="none"` — outlined style
- `stroke="currentColor"` — inherit color from parent
- `stroke-width="1.5"` — consistent stroke thickness
- `viewBox="0 0 24 24
" — consistent viewBox

### 8.5 Responsive Conventions

| Breakpoint | Prefix | Target Devices |
|---|---|---|
| `sm` | `sm:` | Mobile (640px+) |
| `md` | `md:` | Tablet (768px+) |
| `lg` | `lg:` | Desktop (1024px+) |
| `xl` | `xl:` | Large Desktop (1280px+) |
| `2xl` | `2xl:` | Extra Large (1536px+) |

**Standard Responsive Patterns**:
- Filter grids: `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4`
- Stat card grids: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`
- Sidebar: `fixed lg:relative` (mobile overlay, desktop inline)
- Tables: `overflow-x-auto` on mobile
- Modals: `w-full sm:max-w-lg` (full width on mobile, constrained on desktop)

### 8.6 RTL Conventions

Since both projects are Persian/Farsi applications, all shared components MUST support RTL layout:

| Rule | Implementation |
|---|---|
| **Text alignment** | Use `text-right` for labels/headers, `text-left` for actions |
| **Margins** | Use `ml-*` for left margins, `mr-*` for right margins (Tailwind RTL handles swap) |
| **Flex direction** | Use `flex` with appropriate gaps, avoid `flex-row-reverse` |
| **Icons in buttons** | Icon before text: `gap-2` with icon first |
| **Dropdown position** | `origin-top-left` or `origin-top-right` based on context |
| **Breadcrumb separator** | Use `/` or `←` (left arrow) for RTL |
| **Form validation** | Error messages appear below inputs, right-aligned |
| **Modal close button** | Top-left corner (X button) |
| **Pagination** | Previous on right, Next on left |
| **Search icon** | Left side of input (inside padding) |

**Tailwind RTL Configuration**:
```js
// tailwind.config.js
module.exports = {
    // ...
    future: {
        // Ensure RTL variants are enabled
        hoverOnlyWhenSupported: true,
    },
}
```

**Usage in Blade**:
```blade
{{-- RTL-safe icon + text pattern --}}
<button class="flex items-center gap-2">
    <svg class="w-5 h-5 flex-shrink-0" ...>
    <span>متن دکمه</span>
</button>

{{-- RTL-safe dropdown --}}
<div x-show="open" class="origin-top-left absolute left-0 mt-2 w-48">
    {{-- dropdown content --}}
</div>
```

---

## Task 9 — Migration Priority Map

### 9.1 Migration Phases

The migration from inline components to shared UI Kit components is divided into 4 phases over 8 weeks. Each phase targets components with similar risk profiles.

#### Phase A — Low Risk / High Impact (Weeks 1-2)

| Component | Priority | Risk | Effort | Views Affected (saas) | Views Affected (customer) |
|---|---|---|---|---|---|
| **Primary Button** | P0 | Low | 2 days | 25+ | 30+ |
| **Secondary Button** | P0 | Low | 1 day | 15+ | 20+ |
| **Danger Button** | P0 | Low | 1 day | 8+ | 12+ |
| **Text Input** | P0 | Low | 2 days | 20+ | 25+ |
| **Select Input** | P0 | Low | 1 day | 15+ | 18+ |
| **Textarea** | P0 | Low | 0.5 day | 5+ | 8+ |
| **Checkbox** | P0 | Low | 0.5 day | 3+ | 5+ |
| **Loading Spinner** | P0 | Low | 0.5 day | 10+ | 15+ |
| **Flash Messages** | P0 | Low | 1 day | 8+ | 10+ |
| **Validation Errors** | P0 | Low | 0.5 day | 5+ | 8+ |

**Phase A Strategy**: Replace one component at a time across ALL views in both projects. Each component gets its own branch and PR. Test after each component migration.

#### Phase B — Medium Risk / High Impact (Weeks 2-4)

| Component | Priority | Risk | Effort | Views Affected (saas) | Views Affected (customer) |
|---|---|---|---|---|---|
| **Status Badge** | P1 | Medium | 2 days | 12+ | 18+ |
| **Type Badge** | P1 | Medium | 1 day | 8+ | 12+ |
| **Priority Badge** | P1 | Medium | 1 day | 3+ | 6+ |
| **Empty State** | P1 | Medium | 1 day | 5+ | 8+ |
| **Confirm Modal** | P1 | Medium | 1 day | 6+ | 10+ |
| **Action Group** | P1 | Medium | 1 day | 10+ | 15+ |
| **Icon Button** | P1 | Medium | 1 day | 8+ | 12+ |
| **Form Group** | P1 | Medium | 1 day | 15+ | 20+ |

**Phase B Strategy**: Badges require reconciling the different implementations (inline classes vs. `.badge-*` classes). Create a mapping layer. Migrate badge components first, then modals and action groups.

#### Phase C — Higher Risk / Structural (Weeks 3-6)

| Component | Priority | Risk | Effort | Views Affected (saas) | Views Affected (customer) |
|---|---|---|---|---|---|
| **Filter Bar** | P2 | High | 3 days | 8+ | 12+ |
| **Search Input** | P2 | Medium | 1 day | 8+ | 12+ |
| **Select Filter** | P2 | Medium | 1 day | 6+ | 10+ |
| **Date Range** | P2 | Medium | 1 day | 4+ | 6+ |
| **Table Wrapper** | P2 | High | 3 days | 10+ | 15+ |
| **Bulk Actions** | P2 | High | 2 days | 5+ | 8+ |
| **Pagination** | P2 | Medium | 1 day | 8+ | 12+ |
| **Stat/KPI Cards** | P2 | High | 2 days | 5+ | 8+ |

**Phase C Strategy**: Filter and Table components are structural — they change the HTML structure of views. Migrate one view at a time. Use feature flags to toggle between old and new implementations during transition.

#### Phase D — High Risk / Architectural (Weeks 5-8)

| Component | Priority | Risk | Effort | Views Affected (saas) | Views Affected (customer) |
|---|---|---|---|---|---|
| **Page Header** | P3 | High | 3 days | 15+ | 20+ |
| **Breadcrumbs** | P3 | High | 2 days | 10+ | 15+ |
| **Sidebar** | P3 | Critical | 5 days | 1 (layout) | 1 (layout) |
| **Sidebar Icons** | P3 | Critical | 3 days | 1 (layout) | 1 (layout) |
| **Pulse Indicator** | P3 | Medium | 1 day | 0 | 5+ |

**Phase D Strategy**: Layout components are the highest risk because they affect every page. Migrate the sidebar last, after all other components are stable. Use a canary release: deploy to staging first, monitor for 48 hours, then deploy to production.

### 9.2 Migration Timeline (Gantt)

```
Week 1    |████████████████|  Phase A Start
          |  Buttons (P0)  |  Primary, Secondary, Danger, Icon
          |  Forms (P0)    |  Text Input, Select, Textarea, Checkbox
Week 2    |████████████████|  Phase A Complete / Phase B Start
          |  Alerts (P0)   |  Flash Messages, Validation Errors
          |  Badges (P1)   |  Status, Type, Priority
Week 3    |████████████████|  Phase B Complete / Phase C Start
          |  Modals (P1)   |  Confirm Modal
          |  Filters (P2)  |  Filter Bar, Search, Select, Date Range
Week 4    |████████████████|  Phase C Continue
          |  Tables (P2)   |  Table Wrapper, Bulk Actions, Pagination
Week 5    |████████████████|  Phase C Complete / Phase D Start
          |  Cards (P2)    |  Stat/KPI Cards, Empty State
          |  Layout (P3)   |  Page Header, Breadcrumbs
Week 6    |████████████████|  Phase D Continue
          |  Layout (P3)   |  Sidebar preparation
Week 7    |████████████████|  Phase D Continue
          |  Layout (P3)   |  Sidebar migration, Icons
Week 8    |████████████████|  Phase D Complete
          |  Polish        |  Edge cases, cleanup, documentation
```

### 9.3 Migration Rules

1. **One component at a time**: Never migrate multiple components in the same PR
2. **Backward compatibility**: Old classes/styles must continue working during transition
3. **Per-view migration**: Migrate one view file completely before moving to the next
4. **No regressions**: Visual appearance must be identical before and after migration
5. **Both projects simultaneously**: Migrate the same component in both projects in the same PR
6. **Test after each migration**: Run visual regression tests after each component migration
7. **Feature flags**: Use config flags to toggle between old and new implementations during Phase C/D

### 9.4 Rollback Plan

```bash
# If a migration causes issues:
git revert <migration-commit>
git push origin main

# Restore old Blade files (if deleted):
git checkout HEAD~1 -- resources/views/admin/<view>.blade.php

# Disable shared component (if registered):
# Set in .env: UI_KIT_ENABLED=false
# Or remove from composer.json require
```

**Rollback Criteria**:
- Visual regression detected in any view
- Performance degradation (>200ms increase in page load)
- JavaScript errors in shared components
- Broken Livewire bindings
- RTL layout issues

---

## Task 10 — Frontend Anti-Drift Enforcement

### 10.1 Drift Detection Commands

#### 10.1.1 Duplicate Blade Detection

```php
<?php
// src/Console/Commands/DetectDuplicateBlades.php
namespace SaasPlatform\UiKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DetectDuplicateBlades extends Command
{
    protected $signature = 'ui-kit:detect-duplicates
        {--threshold=80 : Similarity percentage threshold}
        {--path= : Custom path to scan}';

    protected $description = 'Detect duplicate Blade files across projects';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $basePath = $this->option('path') ?? base_path();

        $this->info("Scanning for duplicate Blade files (threshold: {$threshold}%)...");

        $bladeFiles = File::glob("{$basePath}/resources/views/**/*.blade.php");
        $duplicates = [];

        foreach ($bladeFiles as $i => $file1) {
            $content1 = file_get_contents($file1);
            foreach ($bladeFiles as $j => $file2) {
                if ($j <= $i) continue;
                $content2 = file_get_contents($file2);
                $similarity = $this->calculateSimilarity($content1, $content2);
                if ($similarity >= $threshold) {
                    $duplicates[] = [
                        'file1' => $file1,
                        'file2' => $file2,
                        'similarity' => $similarity,
                    ];
                }
            }
        }

        if (count($duplicates) === 0) {
            $this->info('✅ No duplicate Blade files found.');
            return Command::SUCCESS;
        }

        $this->warn('⚠️  Duplicate Blade files detected:');
        foreach ($duplicates as $dup) {
            $this->line("  {$dup['similarity']}% similar:");
            $this->line("    - {$dup['file1']}");
            $this->line("    - {$dup['file2']}");
        }

        return Command::FAILURE;
    }

    private function calculateSimilarity(string $a, string $b): float
    {
        $a = preg_replace('/\s+/', ' ', $a);
        $b = preg_replace('/\s+/', ' ', $b);
        similar_text($a, $b, $percent);
        return round($percent, 1);
    }
}
```

#### 10.1.2 Shared Component Usage Audit

```php
<?php
// src/Console/Commands/AuditSharedUsage.php
namespace SaasPlatform\UiKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditSharedUsage extends Command
{
    protected $signature = 'ui-kit:audit-usage
        {--project= : Project path to audit}';

    protected $description = 'Audit usage of shared UI Kit components';

    public function handle(): int
    {
        $projectPath = $this->option('project') ?? base_path();
        $components = [
            'x-ui-button', 'x-ui-secondary-button', 'x-ui-danger-button',
            'x-ui-input', 'x-ui-select', 'x-ui-textarea', 'x-ui-checkbox',
            'x-ui-form-group', 'x-ui-status-badge', 'x-ui-priority-badge',
            'x-ui-type-badge', 'x-ui-filter-bar', 'x-ui-table',
            'x-ui-page-header', 'x-ui-breadcrumbs', 'x-ui-flash',
            'x-ui-validation-errors', 'x-ui-confirm-modal', 'x-ui-spinner',
            'x-ui-empty-state', 'x-ui-stat-card',
        ];

        $this->info("Auditing shared component usage in: {$projectPath}");
        $results = [];

        foreach ($components as $component) {
            $bladeFiles = File::glob("{$projectPath}/resources/views/**/*.blade.php");
            $count = 0;
            $files = [];

            foreach ($bladeFiles as $file) {
                $content = file_get_contents($file);
                if (str_contains($content, $component)) {
                    $count++;
                    $files[] = str_replace($projectPath, '', $file);
                }
            }

            $results[$component] = [
                'count' => $count,
                'files' => $files,
            ];
        }

        $this->table(
            ['Component', 'Usage Count', 'Files'],
            collect($results)->map(fn($r, $c) => [$c, $r['count'], implode("\n", array_slice($r['files'], 0, 5))])->toArray()
        );

        $totalUsage = collect($results)->sum('count');
        $this->info("Total shared component usage: {$totalUsage} instances");

        return Command::SUCCESS;
    }
}
```

#### 10.1.3 CSS Class Drift Detection

```bash
#!/bin/bash
# scripts/detect-css-drift.sh
# Usage: bash detect-css-drift.sh

echo "=== CSS Class Drift Detection ==="
echo ""

# Check for hardcoded color classes that should use shared tokens
echo "1. Hardcoded color classes (should use ui-kit tokens):"
grep -rn 'bg-indigo-600\|bg-purple-600\|bg-emerald-600\|bg-red-600\|bg-amber-500\|bg-blue-500' \
  resources/views/ --include="*.blade.php" | grep -v 'vendor' | head -20

echo ""
echo "2. Non-standard border-radius (should use rounded-xl or rounded-2xl):"
grep -rn 'rounded\($\| \)\|rounded-sm\|rounded-md\|rounded-lg' \
  resources/views/ --include="*.blade.php" | grep -v 'vendor\|rounded-full\|rounded-xl\|rounded-2xl' | head -20

echo ""
echo "3. Inline icon sizes (should use w-5 h-5 default):"
grep -rn 'w-4 h-4\|w-6 h-6\|w-8 h-8' \
  resources/views/ --include="*.blade.php" | grep -v 'vendor\|avatar\|stat' | head -20

echo ""
echo "4. Non-standard button padding (should use px-4 py-3):"
grep -rn 'px-[0-9] py-[0-9]\|px-[0-9][0-9] py-[0-9]' \
  resources/views/ --include="*.blade.php" | grep -v 'vendor\|px-4 py-3' | head -20
```

### 10.2 Enforcement Mechanisms

#### 10.2.1 PR Checklist (GitHub Actions)

```yaml
# .github/workflows/ui-kit-enforcement.yml
name: UI Kit Enforcement

on:
  pull_request:
    paths:
      - 'resources/views/**/*.blade.php'
      - '**/*.css'

jobs:
  drift-detection:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Check for hardcoded colors
        run: |
          if grep -rn 'bg-indigo-600\|bg-purple-600\|bg-emerald-600\|bg-red-600' resources/views/ --include="*.blade.php" | grep -v 'vendor'; then
            echo "❌ Hardcoded colors detected. Use ui-kit tokens instead."
            exit 1
          fi
          echo "✅ No hardcoded colors found."

      - name: Check for non-standard border-radius
        run: |
          if grep -rn 'rounded-sm\|rounded-md\|rounded-lg' resources/views/ --include="*.blade.php" | grep -v 'vendor\|rounded-full\|rounded-xl\|rounded-2xl'; then
            echo "❌ Non-standard border-radius detected. Use rounded-xl or rounded-2xl."
            exit 1
          fi
          echo "✅ Standard border-radius used."

      - name: Check for duplicate Blade files
        run: |
          php artisan ui-kit:detect-duplicates --threshold=80
```

#### 10.2.2 Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit (install with: ln -s ../../scripts/pre-commit-ui-kit.sh .git/hooks/pre-commit)

echo "🔍 UI Kit Pre-commit Check..."

# Check for hardcoded colors in staged Blade files
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.blade\.php$')

if [ -n "$STAGED_FILES" ]; then
    for FILE in $STAGED_FILES; do
        if grep -q 'bg-indigo-600\|bg-purple-600\|bg-emerald-600\|bg-red-600' "$FILE"; then
            echo "❌ Hardcoded color in $FILE"
            echo "   Use ui-kit tokens instead (e.g., x-ui-button instead of bg-indigo-600)"
            exit 1
        fi
    done
fi

echo "✅ UI Kit check passed"
exit 0
```

#### 10.2.3 Weekly Drift Report

```php
<?php
// src/Console/Commands/GenerateDriftReport.php
namespace SaasPlatform\UiKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateDriftReport extends Command
{
    protected $signature = 'ui-kit:drift-report
        {--output= : Output file path}';

    protected $description = 'Generate weekly drift report';

    public function handle(): int
    {
        $outputPath = $this->option('output') ?? storage_path('reports/ui-kit-drift-' . date('Y-m-d') . '.md');
        $projectPaths = [
            'saas-platform' => base_path(),
            'customer-site' => dirname(base_path()) . '/customer-site',
        ];

        $report = "# UI Kit Drift Report — " . date('Y-m-d') . "\n\n";

        foreach ($projectPaths as $name => $path) {
            if (!is_dir($path)) continue;

            $report .= "## {$name}\n\n";

            // Count inline components vs shared components
            $bladeFiles = File::glob("{$path}/resources/views/**/*.blade.php");
            $inlineCount = 0;
            $sharedCount = 0;

            foreach ($bladeFiles as $file) {
                $content = file_get_contents($file);
                if (preg_match('/bg-(indigo|purple|emerald|red|amber|blue)-\d+/', $content)) {
                    $inlineCount++;
                }
                if (str_contains($content, 'x-ui-')) {
                    $sharedCount++;
                }
            }

            $report .= "- Blade files: " . count($bladeFiles) . "\n";
            $report .= "- Inline component usage: {$inlineCount}\n";
            $report .= "- Shared component usage: {$sharedCount}\n";
            $report .= "- Migration progress: " . round(($sharedCount / max($sharedCount + $inlineCount, 1)) * 100, 1) . "%\n\n";
        }

        File::put($outputPath, $report);
        $this->info("Drift report generated: {$outputPath}");

        return Command::SUCCESS;
    }
}
```

### 10.3 Ownership Validation Rules

| # | Rule | Enforcement | Severity |
|---|---|---|---|
| 1 | Every shared component MUST have a registered Blade component alias | CI check | Critical |
| 2 | Every shared component MUST implement ComponentContract | CI check | Critical |
| 3 | No hardcoded colors in Blade files (use tokens or config) | Pre-commit hook | Error |
| 4 | No non-standard border-radius (use rounded-xl or rounded-2xl) | Pre-commit hook | Warning |
| 5 | Icon SVGs MUST use w-5 h-5 default size | CI check | Warning |
| 6 | Every new Blade file MUST be checked for duplication | Weekly report | Info |
| 7 | Shared components MUST NOT contain project-specific logic | Code review | Critical |
| 8 | Tenant overrides MUST use CSS variables, not Blade modification | Code review | Error |

### 10.4 Enforcement Dashboard

```bash
# Generate and view the enforcement dashboard
php artisan ui-kit:drift-report --output=storage/reports/drift-latest.md
php artisan ui-kit:audit-usage
php artisan ui-kit:detect-duplicates --threshold=75

# Quick health check
echo "=== UI Kit Health Check ==="
echo "Components registered:"
php artisan tinker --execute="echo collect(Blade::getClassComponentAliases())->filter(fn($v, $k) => str_starts_with($k, 'ui-'))->count();"
echo ""
echo "Views using shared components:"
grep -rn 'x-ui-' resources/views/ --include="*.blade.php" | wc -l
echo ""
echo "Views with hardcoded colors (need migration):"
grep -rn 'bg-indigo-600\|bg-purple-600\|bg-emerald-600\|bg-red-600' resources/views/ --include="*.blade.php" | wc -l
```

---

## Conclusion

### Phase 1 Summary

| # | Deliverable | Status | Key Output |
|---|---|---|---|
| 1 | Shared UI Audit | ✅ Complete | 51 components audited, 23 identical, 10 near-identical, 17 drifted, 1 tenant-specific |
| 2 | UI Ownership Definition | ✅ Complete | 34 shared components with ownership matrix, 5 tenant-specific components |
| 3 | Shared UI Structure | ✅ Complete | 30+ Blade components, UiKitServiceProvider, composer.json, directory architecture |
| 4 | Design Token Standardization | ✅ Complete | 7 token categories (spacing, radius, shadows, colors, typography, icons, transitions) |
| 5 | Shared Animation System | ✅ Complete | 10 animations, centralized CSS, Pulse Indicator component, usage guidelines |
| 6 | Shared Table/Filter System | ✅ Complete | Table, Filter Bar, Status Badge components with full Blade implementations |
| 7 | Override-Safe UI System | ✅ Complete | 3-level override architecture, decision tree, anti-patterns table |
| 8 | Shared UI Contracts | ✅ Complete | ComponentContract interface, 34 component contracts, slot conventions, icon/RTL standards |
| 9 | Migration Priority Map | ✅ Complete | 4-phase, 8-week migration plan with 30+ components, Gantt chart, rollback plan |
| 10 | Frontend Anti-Drift Enforcement | ✅ Complete | 3 detection commands, CI workflow, pre-commit hook, weekly report, 8 validation rules |

### Key Statistics

- **51 components audited** across both projects
- **34 components** designated as shared (67%)
- **5 components** designated as tenant-specific (10%)
- **30+ Blade components** in the shared UI Kit
- **4-phase migration** over 8 weeks
- **8 validation rules** for ownership enforcement
- **3 override levels** (CSS Variables → Tailwind Config → Blade Override)
- **10 animations** standardized in shared CSS
- **7 design token categories** with standardized values

### Next Steps

1. **Phase 2 — Shared Module Migration**: Migrate business modules (Forms, Menus, Pages, Activity Log, Settings) to the shared package architecture
2. **Phase 3 — Tenant Context Unification**: Implement the 3-phase Tenant Context compatibility layer
3. **Phase 4 — Update Infrastructure**: Implement the update safety foundation with rollback hooks and version contracts
4. **Phase 5 — Anti-Drift Automation**: Deploy the drift detection CI/CD pipeline and enforcement dashboard

### Risk Mitigation

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Visual regression during migration | Medium | High | Per-component testing, visual regression tests, feature flags |
| Developer resistance to new patterns | Low | Medium | Documentation, examples, gradual migration, pair programming |
| Performance impact from shared components | Low | Low | Lazy loading, caching, minimal overhead from Blade components |
| Breaking changes in shared components | Medium | High | Semantic versioning, backward compatibility guarantees, deprecation warnings |
| RTL issues in shared components | Low | Medium | RTL testing in both projects, RTL conventions documented |
| Sidebar migration breaking all pages | Low | Critical | Canary release, 48-hour staging period, immediate rollback capability |

---

> **Phase 1 is the foundation for all future modularization.** Without a shared UI Kit, every module migration would require duplicating frontend code. With this foundation in place, Phase 2 (Shared Module Migration) can proceed safely and efficiently.
>
> **Next**: Proceed to Phase 2 — Shared Module Migration
