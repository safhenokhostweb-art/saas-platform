# Phase 10.0 — Frontend Authority Audit Report
Generated: 2026-05-28

---

## Executive Summary

| Dimension | Score | Grade |
|---|---|---|
| Design Token Coverage | 42/100 | F |
| Component Canonicality | 61/100 | D |
| Visual Parity (saas ↔ customer-site) | 55/100 | F |
| Inline Style Elimination | 38/100 | F |
| Tailwind Version Consistency | 0/100 | CRITICAL |
| Shared Runtime Coverage | 48/100 | F |
| Navigation Canonicality | 30/100 | F |
| **Overall Frontend Authority Score** | **39/100** | **F** |

**Phase 10.0 Objective:** Transform this to 95+/100 (Grade A+).

---

## CRITICAL FINDINGS

### CRITICAL-001 — Tailwind Version Divergence
**Severity: CRITICAL**
- `saas-platform` uses **Tailwind CSS v3** (`tailwind.config.js`, `@tailwind` directives)
- `customer-site` uses **Tailwind CSS v4** (`@tailwindcss/vite`, `@import 'tailwindcss'`, `@theme` blocks)
- These are fundamentally different compilation pipelines
- A class added to saas-platform's config does NOT exist in customer-site without duplication
- **Impact:** All module views compile differently per host project
- **Fix:** Standardize on CSS custom property–driven tokens that work with BOTH versions; isolate Tailwind-version-specific utilities to project-local layer

### CRITICAL-002 — Core-UI Namespace Registered by Wrong Provider
**Severity: CRITICAL**
- `x-core-ui::*` namespace is registered by `FormsServiceProvider`, not `CoreUtilsServiceProvider`
- If the Forms module is absent, the entire shared UI runtime collapses
- `TicketsServiceProvider` has a fallback but it creates a duplicate, divergent registration
- **Fix:** Register canonical `core-ui` namespace in `CoreUtilsServiceProvider` (always loaded)

### CRITICAL-003 — Design Token Prefix Conflict
**Severity: CRITICAL**
- Tokens in `design-tokens.blade.php` use `--forms-*` prefix (module-scoped)
- Admin layout in `saas-platform` defines `--admin-*` variables
- Customer-site uses `--platform-*` variables
- No single canonical `--ui-*` token namespace exists
- **Impact:** Token changes in forms module do NOT propagate to tickets or navigation
- **Fix:** Unify all tokens under `--ui-*` canonical namespace in `core-utils/resources/css/design-tokens.css`

### CRITICAL-004 — Tickets Module Has No Design Token Usage
**Severity: CRITICAL**
- `tickets/admin/index.blade.php` contains 100+ lines of inline `<style>` with hardcoded hex values
- `tickets/admin/show.blade.php` same pattern
- Zero usage of any design token or `x-core-ui::*` component
- All animations, shadows, colors are hardcoded inside `@push('head')` style blocks
- **Impact:** Visual drift is guaranteed; any token change has zero effect on tickets UI
- **Fix:** Full tickets module retrofit (Task 7)

### CRITICAL-005 — Separate Button Systems (3 implementations)
**Severity: CRITICAL**
- System 1: `.btn-*` CSS classes in `saas-platform/resources/css/app.css`
- System 2: `.btn-gradient` in `tickets/index.blade.php` inline style
- System 3: Tailwind utility composition in `forms/index.blade.php` (bg-gradient-to-r from-indigo-600...)
- No canonical `x-core-ui::button` component exists
- **Fix:** Create `x-core-ui::button` component; retire all inline button systems

### CRITICAL-006 — Dual Navigation Systems
**Severity: CRITICAL**
- saas-platform: Glass sidebar in `layouts/admin.blade.php` with custom CSS vars + inline `<style>`
- customer-site: `admin/layout.blade.php` with separate sidebar implementation
- No shared navigation component exists
- **Impact:** Navigation changes must be applied twice; drift is structural
- **Fix:** Create canonical `x-core-ui::sidebar-group` and `x-core-ui::navigation-item` (Task 8)

