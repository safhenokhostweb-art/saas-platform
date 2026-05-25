# Tickets Module — Complete Divergence Audit

**PHASE 7.1 — TASK 1**
**Date:** 2026-05-25
**Auditor:** Canonical Migration System
**Status:** MIGRATION IN PROGRESS

---

## Executive Summary

The Tickets module exists in THREE concurrent states:

| State | Location | Status |
|-------|----------|--------|
| Canonical Package (v1.0.0) | `packages/saas-platform/modules/tickets/` | Foundation ready — needs full canonicalization |
| Legacy Project Implementation | `app/` (saas-platform) | Mature, feature-rich — being deprecated |
| Customer-Site Consumer | `c:\laragon\www\customer-site` | Consumes package directly — no local implementation |

---

## Ownership Map

### Package Canonical (Source of Truth)
```
packages/saas-platform/modules/tickets/
├── src/Models/Ticket.php               ← CANONICAL
├── src/Models/TicketReply.php          ← CANONICAL
├── src/Services/TicketService.php      ← CANONICAL (base)
├── src/Repositories/TicketRepository.php ← CANONICAL
├── src/Contracts/...                   ← CANONICAL
├── Database/Migrations/tickets*.php    ← CANONICAL (table: tickets)
└── Resources/views/                    ← CANONICAL (being expanded)
```

### Legacy Project (Deprecated)
```
app/Models/SupportTicket.php            ← DEPRECATED → uses support_tickets table
app/Models/SupportTicketReply.php       ← DEPRECATED
app/Services/SupportTicketService.php   ← DEPRECATED (has SLA, activity extras)
app/Repositories/SupportTicketRepository.php ← DEPRECATED
app/Http/Controllers/Admin/SupportTicketController.php ← DEPRECATED
app/Http/Controllers/Customer/CustomerSupportTicketController.php ← DEPRECATED
resources/views/admin/support-tickets/  ← DEPRECATED → rich chat UI
resources/views/customer/support-tickets/ ← DEPRECATED
```

---

## Divergence Matrix

### 1. Database Schema

