# Phase 9.3 — Cross-Context Runtime Parity Audit
**Date:** 2026-05-27  
**Scope:** saas-platform ↔ customer-site (Forms + Tickets modules)

---

## 1. ARCHITECTURE AUTHORITY MAP

```
CANONICAL UI AUTHORITY:
  packages/saas-platform/modules/forms/resources/views/    → forms:: namespace
  packages/saas-platform/modules/tickets/Resources/views/  → tickets:: namespace

SHARED COMPONENT NAMESPACE:
  packages/saas-platform/modules/forms/resources/views/core-ui/components/
    → Registered as 'core-ui::' in FormsServiceProvider
    → NOT yet registered in TicketsServiceProvider → DRIFT ITEM #1

FORBIDDEN OVERRIDE ZONES:
  {saas-platform,customer-site}/resources/views/admin/forms/   → EMPTY ✓
  {saas-platform,customer-site}/resources/views/site/forms/    → EMPTY ✓
  {saas-platform,customer-site}/resources/views/admin/tickets/ → EMPTY ✓
  {saas-platform,customer-site}/resources/views/site/tickets/  → EMPTY ✓
```

---

## 2. FORMS MODULE PARITY MATRIX

| Feature | saas-platform | customer-site | Parity |
|---------|---------------|---------------|--------|
| Form list (index) | ✓ package | ✓ via package | PASS |
| Form create | ✓ package | ✓ via package | PASS |
| Form builder | ✓ package | ✓ via package | PASS |
| Drag/drop (SortableJS) | ✓ | ✓ (same file) | PASS |
| Autosave + AbortController | ✓ Phase 9.2 | ✓ (same file) | PASS |
| Undo/redo (Ctrl+Z/Y) | ✓ | ✓ (same file) | PASS |
| Submissions index | ✓ package | ✓ via package | PASS |
| Analytics | ✓ package | ✓ via package | PASS |
| Automation UI | ✓ package | ✓ via package | PASS |
| Templates | ✓ package | ✓ via package | PASS |
| Conditional logic | ✓ package | ✓ via package | PASS |
| Draft recovery | ✓ Service exists | ✓ (same service) | PASS |
| Keyboard shortcuts | ✓ Phase 9.2 | ✓ (same file) | PASS |
| Toast system | ✓ core-ui::toast | ✓ (same namespace) | PASS |
| Modal system | ✓ core-ui::modal | ✓ (same namespace) | PASS |
| Confirm modal | ✓ core-ui::confirm-modal | ✓ (same namespace) | PASS |
| Design tokens | ✓ core-ui::design-tokens | ✓ (same namespace) | PASS |
| Version history | ✓ Service exists | ✓ (same service) | PASS |
| @platform/@tenant directives | ✓ Forms SP | ✗ NOT in Tickets SP | **DRIFT** |
| core-ui:: namespace (tickets) | N/A | N/A | **DRIFT** |

**Forms Parity Score: 95/100** (minor: design tokens not injected by default in layout)

---

## 3. TICKETS MODULE PARITY MATRIX

| Feature | saas-platform | customer-site | Parity |
|---------|---------------|---------------|--------|
| Ticket list (admin) | ✓ tickets::admin.tickets.index | ✓ via package | PASS |
| Ticket show (admin) | ✓ tickets::admin.tickets.show | ✓ via package | PASS |
| Ticket create | ✓ package | ✓ via package | PASS |
| Chat UI (show) | ✓ Alpine + bubble CSS | ✓ (same file) | PASS |
| Status changes | ✓ TicketStatusController | ✓ via SupportController | PASS |
| Assignment UI | ✓ TicketAssignmentController | ✓ via SupportController | PASS |
| Internal notes (noteMode) | ✓ Show view | ✓ (same file) | PASS |
| Watchers | ✓ Alpine state | ✓ (same file) | PASS |
| SLA dashboard | ✓ TicketSlaController | ✗ Not in customer-site routes | PARTIAL |
| SMS notifications | ✓ TicketSmsController | ✗ Not exposed to tenant | PARTIAL |
| Bulk operations | ✓ UI state | ✗ No backend handler | MISSING |
| Activity timeline | ✗ No ticket_activities table | ✗ | MISSING |
| Assignment history | ✗ No persistent log | ✗ | MISSING |
| Portal (site) listing | ✓ tickets::site.tickets.index | ✓ via package | PASS |
| Portal ticket show | ✓ tickets::site.tickets.show | ✓ via package | PASS |
| core-ui:: components | ✗ NOT used in tickets views | ✗ | DRIFT |
| Design tokens | ✗ Own CSS vars in views | ✗ | DRIFT |
| @platform/@tenant | ✗ NOT in TicketsServiceProvider | ✗ | DRIFT |
| core-ui:: namespace | ✗ Not registered in TicketsSP | ✗ | DRIFT |