---

## HIGH SEVERITY FINDINGS

### HIGH-001 — Inline `<style>` Blocks in Layouts
**Files:**
- `saas-platform/resources/views/layouts/admin.blade.php` (lines 14-46): CSS vars + sidebar link styles
- `saas-platform/resources/views/layouts/app.blade.php` (lines 14-47): navbar, form, alert styles
- `customer-site/resources/views/layouts/auth.blade.php` (lines 13-41): btn, form-control, card styles

### HIGH-002 — No `x-core-ui::button` Component
All 83+ admin views and 120+ customer-site views implement buttons ad-hoc with Tailwind utility compositions. No canonical interactive primitive exists.

### HIGH-003 — No `x-core-ui::input`, `x-core-ui::select`, `x-core-ui::textarea`
All form controls are implemented with raw `<input>`, `<select>`, `<textarea>` tags with duplicated Tailwind classes across every view.

### HIGH-004 — No `x-core-ui::dropdown` or `x-core-ui::action-menu`
Dropdown menus in tables (edit/delete action menus) are each independently implemented with duplicated Alpine.js logic.

### HIGH-005 — No Canonical `x-core-ui::navigation-item` or `x-core-ui::sidebar-group`
Sidebar navigation items are raw `<a>` tags with duplicated hover/active state logic across both projects.

### HIGH-006 — Local Component in saas-platform `resources/views/components/`
Files: `alert.blade.php`, `flash.blade.php`, `validation-errors.blade.php`, `errors.blade.php`
These shadow or duplicate what should be `x-core-ui::alert`, `x-core-ui::toast`, etc.

### HIGH-007 — `admin/users/_delete_modal.blade.php` Is a Non-Canonical Modal
Uses custom Alpine.js pattern (`deleteModalOpen`, `confirmDelete()`) instead of `x-core-ui::confirm-modal`. Has hardcoded `bg-red-600` button class.

### HIGH-008 — Customer-Site Has No Core-UI Registration
`customer-site/app/Providers/AppServiceProvider.php` does NOT register `x-core-ui::*` namespace. Core-UI components only work because FormsServiceProvider is loaded — fragile dependency.

### HIGH-009 — `partials/platform-styles.blade.php` Duplicated in Both Projects
Both projects have this file with independent styling logic. No shared source.

### HIGH-010 — `vazirmatn-font-faces-inline.blade.php` Duplicated
Font declaration duplicated in both projects independently.

---

## MEDIUM SEVERITY FINDINGS

### MEDIUM-001 — `stats-card` Exists in core-utils AND forms as `stat-card`
Two implementations with different APIs and different visual styles.

### MEDIUM-002 — `empty-state` Exists in core-utils AND forms  
Duplicate component with different prop signatures.

### MEDIUM-003 — `status-badge` in core-utils, `form-status-pill` in forms, `priority-badge` in tickets
Three status badge implementations with no shared token base.

### MEDIUM-004 — Ticket Components Registered as `tickets::components`
`priority-badge`, `reply-item`, `status-badge` under `tickets::` namespace are invisible to forms module and vice versa.

### MEDIUM-005 — No `z-index` Token Governance
`z-index` values hardcoded: `z-[9998]` in admin delete modal, `z-50` in various dropdowns, no `--ui-z-*` canonical scale.

### MEDIUM-006 — No Focus Ring Token
Focus states use `focus-visible:ring-2 focus-visible:ring-indigo-400` (hardcoded indigo) instead of a canonical `--ui-focus-ring` token.

### MEDIUM-007 — Animation Definitions Scattered
- `@keyframes pdot-pulse` in tickets/index inline style
- `@keyframes skeleton-wave` in tickets/index inline style
- `@keyframes alert-pulse` in tickets/index inline style
- canonical motion tokens defined in design-tokens.blade.php but only accessible inside forms views

