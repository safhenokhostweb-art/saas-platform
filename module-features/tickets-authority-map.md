# Tickets Module — Canonical Authority Map

**PHASE 7.1 — TASK 2**
**Date:** 2026-05-25
**Status:** ENFORCED

---

## Authority Hierarchy

```
PACKAGE (saas-platform/modules-tickets)
         │
         ├── OWNS: business logic, repositories, services, contracts,
         │         routes, views, policies, notifications, events,
         │         lifecycle, update mechanics, distribution
         │
SAAS-PLATFORM (project)
         │
         ├── OWNS: platform administration, tenant assignment,
         │         billing linkage, module licensing, global monitoring
         │
CUSTOMER-SITE (tenant project)
         │
         └── OWNS: tenant runtime, customer branding,
                   customer overrides, tenant-specific configs
```

---

## Package Ownership — ABSOLUTE

The package owns these unconditionally. No project-level override of logic allowed:

### Business Logic
- Ticket state machine (open → in_progress → closed)
- Reply workflow (customer reply, staff reply, auto-promote)
- Ticket number generation (ST-YYYY-XXXXX)
- SLA calculation logic
- Assignment rules
- Priority escalation rules

### Repositories (data access)
- `TicketRepository` — all DB queries for tickets
- `TicketReplyRepository` — all DB queries for replies
- `TicketStatusRepository` — status history
- `TicketPriorityRepository` — priority management
- `TicketSlaRepository` — SLA tracking data

### Services (business operations)
- `TicketService` — core CRUD
- `TicketLifecycleService` — state machine
- `TicketAssignmentService` — assignment
- `TicketStatusService` — status transitions
- `TicketPriorityService` — priority management
- `TicketSlaService` — SLA computation
- `TicketNotificationService` — notification dispatch
- `TicketPortalService` — customer-facing operations
- `TicketActivityService` — activity logging
- `TicketMonitoringService` — monitoring/alerts
- `TicketGateService` — permission resolution
- `TicketUpdateValidationService` — pre-update safety
- `TicketRuntimeSafetyService` — runtime checks
- `TicketOverrideSafetyService` — override preservation
- `TicketCanonicalValidationService` — architecture validation

### Contracts (interfaces)
- All `Contracts/` interfaces are authoritative
- Customer-site MAY implement interfaces, MUST NOT bypass them

### Routes
- `/admin/tickets` prefix = admin management (platform + admin users)
- `/tickets` prefix = customer portal (tenant users)
- `/api/tickets` prefix = API (authenticated, scoped)

### Views
- `resources/views/admin/tickets/` — canonical admin views
- `resources/views/site/tickets/` — canonical site/customer views
- `resources/views/components/` — shared components

### Policies
- `TicketPolicy` — who can view/edit tickets
- `TicketReplyPolicy` — who can reply
- `TicketAdminPolicy` — platform admin capabilities

### Migrations
- `tickets` table
- `ticket_replies` table
- Schema version tracked in `module.json`

### Lifecycle
- Install, activate, deactivate, update, rollback, uninstall
- All via `TicketModuleLifecycle`

---

## SaaS-Platform Project Ownership

The platform project (saas-platform) owns:

### Platform Administration
- Module assignment to tenants (`site_modules` table)
- Module licensing controls
- Feature flag management per tenant
- Cross-site ticket monitoring (platform admin view)
- SLA configuration per support plan

### Billing Linkage
- Support plan → ticket limits
- Support level determination (via `SupportLicenseService`)
- Invoice generation for support hours

### Module Distribution
- `module:release tickets` pipeline
- Satis repository publication
- Release manifest generation

---

## Customer-Site Ownership

The customer project (customer-site) owns:

### Tenant Runtime
- Config overrides via `vendor:publish --tag=tickets-config`
- View overrides via `vendor:publish --tag=tickets-views`
- Custom branding in published views
- Chat color settings (via `config/tickets.php` published override)

### Customer Customisation Points
```php
// Customer-site can override in their published config:
'chat' => [
    'customer_bg'   => '#your-color',
    'customer_text' => '#your-color',
    'staff_bg'      => '#your-color',
    'staff_text'    => '#your-color',
],
```

### What Customer-Site CANNOT Do
- ❌ Bypass `TicketGateService` permission checks
- ❌ Access tickets from other sites (enforced by site_id scoping)
- ❌ Override the ticket state machine
- ❌ Add platform-level features to tenant views

---

## Forbidden Patterns

These patterns are NEVER allowed:

| Pattern | Reason |
|---------|--------|
| Direct `Ticket::all()` in controllers | Bypasses tenant scoping |
| `DB::table('tickets')` in app code | Bypasses repository |
| Hardcoded `@extends('layouts.admin')` in views | Breaks authority |
| Ticket logic in `app/Services/` | Duplicates canonical package |
| Platform routes in customer-site | Authority violation |
| Customer portal routes in admin controllers | Segregation violation |

---

## View Authority Rules

### Admin Views (`resources/views/admin/tickets/`)
- ✅ May use `@platform` directive
- ✅ May use `@central` directive
- ✅ May show cross-site data
- ❌ Must NOT expose tenant management UI
- ❌ Must NOT expose billing controls

### Site Views (`resources/views/site/tickets/`)
- ✅ May use `@tenant` directive
- ❌ Must NOT use `@platform` directive
- ❌ Must NOT show tickets from other sites
- ❌ Must NOT show admin-only actions (assign, monitor)

### Component Views (`resources/views/components/`)
- ✅ Shared between admin and site
- ✅ Context-agnostic (no hardcoded admin/tenant logic)
- ✅ Receive context via props

---

## Enforcement Mechanisms

| Mechanism | Tool |
|-----------|------|
| View authority | `module:build tickets` |
| Tenant leakage | `drift:authority` |
| Duplicate logic | `drift:blades`, `drift:controllers` |
| Contract compliance | `drift:contracts` |
| Overall authority | `platform:validate-authority` |
| Distribution readiness | `module:release tickets --dry-run` |
