# Cross-Module UI Drift Audit — Phase 10.1

**Audit Date:** 2026-05-28  
**Platform Version:** Phase 10.0 baseline  
**Scope:** saas-platform + customer-site, all modules, all shared components  
**Auditor:** Enterprise Frontend Governance System

---

## EXECUTIVE SUMMARY

| Dimension | Score | Status |
|-----------|-------|--------|
| Design Token Adoption | 74/100 | NEEDS WORK |
| Core-UI Component Usage | 61/100 | CRITICAL |
| Modal Normalization | 42/100 | CRITICAL |
| Form Control Normalization | 58/100 | HIGH |
| Table System | 35/100 | CRITICAL |
| Responsive Governance | 67/100 | HIGH |
| Animation Consistency | 72/100 | HIGH |
| Loading State Unification | 48/100 | CRITICAL |
| Accessibility Baseline | 63/100 | HIGH |
| Cross-Context Parity | 55/100 | HIGH |

**Overall Drift Score: 58/100 — Significant Remediation Required**

---

## SECTION 1 — FORMS MODULE AUDIT

### 1.1 forms/admin/forms/index.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| Uses `x-core-ui::design-tokens` — CORRECT | PASS | Canonical token injection present |
| Table rendered as raw `<table>` | CRITICAL | Must migrate to `x-core-ui::data-table` |
| Status badges use inline `bg-green-100 text-green-700` | HIGH | Must use `x-core-ui::badge` with token vars |
| Action buttons partially use `x-core-ui::button` | HIGH | Remaining raw `<button>` elements to migrate |
| Filter bar uses custom Alpine dropdown | HIGH | Must use `x-core-ui::dropdown` |
| No loading skeleton on table rows | HIGH | Must use `x-core-ui::skeleton` |
| Pagination hardcoded — non-canonical | HIGH | Must use `x-core-ui::table-pagination` |
| `text-gray-500` hardcoded (non-token) | MEDIUM | Replace with `text-[var(--ui-text-muted)]` |
| `border-gray-100` hardcoded | MEDIUM | Replace with `border-[var(--ui-border-soft)]` |

### 1.2 forms/admin/forms/builder.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| `AbortController` race prevention — CORRECT | PASS | Phase 9.2 stabilized |
| SortableJS DOM-resync — CORRECT | PASS | Phase 9.2 fixed |
| Confirm modal uses Alpine custom impl | HIGH | Must delegate to `x-core-ui::confirm-modal` |
| Floating panel has inline `style="width:340px"` | HIGH | Must use CSS token var |
| `z-index: 50` hardcoded inline | CRITICAL | Must use `--ui-z-modal` token |
| Font sizes use `text-xs text-sm` raw Tailwind | MEDIUM | Mixed: some tokenized, some not |
| Loading indicator — custom spinner | HIGH | Must use canonical `x-core-ui::loading` |
| Section builder panel lacks mobile collapse | HIGH | Responsive governance failure |
| Undo/redo keyboard shortcut — CORRECT | PASS | Phase 9.2 keyboard help overlay |

### 1.3 forms/admin/forms/submissions/index.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| Sticky header table — needs canonical | HIGH | `position: sticky` not via token |
| Analytics stat cards use custom HTML | HIGH | Must use `x-core-ui::stat-card` |
| Bulk actions toolbar custom implementation | CRITICAL | Must use `x-core-ui::bulk-toolbar` from canonical |
| Filter drawer overlay lacks `--ui-z-modal` | HIGH | z-index drift |
| `bg-white` hardcoded | MEDIUM | Replace with `bg-[var(--ui-surface)]` |
| Empty state custom HTML | HIGH | Must use `x-core-ui::empty-state` |

### 1.4 forms/admin/forms/analytics.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| Chart.js container lacks responsive wrapper | HIGH | No canonical responsive container |
| Stats cards use `analytics-card` (forms-only) | HIGH | Must use canonical `x-core-ui::stat-card` |
| `text-indigo-600` hardcoded | MEDIUM | Replace with `text-[var(--ui-indigo)]` |

### 1.5 forms/site/* views

| Finding | Severity | Detail |
|---------|----------|--------|
| `site/forms/show.blade.php` uses raw form tag | CRITICAL | No canonical form wrapper |
| Submit button is raw `<button>` | HIGH | Must use `x-core-ui::button` |
| Field rendering inline CSS `margin-bottom:16px` | HIGH | Replace with space tokens |
| No loading state during submission | HIGH | Must add `x-core-ui::loading` |

---

## SECTION 2 — TICKETS MODULE AUDIT