### MEDIUM-008 — App.css Button System Uses Hardcoded Hex Colors
`.btn-primary { background: #2563eb }` — not token-driven.

---

## LOW SEVERITY FINDINGS

### LOW-001 — `app - Copy.css` in customer-site
Backup file committed: `customer-site/resources/css/app - Copy.css`

### LOW-002 — CDN Fallback Script in app.blade.php
Commented-out CDN Tailwind fallback still exists in source

### LOW-003 — helpers.js Duplicated in Both Projects
Both projects have `resources/js/helpers.js` independently maintained

### LOW-004 — `support-chat.js` Duplicated
Both projects have `resources/js/components/support-chat.js` independently maintained

---

## Divergence Map: saas-platform ↔ customer-site

| UI Area | saas-platform | customer-site | Divergence |
|---|---|---|---|
| Design tokens | `--admin-*` (8 vars) | `--platform-*` (via partials) | CRITICAL |
| Tailwind version | v3 (config.js) | v4 (@tailwindcss/vite) | CRITICAL |
| Core-UI registration | Via FormsServiceProvider | None (inherited) | CRITICAL |
| Button system | `.btn-*` CSS classes | Inherited from saas | HIGH |
| Modal system | core-ui::modal + local _delete_modal | Inherited from modules | HIGH |
| Navigation | Glass sidebar (admin.blade.php) | Separate admin/layout.blade.php | HIGH |
| Font declaration | vazirmatn-font-faces-inline.blade.php | vazirmatn-font-faces-inline.blade.php | MEDIUM (duplicated) |
| Form controls | Raw HTML | Raw HTML | HIGH |
| Table system | core-ui::data-table (partial) | Not used | HIGH |
| Toast system | core-ui::toast | Not used directly | MEDIUM |

---

## Component Inventory

### Exists in core-utils/resources/views/components/ (16 files)
`data-table`, `empty-state`, `filter-bar`, `module-actions`, `module-card`, `module-status-badge`, `notification-center`, `patch-status-badge`, `security-patch-banner`, `security-score-card`, `stats-card`, `status-badge`, `table-pagination`, `trust-indicator`, `ai-inline-assistant`, `ai-runtime-store`

### Exists in forms/resources/views/core-ui/components/ (55+ files)
`activity-stream`, `activity-timeline`, `advanced-filters`, `ai-assistant-panel`, `ai-confidence-badge`, `ai-generation-progress`, `ai-stream-output`, `ai-suggestion-card`, `alert`, `analytics-card`, `autosave-indicator`, `badge`, `bulk-toolbar`, `collaborative-lock-banner`, `command-palette`, `confirm-modal`, `design-tokens`, `draggable-handle`, `empty-state`, `field-card`, `form-status-pill`, `live-feed-panel`, `live-presence-dot`, `live-status`, `modal`, `notification-center`, `page-header`, `quick-actions-panel`, `realtime-avatar-stack`, `section-header`, `skeleton-card`, `skeleton-list`, `slide-over`, `smart-search`, `spotlight-overlay`, `stat-card`, `tabs`, `toast`, `toolbar`, `update-banner`, `validation-badge`

### MISSING — Required by Phase 10.0 Spec
`button`, `icon-button`, `card`, `table` (canonical), `dropdown`, `input`, `textarea`, `select`, `checkbox`, `radio-card`, `navigation-item`, `sidebar-group`, `action-menu`

---

## Remediation Priority Order

1. **CRITICAL-002** — Move core-ui registration to CoreUtilsServiceProvider
2. **CRITICAL-003** — Create canonical `--ui-*` token namespace
3. **CRITICAL-001** — Adopt CSS-variable–based token system (Tailwind-version-agnostic)
4. **CRITICAL-004** — Retrofit tickets module
5. **CRITICAL-005** — Create `x-core-ui::button` and retire inline button systems
6. **CRITICAL-006** — Create canonical navigation components
7. **HIGH-001** — Extract inline layout styles to token-based system
8. **HIGH-002 through HIGH-005** — Create missing form/interactive primitives
