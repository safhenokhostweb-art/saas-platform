# Canonical Component Rules — Phase 10.1

## COMPONENT AUTHORITY REGISTRY

All canonical components live in:
`packages/saas-platform/core-utils/resources/views/components/`

Registered as `x-core-ui::*` via `Blade::anonymousComponentPath()` in `CoreUtilsServiceProvider`.

---

## CANONICAL COMPONENT CATALOG

### Form Controls
| Component | Usage | Phase |
|-----------|-------|-------|
| `x-core-ui::input` | Text, email, password, number inputs | 10.0 |
| `x-core-ui::select` | Select dropdowns | 10.0 |
| `x-core-ui::textarea` | Multi-line text | 10.0 |
| `x-core-ui::checkbox` | Checkbox with label | 10.0 |
| `x-core-ui::radio-card` | Radio button card layout | 10.0 |
| `x-core-ui::switch` | Toggle switch | **10.1** |

### Actions
| Component | Usage | Phase |
|-----------|-------|-------|
| `x-core-ui::button` | All CTA buttons | 10.0 |
| `x-core-ui::icon-button` | Icon-only actions | 10.0 |
| `x-core-ui::action-menu` | Dropdown action menu | 10.0 |

### Feedback / Status
| Component | Usage | Phase |
|-----------|-------|-------|
| `x-core-ui::badge` | Status badges, tags | **10.1** |
| `x-core-ui::alert` | Inline alerts | **10.1** |
| `x-core-ui::toast` | Toast notifications | **10.1** |
| `x-core-ui::skeleton` | Loading placeholder | **10.1** |
| `x-core-ui::stat-card` | Stats display | **10.1** |

### Overlays
| Component | Usage | Phase |
|-----------|-------|-------|
| `x-core-ui::modal` | All dialogs | **10.1** |
| `x-core-ui::confirm-modal` | Destructive action confirmation | **10.1** |
| `x-core-ui::dropdown` | Dropdown menus | 10.0 |
| `x-core-ui::dropdown-item` | Items inside dropdown | 10.0 |

### Data Display
| Component | Usage | Phase |
|-----------|-------|-------|
| `x-core-ui::data-table` | All data tables | **10.1** (upgraded) |
| `x-core-ui::card` | Content cards | 10.0 |
| `x-core-ui::empty-state` | Empty list/table states | 10.0 |
| `x-core-ui::table-pagination` | Pagination controls | 10.0 |

### Layout
| Component | Usage | Phase |
|-----------|-------|-------|
| `x-core-ui::page-header` | Page titles + actions | **10.1** |
| `x-core-ui::toolbar` | Filter/search + action bar | **10.1** |
| `x-core-ui::content-container` | Max-width content wrapper | **10.1** |
| `x-core-ui::filter-area` | Collapsible filter panel | **10.1** |
| `x-core-ui::stats-strip` | Horizontal stats grid | **10.1** |
| `x-core-ui::dashboard-grid` | Dashboard grid layout | **10.1** |
| `x-core-ui::navigation-item` | Sidebar nav item | 10.0 |
| `x-core-ui::sidebar-group` | Sidebar section grouping | 10.0 |

---

## COMPONENT CREATION RULES

1. New components MUST live in `core-utils/resources/views/components/`
2. Props MUST use `@props([])` with defaults
3. Styling MUST use `--ui-*` CSS custom properties exclusively
4. Interactive behavior MUST use Alpine.js (no vanilla JS)
5. Must include: `aria-*` labels, `focus-visible` rings, keyboard handling
6. Must support `{{ $attributes }}` passthrough for additional classes/attrs
7. MUST NOT import Tailwind hardcoded color classes like `bg-blue-600`
8. MUST support RTL layout via `--ui-text-align`, `--ui-dir` tokens

---

## COMPONENT OVERRIDE RULE

Modules may NOT override canonical components. The only allowed pattern:

```blade
{{-- Module-specific wrapper around canonical component --}}
<x-core-ui::button variant="primary" class="extra-module-class">
    {{ $slot }}
</x-core-ui::button>
```

Direct modification of core-utils component files is forbidden without a governance review.

---

## DEPRECATED COMPONENT LIST

| Was | Replace With | Deadline |
|-----|-------------|----------|
| Forms `core-ui::modal` (forms-module fork) | `x-core-ui::modal` | Phase 10.2 |
| Forms `core-ui::toast` (forms-module fork) | `x-core-ui::toast` | Phase 10.2 |
| Forms `core-ui::empty-state` (forms-module fork) | `x-core-ui::empty-state` | Phase 10.2 |
| Tickets inline `<style>` blocks | design-tokens.css vars | Phase 10.1 |