| Field | Package (tickets) | Legacy (support_tickets) | Resolution |
|-------|-------------------|--------------------------|------------|
| Table | `tickets` | `support_tickets` | **tickets** (canonical) |
| Body field | `description` | `message` | **description** (canonical) |
| Reply FK | `ticket_id` | `support_ticket_id` | **ticket_id** (canonical) |
| Reply table | `ticket_replies` | `support_ticket_replies` | **ticket_replies** (canonical) |
| `site_id` | ✅ | ✅ | Match |
| `ticket_number` | ✅ | ✅ | Match |
| `status` | ✅ | ✅ | Match |
| `priority` | ✅ | ✅ | Match |
| `assigned_to` | ✅ | ✅ | Match |
| `customer_id` | ✅ | ❌ missing | **Package adds** |
| `category` | ✅ | ✅ (added later) | Match |
| `tags` | ✅ | ✅ (added later) | Match |
| `attachments` | ✅ (json) | ✅ (json) | Match |
| `support_level` | ❌ | ✅ | **Feature extraction needed** |
| Soft deletes | ✅ | ✅ | Match |
| File path | `tickets/Y/m/` | `support-tickets/Y/m/` | **tickets/** (canonical) |

**Drift Severity: HIGH** — table names and body field differ.

### 2. Controllers

| Controller | Package | Legacy | Gap |
|-----------|---------|--------|-----|
| Admin CRUD | `TicketsController` (monolith) | `Admin/SupportTicketController` (separate) | Package needs split |
| Admin Reply | bundled in TicketsController | separate method | **Extract TicketReplyController** |
| Admin Assignment | bundled | separate method | **Extract TicketAssignmentController** |
| Admin Status | bundled | separate method | **Extract TicketStatusController** |
| Admin Priority | ❌ missing | ❌ missing | **Create TicketPriorityController** |
| Admin SLA | ❌ missing | ❌ missing | **Create TicketSlaController** |
| Customer Portal | ❌ missing | `Customer/CustomerSupportTicketController` | **Create Site/TicketPortalController** |
| API | ❌ missing | `Api/SupportTicketController` | **Create Api/TicketApiController** |

**Drift Severity: HIGH** — package has monolithic controller, legacy has rich split architecture.

### 3. Services

| Service | Package | Legacy | Gap |
|---------|---------|--------|-----|
| Core CRUD | ✅ TicketService | ✅ SupportTicketService | Package is canonical |
| Lifecycle | ❌ | ❌ | **Create TicketLifecycleService** |
| Assignment | ❌ | bundled | **Extract TicketAssignmentService** |
| Status engine | ❌ | bundled | **Extract TicketStatusService** |
| Priority engine | ❌ | ❌ | **Create TicketPriorityService** |
| SLA tracking | ❌ | via SlaService | **Create TicketSlaService** |
| Notifications | ❌ | ❌ | **Create TicketNotificationService** |
| Customer portal | ❌ | bundled | **Create TicketPortalService** |
| Activity logging | ❌ | via activityLog() | **Create TicketActivityService** |
| Monitoring | ❌ | ❌ | **Create TicketMonitoringService** |
| Gate/permissions | ❌ | via CustomerMenu | **Create TicketGateService** |
| Update validation | ❌ | ❌ | **Create TicketUpdateValidationService** |
| Runtime safety | ❌ | ❌ | **Create TicketRuntimeSafetyService** |
| Override safety | ❌ | ❌ | **Create TicketOverrideSafetyService** |
| Canonical validation | ❌ | ❌ | **Create TicketCanonicalValidationService** |

**Drift Severity: CRITICAL** — missing 14 services that Forms module has.

### 4. Repositories

| Repository | Package | Legacy | Gap |
|-----------|---------|--------|-----|
| Base interface | ❌ | ❌ | **Create BaseTicketRepositoryInterface** |
| TicketRepository | ✅ (basic) | ✅ (rich: cache, filters) | **Enhance with caching, escapeLike** |
| TicketReplyRepository | ✅ (minimal) | ✅ (basic) | Match |
| StatusRepository | ❌ | ❌ | **Create** |
| PriorityRepository | ❌ | ❌ | **Create** |
| SlaRepository | ❌ | ❌ | **Create** |

**Drift Severity: MEDIUM**

### 5. Routes

| Route | Package | Legacy (saas-platform) | Customer-Site |
|-------|---------|----------------------|---------------|
| Admin list | `GET /admin/tickets` | `GET /admin/support-tickets` | Via package |
| Admin show | `GET /admin/tickets/{id}` | `GET /admin/support-tickets/{id}` | Via package |
| Admin create | `GET /admin/tickets/create` | similar | Via package |
| Admin store | `POST /admin/tickets` | similar | Via package |
| Status update | `PATCH /admin/tickets/{id}/status` | similar | Via package |
| Reply | `POST /admin/tickets/{id}/replies` | similar | Via package |
| Assign | `PATCH /admin/tickets/{id}/assign` | similar | Via package |
| Customer portal | ❌ | `GET /dashboard/support-tickets` | Via package |
| API endpoints | ❌ | `/api/support-tickets` | Via package |

**Resolution:** Package adds `routes/site.php` and `routes/api.php`.

### 6. Views

| View | Package | Legacy | Quality |
|------|---------|--------|---------|
| Admin index | `Resources/views/index.blade.php` (basic) | `admin/support-tickets/index.blade.php` (rich, SLA, Persian) | **Legacy is richer** |
| Admin show | `Resources/views/show.blade.php` (basic) | `admin/support-tickets/show.blade.php` (chat UI, Alpine.js) | **Legacy is richer** |
| Admin create | `Resources/views/create.blade.php` | similar | Similar |
| Customer index | ❌ | `customer/support-tickets/index.blade.php` | **Extract to package** |
| Customer show | ❌ | `customer/support-tickets/show.blade.php` | **Extract to package** |
| Components | ❌ | ❌ | **Create** |

**Resolution:** Build full canonical views in package, archive legacy with `_legacy/` prefix.

### 7. Features Not in Package

| Feature | Legacy Has | Package Needs |
|---------|-----------|---------------|
| SLA metrics | ✅ (via feature flag) | Add to TicketSlaService |
| Activity logging | ✅ (activityLog()) | Add to TicketActivityService |
| Repository caching | ✅ (Cache::remember, 900s TTL) | Add to TicketRepository |
| Customer menu integration | ✅ (CustomerMenu::isVisible) | Abstract to TicketGateService |
| Chat color settings | ✅ (Setting::get) | Config-driven |
| Persian number formatting | ✅ (NumberHelper::formatFa) | Views use it |
| Jalali date formatting | ✅ (Jalali::format) | Views use it |
| Support level tracking | ✅ (support_level field) | Via TicketSlaService |
| High priority count in stats | ✅ | Add to TicketRepository::getStats() |
| Full text search | ✅ (LIKE on multiple fields) | Already in TicketRepository |

---

## Canonical Authority Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Source of truth | Package module | Single distribution unit |
| Table name | `tickets` | Cleaner, no prefix needed |
| Body field | `description` | Standard Laravel convention |
| Reply FK | `ticket_id` | Clean FK naming |
| File storage path | `tickets/Y/m/` | Consistent with module name |
| Route prefix (admin) | `/admin/tickets` | No legacy prefix |
| Route prefix (site) | `/tickets` | Clean tenant-facing URL |
| Controller structure | Split (9 controllers) | SRP compliance |
| View location | `resources/views/admin/tickets/` | Nested admin/site split |

---

## Safe Migration Order

```
1. [SAFE] Create new service layer (additive — no breaking changes)
2. [SAFE] Create new controllers (additive — new routes)
3. [SAFE] Create new routes (additive — doesn't replace legacy routes)
4. [SAFE] Create canonical views (additive — package views)
5. [SAFE] Update TicketsServiceProvider to register new services
6. [MEDIUM RISK] Update module.json schema_version
7. [SAFE] Mark legacy controllers @deprecated
8. [SAFE] Mark legacy services @deprecated
9. [DEFERRED] Delete legacy code (after full validation)
```

---

## Risky Areas

| Area | Risk | Severity | Mitigation |
|------|------|----------|------------|
| `description` vs `message` field | Data access failure | HIGH | Legacy models stay, package uses `description` |
| `tickets` vs `support_tickets` | Route confusion | HIGH | Both routes kept during transition |
| SLA service dependency | Missing on customer-site | MEDIUM | TicketSlaService is optional/feature-flagged |
| Customer portal auth | Wrong tenant scope | HIGH | TicketPortalController enforces site_id scope |
| Reply file paths | Storage drift | LOW | Config-driven path |

---

## Rollback Triggers

- ❌ Customer-site fails to load ticket views → rollback TicketsServiceProvider changes
- ❌ Admin ticket list returns 500 → check DB table name, revert route changes
- ❌ Existing tickets invisible → check `tickets` vs `support_tickets` table
- ❌ Customer portal shows cross-tenant tickets → immediate rollback
- ❌ File attachments 404 → check storage path config

---

## Current Phase: 7.1 Implementation

Migration is NOW IN PROGRESS. All new code is additive. Legacy code is not deleted.