### 2.1 tickets/admin/tickets/index.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| Inline `<style>` block present (100+ lines) | CRITICAL | Phase 10.0 partially removed; residual remains |
| Custom filter pills not using core-ui | HIGH | Must use `x-core-ui::badge` for status filters |
| Priority badges hardcoded | HIGH | Must use `x-core-ui::badge` |
| Table header sort not using canonical | HIGH | Must migrate to `x-core-ui::data-table` |
| Bulk checkbox handling — custom | HIGH | Must use data-table bulk-selection prop |
| `box-shadow: 0 2px 8px rgba(0,0,0,.08)` inline | HIGH | Must use `--ui-shadow-sm` |
| Hardcoded `#374151` color | CRITICAL | Non-token color usage |
| No keyboard navigation on table rows | HIGH | Accessibility failure |

### 2.2 tickets/admin/tickets/show.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| Residual inline `<style>` block | CRITICAL | Must be fully eliminated |
| Reply textarea raw HTML | HIGH | Must use `x-core-ui::textarea` |
| Status/Priority dropdowns custom | HIGH | Must use `x-core-ui::dropdown` |
| AI panel wiring custom | MEDIUM | Uses Phase 9.4 enterprise-desk-panel |
| Avatar stack inline SVG styling | MEDIUM | Must use `x-core-ui::realtime-avatar-stack` |
| Timeline events not using activity-timeline | HIGH | Must use canonical component |
| `font-size: 13px` inline | MEDIUM | Replace with `--ui-text-sm` token |

### 2.3 tickets/admin/tickets/settings.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| Form controls raw HTML | CRITICAL | All inputs must use core-ui variants |
| Save button raw `<button>` | HIGH | Must use `x-core-ui::button` |
| No loading state | HIGH | Missing async save indicator |

### 2.4 tickets/site/* views

| Finding | Severity | Detail |
|---------|----------|--------|
| Create form raw HTML | CRITICAL | Not using core-ui form controls |
| File upload raw input | CRITICAL | No canonical file upload component |
| Status display raw span | HIGH | Must use `x-core-ui::badge` |
| No skeleton loading | HIGH | Page load state missing |

---

## SECTION 3 — ADMIN LAYOUT AUDIT

### 3.1 resources/views/layouts/admin.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| Sidebar uses custom HTML (not core-ui components) | CRITICAL | Must use `x-core-ui::navigation-item` |
| Navbar hardcoded `h-16` | HIGH | Must use `--ui-navbar-height` token |
| Sidebar width hardcoded `w-64` (256px) | HIGH | Must use `--ui-sidebar-width` token |
| Mobile sidebar toggle custom Alpine | HIGH | Must use canonical `x-core-ui::sidebar-group` |
| No canonical page transition | MEDIUM | Page loading indicator missing |
| `bg-gray-900` sidebar — token mismatch | HIGH | Must use `--ui-sidebar-bg` |

### 3.2 resources/views/layouts/app.blade.php

| Finding | Severity | Detail |
|---------|----------|--------|
| No canonical token injection at root | CRITICAL | Must include `<x-core-ui::design-tokens />` |
| Flash message system not canonical | HIGH | Must use `x-core-ui::toast` system |
| No global notification hub | HIGH | Must wire `x-core-ui::notification-center` |

---

## SECTION 4 — SHARED COMPONENT DRIFT

### 4.1 Duplicate Component Implementations

| Component | Core-UI | Forms | Tickets | Status |
|-----------|---------|-------|---------|--------|
| empty-state | YES | YES (fork) | NO (custom) | DRIFT |
| modal | NO | YES | NO (inline) | CRITICAL |
| page-header | NO | YES | NO | MISSING |
| toolbar | NO | YES | NO | MISSING |
| badge | NO | YES | NO | CRITICAL |
| alert | NO | YES | NO | MISSING |
| toast | NO | YES | NO | MISSING |
| skeleton | NO | YES (card) | NO | MISSING |
| stat-card | NO | YES | NO | MISSING |
| notification-center | YES | YES (fork) | NO | DRIFT |

### 4.2 Alpine.js Pattern Drift

| Pattern | Forms | Tickets | Status |
|---------|-------|---------|--------|
| Modal open/close | `x-show` + `@keydown.escape` | `onclick` | DRIFT |
| Dropdown | Alpine component | raw JS | CRITICAL |
| Form dirty tracking | Alpine `$watch` | None | DRIFT |
| Toast dismissal | Custom Alpine | None | MISSING |
| Loading overlay | `x-show="loading"` | None | MISSING |

### 4.3 Hardcoded Non-Token Values Found