**Tickets Parity Score: 68/100** — significant gaps

---

## 4. DETECTED DRIFT ITEMS

### CRITICAL DRIFTS

| # | Item | Location | Fix |
|---|------|----------|-----|
| D-01 | core-ui:: namespace not in TicketsServiceProvider | TicketsServiceProvider.php | Register namespace in boot() |
| D-02 | @platform/@tenant directives not registered for tickets views | TicketsServiceProvider.php | Register Blade::if() in boot() |
| D-03 | Tickets CSS uses its own variables (`--c-indigo`, etc.) | admin/tickets/index+show | Migrate to --forms-* design tokens |
| D-04 | No ticket_activities table — no persistent timeline | Database | Create migration |
| D-05 | Bulk operations have UI but no backend handler | TicketController | Add bulkAction() endpoint |
| D-06 | Flash messages not using core-ui::toast | All tickets views | Replace inline flash with <x-core-ui::toast /> |

### MEDIUM DRIFTS

| # | Item | Location | Fix |
|---|------|----------|-----|
| D-07 | Status/priority badges duplicated between index/show | tickets views | Use core-ui::badge component |
| D-08 | Assignment history not tracked | TicketAssignmentService | Use TicketActivityService |
| D-09 | SLA dashboard not accessible to tenant-admin | Routes/admin.php | Add tenant gate check |
| D-10 | SMS controller not in customer-site routes | customer-site/routes | Add opt-in route for tenant SMS |
| D-11 | No empty state component in tickets index | tickets/index.blade.php | Add core-ui::empty-state |
| D-12 | No skeleton loaders in tickets list | tickets/index.blade.php | Add loading states |

### LOW DRIFTS

| # | Item | Location | Fix |
|---|------|----------|-----|  
| D-13 | Ticket show: reply form has no draft persistence | site/tickets/show | Add localStorage draft save |
| D-14 | No unread indicator on tickets list | tickets/index | Add unread count tracking |
| D-15 | Customer portal lacks keyboard navigation | site/tickets | Add focus-visible, ARIA |

---

## 5. CUSTOMER-SITE INTEGRATION DETAILS

```
Route prefix mapping:
  package: /admin/tickets/*  → customer-site: /support/* (aliased as tickets.site.*)
  package: /tickets/*        → customer-site: /tickets/* (same prefix)

Controller delegation:
  customer-site uses App\Http\Controllers\Admin\SupportController
  → Methods: index, create, store, show, reply
  → Uses: TicketPortalService + TicketLifecycleService (package services)

View rendering:
  All views rendered via tickets:: namespace
  No forbidden overrides detected ✓

Permissions:
  customer-site: ['permission:support'] middleware on all support routes
  saas-platform: auth + admin access checks
```

---

## 6. REQUIRED REMEDIATION (Phase 9.3)

Priority-ordered fix list:

1. **[P0]** Register `core-ui::` namespace in TicketsServiceProvider
2. **[P0]** Register `@platform`/`@tenant` Blade directives in TicketsServiceProvider
3. **[P0]** Create `ticket_activities` migration + model + service
4. **[P0]** Add bulk operation backend handler to TicketController
5. **[P1]** Replace inline flash messages with `<x-core-ui::toast />`
6. **[P1]** Replace duplicated badge HTML with `<x-core-ui::badge>`
7. **[P1]** Add activity timeline to ticket show view
8. **[P1]** Add full enterprise timeline with all event types
9. **[P2]** Add SLA timer UI to ticket show
10. **[P2]** Add draft persistence to reply textarea
11. **[P2]** Migrate CSS variables to design token system
12. **[P2]** Add empty-state and skeleton to tickets index