| Location | Value | Type | Severity |
|----------|-------|------|----------|
| tickets/index.blade.php | `#374151` | Color | CRITICAL |
| tickets/show.blade.php | `font-size: 13px` | Typography | HIGH |
| tickets/show.blade.php | `box-shadow: 0 2px 8px rgba(0,0,0,.08)` | Shadow | HIGH |
| admin.blade.php | `w-64` (sidebar) | Spacing | HIGH |
| admin.blade.php | `h-16` (navbar) | Spacing | HIGH |
| forms/builder.blade.php | `style="width:340px"` | Spacing | HIGH |
| forms/builder.blade.php | `z-index: 50` | Z-index | CRITICAL |
| forms/submissions.blade.php | `bg-white` | Surface | MEDIUM |
| Multiple views | `text-gray-*` | Color | MEDIUM |
| Multiple views | `border-gray-*` | Color | MEDIUM |
| Multiple views | `bg-green-100 text-green-700` | Status color | HIGH |
| Multiple views | `bg-red-100 text-red-700` | Status color | HIGH |

---

## SECTION 5 — ANIMATION & MOTION DRIFT

| Finding | Severity |
|---------|----------|
| Forms module: `transition-all duration-200` raw Tailwind | MEDIUM |
| Tickets module: custom CSS `@keyframes` in inline style block | CRITICAL |
| Builder panel: `transition: all 0.2s ease` inline | HIGH |
| No canonical `prefers-reduced-motion` support | HIGH |
| Animation durations inconsistent: 150ms, 200ms, 300ms mixed | HIGH |

---

## SECTION 6 — RESPONSIVE BEHAVIOR DRIFT

| Finding | Severity |
|---------|----------|
| Forms builder: no mobile layout (3-panel breaks on <768px) | CRITICAL |
| Tickets index: table overflows on mobile without scroll wrapper | HIGH |
| Admin sidebar: no canonical mobile collapse behavior | CRITICAL |
| Forms submissions drawer: not fullscreen on mobile | HIGH |
| Touch targets below 44px minimum in several places | HIGH |
| RTL support untested in tickets module | HIGH |

---

## SECTION 7 — MISSING CANONICAL TOKENS (Phase 10.0 gaps)

| Token Group | Missing Tokens |
|-------------|----------------|
| Layout | `--ui-navbar-height`, `--ui-content-max-width`, `--ui-sidebar-collapsed-width` |
| Interaction | `--ui-transition-fast`, `--ui-transition-slow`, `--ui-hover-bg`, `--ui-active-bg` |
| States | `--ui-disabled-opacity`, `--ui-loading-opacity`, `--ui-skeleton-bg` |
| Table | `--ui-table-row-height`, `--ui-table-header-height`, `--ui-table-density-compact`, `--ui-table-density-default` |
| Forms | `--ui-input-height-sm`, `--ui-input-height-md`, `--ui-input-height-lg` |
| Buttons | `--ui-btn-height-sm`, `--ui-btn-height-md`, `--ui-btn-height-lg` |
| Modal | `--ui-modal-overlay-bg`, `--ui-modal-max-width`, `--ui-modal-z-index` |
| Mobile | `--ui-breakpoint-sm`, `--ui-breakpoint-md`, `--ui-breakpoint-lg`, `--ui-touch-target-min` |
| Blur | `--ui-blur-sm`, `--ui-blur-md`, `--ui-blur-lg` |
| High-density | `--ui-density-compact-factor`, `--ui-density-cozy-factor` |

---

## PHASE 10.1 REMEDIATION PLAN

### Priority 1 — CRITICAL (Blocks Enterprise Release)

1. Expand design-tokens.css with all missing token groups
2. Create canonical `x-core-ui::modal` system (replaces all custom modals)
3. Upgrade `x-core-ui::data-table` to enterprise grade
4. Create canonical page layout components
5. Eliminate inline `<style>` blocks in tickets module
6. Wire `x-core-ui::navigation-item` into admin layout

### Priority 2 — HIGH (Required for Cross-Module Parity)

1. Create `x-core-ui::switch`, `x-core-ui::alert`, `x-core-ui::badge`, `x-core-ui::toast`, `x-core-ui::skeleton`
2. Migrate all status badges to `x-core-ui::badge`
3. Add `prefers-reduced-motion` support to all animations
4. Create FrontendStateGovernanceService for unified loading/error/success states
5. Normalize all form controls in tickets module

### Priority 3 — MEDIUM (Governance Completion)

1. Create FrontendComponentGovernanceService with Artisan validation command
2. Create ResponsiveExperienceValidationService
3. Create EnterpriseFrontendGovernanceValidationService
4. Produce governance documentation suite

---

## DRIFT CLASSIFICATION SUMMARY

| Severity | Count | Percentage |
|----------|-------|------------|
| CRITICAL | 18 | 24% |
| HIGH | 38 | 50% |
| MEDIUM | 20 | 26% |
| **Total** | **76** | 100% |

**Target after Phase 10.1:** Zero CRITICAL, ≤5 HIGH (accepted tech debt), MEDIUM within governance SLA.
