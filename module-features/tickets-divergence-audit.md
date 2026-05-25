# Tickets Module — Comprehensive Divergence Audit (PHASE 7.0 TASK 1)

**Date:** 2026-05-24  
**Author:** AI Architecture Audit  
**Scope:** All three implementations across both projects

---

## 1. EXECUTIVE SUMMARY

The Tickets/Support functionality exists in **three completely different implementations** across the workspace:

| # | Implementation | Location | Architecture | Status |
|---|---------------|----------|-------------|--------|
| A | **Package Module** | `packages/saas-platform/modules/tickets/` | Basic package, no lifecycle, no contracts | ⚠️ Incomplete |
| B | **saas-platform Project** | `app/Models/SupportTicket*`, `app/Services/Support*`, etc. | Full-featured, repository pattern, contracts, SLA, licensing | ✅ Reference |
| C | **customer-site Project** | `c:\laragon\www\customer-site\app/...` | API client-based, no local DB, proxies to platform API | ⚠️ Divergent |

**Key Finding:** Implementation B (saas-platform project-level) is the most complete and should serve as the **canonical reference** for extracting shared contracts into the package module (Implementation A).

---

## 2. MODEL DIVERGENCE

### 2.1 Ticket Model

| Aspect | A: Package (Ticket) | B: Project (SupportTicket) | C: customer-site |
|--------|-------------------|---------------------------|-----------------|
| **Class** | `SaaSPlatform\Modules\Tickets\Models\Ticket` | `App\Models\SupportTicket` | N/A (no local model) |
| **Namespace** | Package namespace | App namespace | — |
| **Traits** | `HasFactory, SoftDeletes` | `HasFactory, SoftDeletes` | — |
| **Field: description** | ✅ `description` | ❌ Uses `message` instead | — |
| **Field: message** | ❌ Not present | ✅ `message` | — |
| **Field: support_level** | ❌ Not present | ✅ `support_level` | — |
| **Field: customer_id** | ✅ `customer_id` | ❌ Not present | — |
| **Ticket Number Gen** | In Service (`generateTicketNumber()`) | In Model booted (`static::creating`) | — |
| **Ticket Number Prefix** | Config: `ST` | Hardcoded: `ST-` | — |
| **Relations** | `customer()`, `assignee()`, `site()`, `replies()` | `site()`, `assignee()`, `replies()` | — |
| **Constants** | `STATUS_*`, `PRIORITY_*` | Same constants | — |
| **SoftDeletes** | ✅ | ✅ | — |

### 2.2 Reply Model

| Aspect | A: Package (TicketReply) | B: Project (SupportTicketReply) | C: customer-site |
|--------|-------------------------|-------------------------------|-----------------|
| **Class** | `SaaSPlatform\Modules\Tickets\Models\TicketReply` | `App\Models\SupportTicketReply` | — |
| **FK Field** | `ticket_id` | `support_ticket_id` | — |
| **Relation Name** | `ticket()` | `ticket()` | — |
| **Constants** | `AUTHOR_STAFF`, `AUTHOR_CUSTOMER` | Same | — |
| **isFromStaff()** | ✅ | ✅ | — |
| **Fillable** | `ticket_id, user_id, message, author_type, customer_email, customer_name, attachments` | `support_ticket_id, user_id, message, author_type, customer_email, customer_name, attachments` | — |

---

## 3. SERVICE LAYER DIVERGENCE

### 3.1 Ticket Service

| Aspect | A: Package (TicketService) | B: Project (SupportTicketService) |
|--------|---------------------------|-----------------------------------|
| **Implements Interface** | ❌ No | ✅ `SupportTicketServiceInterface` |
| **Uses Repository** | ❌ Direct DB queries | ✅ `SupportTicketRepositoryInterface` |
| **Uses Reply Repository** | ❌ Direct model calls | ✅ `SupportTicketReplyRepositoryInterface` |
| **Uses License Service** | ❌ No | ✅ `SupportLicenseService` |
| **Activity Logging** | ❌ No | ✅ `ActivityLogger::log()` |
| **Missing Use Statements** | ⚠️ `LengthAwarePaginator`, `ValidationException` | ✅ All present |
| **File Upload Path** | `tickets/` | `support-tickets/` |
| **getAll()** | Direct query with filters | Delegates to repository `search()` |
| **getById()** | Direct query | Delegates to repository `findById()` |
| **create()** | Direct `Ticket::create()` | Delegates to repository, sets `support_level` |
| **updateStatus()** | Direct update | Delegates to repository, logs activity |
| **assignTo()** | Direct update | Delegates to repository, logs activity |
| **addReply()** | Direct `TicketReply::create()` | Delegates to reply repository, logs activity |
| **getStats()** | Direct queries | Delegates to repository (cached) |
| **getBySiteId()** | ❌ Not present | ✅ Present |
| **getByIdForSite()** | ❌ Not present | ✅ Present |
| **countUnreadBySiteId()** | ❌ Not present | ✅ Present |

### 3.2 Additional Services (B only — not in A)

| Service | Purpose | Lines |
|---------|---------|-------|
| `SupportLicenseService` | License level management (basic/pro/enterprise), grace period, eligibility | 119 |
| `SupportSlaService` | SLA breach detection, business hours calculation, response/resolution deadlines | 167 |
| `SupportPortalService` | API portal logic, license validation, ticket creation via API | 120 |
| `SupportApiClient` (customer-site) | HTTP client to platform API, file upload proxy | 132 |

---

## 4. CONTROLLER DIVERGENCE

### 4.1 Admin Controllers

| Aspect | A: Package (TicketsController) | B: Project (SupportTicketController) |
|--------|-------------------------------|--------------------------------------|
| **Namespace** | `SaaSPlatform\Modules\Tickets\Http\Controllers` | `App\Http\Controllers\Admin` |
| **FormRequests** | ❌ Inline `$request->validate()` | ✅ Separate FormRequest classes |
| **TenantContext** | ✅ Uses `TenantContext::hasFeature()` | ❌ Not used |
| **Site Query** | `Site::where('is_active', true)->get()` | Same pattern |
| **View Namespace** | `tickets::index` | `admin.support-tickets.index` |
| **Route Names** | `admin.tickets.*` | `admin.support-tickets.*` |
| **Methods** | `index, create, store, show, updateStatus, storeReply, assign` | Same 7 methods |

### 4.2 API Controller (B only)

| Aspect | B: Project (Api/SupportTicketController) |
|--------|------------------------------------------|
| **Namespace** | `App\Http\Controllers\Api` |
| **Service** | `SupportPortalService` |
| **Endpoints** | `index`, `store`, `reply`, `unreadCount` |
| **Auth** | License key + domain validation |
| **Error Handling** | `SupportPortalException` → JSON error response |
| **Payload Mapping** | `ticketPayload()`, `replyPayload()` |

### 4.3 Customer Controller (B only)

| Aspect | B: Project (CustomerSupportTicketController) |
|--------|----------------------------------------------|
| **Namespace** | `App\Http\Controllers\Customer` |
| **Access Control** | `CustomerMenu::isVisible('support-tickets')` |
| **Site Scoping** | Only customer's own sites via `$customer->sites()->pluck('id')` |
| **Methods** | `index, create, store, show, storeReply` |

### 4.4 Customer-site Controller (C)

| Aspect | C: customer-site (SupportController) |
|--------|--------------------------------------|
| **Namespace** | `App\Http\Controllers\Admin` |
| **Service** | `SupportApiClient` (HTTP proxy) |
| **Local DB** | ❌ No local ticket data |
| **License** | Uses `License::current()` model |
| **Methods** | `create, show, index, store, history, reply` |
| **Error Handling** | Session flash messages |
| **View Namespace** | `admin.support.*` |

---

## 5. FORM REQUEST DIVERGENCE

### 5.1 Store Ticket Requests

| Aspect | B: Admin (StoreSupportTicketRequest) | C: customer-site (StoreSupportRequest) |
|--------|--------------------------------------|----------------------------------------|
| **site_id** | `required\|exists:sites,id` | ❌ Not present (uses License) |
| **subject** | `required\|string\|max:255` | Same |
| **message** | `required\|string\|max:5000` | Same |
| **customer_email** | `required\|email` | Uses `email` field name |
| **customer_name** | `nullable\|string\|max:255` | Uses `name` field name |
| **priority** | `nullable\|in:low,normal,high` | Same |
| **category** | `nullable\|string\|max:50` | Same |
| **tags** | `nullable\|string\|max:500` | Same |
| **attachments** | `nullable\|array` | `nullable\|array\|max:5` |
| **attachments.*** | `file\|mimes:...\|max:10240` | Same |
| **Persian Messages** | ✅ | ✅ |

### 5.2 API Requests (B only)

| Request | Fields |
|---------|--------|
| `StoreSupportTicketApiRequest` | `license_key, domain, subject, message, customer_email, customer_name, priority, category, tags, attachments.*.name/path/url/size/mime` |
| `StoreSupportTicketReplyApiRequest` | `license_key, domain, message, customer_name, attachments.*` |
| `IndexSupportTicketsRequest` | `license_key, domain, status, per_page` |

### 5.3 Customer Request (B only)

| Request | Fields |
|---------|--------|
| `StoreCustomerSupportTicketRequest` | `site_id (in: customer's sites), subject, message, priority, category, tags, attachments.*` |

---

## 6. VIEW DIVERGENCE

### 6.1 Layout Reference

| Aspect | A: Package Views | B: Project Views | C: customer-site Views |
|--------|-----------------|------------------|----------------------|
| **Layout** | `@extends('admin.layout')` | `@extends('layouts.admin')` | `@extends('admin.layout')` |
| **View Path** | `tickets::index/show/create` | `admin.support-tickets.*` | `admin.support.*` |
| **CSS Approach** | Tailwind classes + `@asset_module` | Inline `<style>` blocks + glass classes | Inline `<style>` blocks |
| **Stat Cards** | 4 cards (total, open, in_progress, closed) | 5 cards (adds SLA breach) | 5 cards (SLA hardcoded to 0) |
| **Data Access** | Eloquent `$ticket->field` | Eloquent `$supportTicket->field` | Array `$ticket['field']` |
| **Date Format** | `$ticket->created_at->format('Y/m/d H:i')` | `Jalali::format($t->created_at)` | `Jalali::formatFa(Carbon::parse(...))` |
| **Number Format** | `number_format()` | `NumberHelper::formatFa()` | `NumberHelper::formatFa()` |
| **SLA Display** | ❌ Not present | ✅ SLA breach count + per-row indicator | ❌ Hardcoded to 0 |
| **Site Column** | ❌ Not in table | ✅ Site column with name | ✅ Site column (from `$t['site_name']`) |
| **Assignee** | ❌ Not shown | ✅ Shown in table row | ❌ Not shown |
| **Chat UI** | ❌ Simple reply list | ✅ Full chat UI with Alpine.js `supportChat()` | ✅ Similar chat UI |
| **Chat Colors** | N/A | From `Setting` model | Hardcoded defaults |
| **Settings Page** | ❌ Not present | ✅ `admin.support-tickets.settings` | ❌ Not present |
| **Guide Include** | ❌ Not present | ✅ `@include('admin.support-tickets.guide')` | ✅ `@include('admin.support.guide')` |
| **Module Features** | ❌ Not present | ✅ `<x-module-features module="support" />` | ✅ `<x-module-features module="support" />` |
| **Pagination** | `$tickets->links()` | `$tickets->links()` | `method_exists($tickets, 'links') ? ... : ''` |

### 6.2 Create Form Differences

| Aspect | A: Package | B: Project | C: customer-site |
|--------|-----------|------------|-----------------|
| **site_id field** | ✅ Present | ✅ Present | ❌ Not present (uses License) |
| **customer_email** | ✅ `customer_email` | ✅ `customer_email` | Uses `email` field |
| **customer_name** | ✅ `customer_name` | ✅ `customer_name` | Uses `name` field |
| **tags field** | ❌ Not present | ✅ Present | ✅ Present |
| **Category select** | ❌ Text input | ✅ Select with options | ✅ Select with options |
| **Attachments max** | No limit | No limit | No limit |

### 6.3 Show View Differences

| Aspect | A: Package | B: Project | C: customer-site |
|--------|-----------|------------|-----------------|
| **Layout** | Simple card layout | Full chat UI (2/3 + 1/3 grid) | Full chat UI (2/3 + 1/3 grid) |
| **Sidebar** | Simple info grid | Full sidebar: info, status control, assign control, quick actions | Sidebar: info, quick actions only |
| **Status Control** | Buttons (start/close) | Select dropdown + save | ❌ Not present |
| **Assign Control** | ❌ Not present | ✅ User select + assign button | ❌ Not present |
| **SLA Display** | ❌ Not present | ✅ SLA breach badge + deadlines | ❌ Not present |
| **Support Level** | ❌ Not present | ✅ Displayed in sidebar | ❌ Not present |
| **Settings Link** | ❌ Not present | ✅ Link to settings page | ❌ Not present |
| **Alpine.js Chat** | ❌ Simple form | ✅ `supportChat()` with scroll, loading states | ✅ `supportChat()` with scroll, loading states |

---

## 7. ROUTE DIVERGENCE

### 7.1 Admin Routes

| Route | A: Package | B: Project | C: customer-site |
|-------|-----------|------------|-----------------|
| **Prefix** | `admin/tickets` | `admin/support-tickets` | `admin/support` |
| **Name Prefix** | `admin.tickets.*` | `admin.support-tickets.*` | `admin.support.*` |
| **Resource** | `index, create, store, show` | `index, create, store, show` | Custom routes |
| **updateStatus** | `PATCH tickets/{ticket}/status` | `PATCH support-tickets/{support_ticket}/status` | ❌ Not present |
| **storeReply** | `POST tickets/{ticket}/replies` | `POST support-tickets/{support_ticket}/replies` | `POST support/{ticketId}/reply` |
| **assign** | `PATCH tickets/{ticket}/assign` | `PATCH support-tickets/{support_ticket}/assign` | ❌ Not present |
| **history** | ❌ Not present | ❌ Not present | ✅ `GET support/history` |
| **settings** | ❌ Not present | ✅ `GET support-tickets/settings` | ❌ Not present |

### 7.2 API Routes (B only)

| Route | Method | Endpoint |
|-------|--------|----------|
| `api.support-tickets.store` | POST | `/api/support-tickets` |
| `api.support-tickets.index` | GET | `/api/support-tickets` |
| `api.support-tickets.reply` | POST | `/api/support-tickets/{id}/reply` |
| `api.support-tickets.unread-count` | GET | `/api/support-tickets/unread-count` |

### 7.3 Customer Routes (B only)

| Route | Method | Endpoint |
|-------|--------|----------|
| `customer.support-tickets.index` | GET | `/dashboard/support-tickets` |
| `customer.support-tickets.create` | GET | `/dashboard/support-tickets/create` |
| `customer.support-tickets.store` | POST | `/dashboard/support-tickets` |
| `customer.support-tickets.show` | GET | `/dashboard/support-tickets/{support_ticket}` |
| `customer.support-tickets.replies.store` | POST | `/dashboard/support-tickets/{support_ticket}/replies` |

---

## 8. REPOSITORY DIVERGENCE

### 8.1 Ticket Repository (B only)

| Aspect | B: Project (SupportTicketRepository) |
|--------|--------------------------------------|
| **Interface** | `SupportTicketRepositoryInterface` |
| **Cache** | ✅ Cache with TTL 900s |
| **Search** | Allowed sorts, escapeLikeTerm, date range filters |
| **Extra Filters** | `site_ids` (array), `assigned_to`, `created_from`, `created_to` |
| **Stats** | Cached: total, open, in_progress, closed, high_priority, new_this_month |
| **clearCache()** | ✅ On create/update |

### 8.2 Reply Repository (B only)

| Aspect | B: Project (SupportTicketReplyRepository) |
|--------|-------------------------------------------|
| **Interface** | `SupportTicketReplyRepositoryInterface` |
| **Methods** | `getByTicketId()`, `create()` |
| **Cache Clear** | On create |

---

## 9. CONTRACT/INTERFACE DIVERGENCE

### 9.1 Service Interface (B only)

```php
interface SupportTicketServiceInterface {
    getAll(array $filters): LengthAwarePaginator
    getById(int $id): ?SupportTicket
    getBySiteId(int $siteId): Collection
    getByIdForSite(int $id, int $siteId): ?SupportTicket
    countUnreadBySiteId(int $siteId): int
    create(array $data, ?array $files): SupportTicket
    updateStatus(SupportTicket $ticket, string $status): SupportTicket
    assignTo(SupportTicket $ticket, ?int $userId): SupportTicket
    addReply(SupportTicket $ticket, array $data, ?array $files): SupportTicket
    getStats(): array
}
```

### 9.2 Repository Interface (B only)

```php
interface SupportTicketRepositoryInterface {
    search(array $filters, string $sortBy, string $sortDir, int $perPage): LengthAwarePaginator
    findById(int $id, array $relations): ?SupportTicket
    findByTicketNumber(string $ticketNumber): ?SupportTicket
    findByIdForSite(int $id, int $siteId, array $relations): ?SupportTicket
    getBySiteId(int $siteId, array $relations): Collection
    countUnreadBySiteId(int $siteId): int
    create(array $data): SupportTicket
    update(SupportTicket $ticket, array $data): SupportTicket
    getStats(): array
}
```

---

## 10. LICENSING & SLA DIVERGENCE

### 10.1 SupportLicenseService (B only)

| Feature | Implementation |
|---------|---------------|
| **Levels** | `basic`, `pro`, `enterprise` |
| **Grace Period** | Configurable via `config('support.grace_days', 7)` |
| **Methods** | `isEligibleForUpdates()`, `getEffectiveSupportEnd()`, `isInGracePeriod()`, `getStatus()`, `getLevel()`, `canAccessSupport()` |
| **Integration** | Used by `SupportTicketService::create()` to set `support_level` |

### 10.2 SupportSlaService (B only)

| Feature | Implementation |
|---------|---------------|
| **Response Hours** | Per-level from config (`support.sla.{level}.response_hours`) |
| **Resolution Hours** | Per-level from config (`support.sla.{level}.resolution_hours`) |
| **Business Hours** | Configurable weekdays, timezone, start/end time |
| **Methods** | `getResponseHours()`, `getResolutionHours()`, `isBusinessHoursEnabled()`, `addBusinessHours()`, `isResponseBreached()`, `isResolutionBreached()`, `hasAnyBreach()`, `getResponseDeadline()`, `getResolutionDeadline()` |

---

## 11. FILE UPLOAD DIVERGENCE

| Aspect | A: Package | B: Project | C: customer-site |
|--------|-----------|------------|-----------------|
| **Upload Path** | `tickets/Y/m` | `support-tickets/Y/m` | `support-tickets/Y/m` |
| **Disk** | `public` | `public` | `public` |
| **Attachment Struct** | `{name, path, size, mime}` | Same | `{name, path, url, size, mime}` |
| **Max Files** | No limit | No limit | No limit (client-side only) |

---

## 12. MISSING FEATURES IN PACKAGE MODULE (A)

The following features exist in Implementation B but are **completely absent** from the Package Module (A):

| # | Feature | Present in B | Present in A |
|---|---------|-------------|-------------|
| 1 | Repository Pattern | ✅ | ❌ |
| 2 | Service Interface/Contract | ✅ | ❌ |
| 3 | Repository Interface/Contract | ✅ | ❌ |
| 4 | FormRequest Validation | ✅ | ❌ (inline) |
| 5 | Activity Logging | ✅ | ❌ |
| 6 | SupportLicenseService | ✅ | ❌ |
| 7 | SupportSlaService | ✅ | ❌ |
| 8 | SupportPortalService (API) | ✅ | ❌ |
| 9 | API Controller | ✅ | ❌ |
| 10 | Customer Controller | ✅ | ❌ |
| 11 | API FormRequests | ✅ | ❌ |
| 12 | Customer FormRequests | ✅ | ❌ |
| 13 | Chat UI (Alpine.js) | ✅ | ❌ (simple list) |
| 14 | SLA Display in Views | ✅ | ❌ |
| 15 | Assign Control in Views | ✅ | ❌ |
| 16 | Status Control (dropdown) | ✅ | ❌ (buttons) |
| 17 | Settings Page | ✅ | ❌ |
| 18 | Guide Include | ✅ | ❌ |
| 19 | Module Features Component | ✅ | ❌ |
| 20 | Jalali Date Formatting | ✅ | ❌ (Gregorian) |
| 21 | NumberHelper Formatting | ✅ | ❌ (number_format) |
| 22 | Cache Layer | ✅ | ❌ |
| 23 | Site Column in Table | ✅ | ❌ |
| 24 | Assignee Display | ✅ | ❌ |
| 25 | Support Level Field | ✅ | ❌ |
| 26 | Tags Field | ✅ | ❌ |
| 27 | Category Select (predefined) | ✅ | ❌ (text input) |
| 28 | Exception Class | ✅ | ❌ |
| 29 | SLA Breach Stats | ✅ | ❌ |
| 30 | Business Hours Logic | ✅ | ❌ |

---

## 13. CUSTOMER-SITE SPECIFIC DIVERGENCE (C)

The customer-site implementation has unique characteristics:

| Aspect | Detail |
|--------|--------|
| **Architecture** | API client proxy — no local ticket DB |
| **License Model** | Uses `License::current()` instead of `site_id` |
| **Data Format** | Array-based (from API JSON), not Eloquent |
| **Field Names** | `email` instead of `customer_email`, `name` instead of `customer_name` |
| **No site_id** | Create form has no site selector (uses License) |
| **No Assign** | No assign functionality (API doesn't expose it) |
| **No Status Change** | No status update controls |
| **No SLA** | SLA breach count hardcoded to 0 |
| **Chat Colors** | Hardcoded defaults (no Setting model) |
| **History Page** | Has a separate `history` view (unique to C) |
| **Pagination** | Guarded with `method_exists` check |
| **Alpine.js Filter** | Client-side filtering (x-show) instead of server-side |

---

## 14. DIVERGENCE SEVERITY MATRIX

| Area | Severity | Impact | Notes |
|------|----------|--------|-------|
| Model Fields | 🔴 HIGH | Data loss on migration | `description` vs `message`, missing `support_level` |
| Service Layer | 🔴 HIGH | Architecture mismatch | Direct queries vs Repository pattern |
| Validation | 🟡 MEDIUM | Inconsistent UX | Inline vs FormRequest |
| Views | 🟡 MEDIUM | UI inconsistency | Layout, date format, number format |
| Routes | 🟡 MEDIUM | URL/name collision risk | Different prefixes and names |
| Licensing | 🔴 HIGH | Missing business logic | No support_level, no SLA in package |
| File Uploads | 🟢 LOW | Path difference | Easy to reconcile |
| API Layer | 🔴 HIGH | Missing entirely in package | Required for customer-site |
| Customer Portal | 🔴 HIGH | Missing entirely in package | Required for customer-site |
| Caching | 🟡 MEDIUM | Performance impact | No cache in package |

---

## 15. RECOMMENDED CANONICAL AUTHORITY

**Implementation B (saas-platform project-level)** should be the **canonical reference** because:

1. ✅ Most complete feature set (29/30 features)
2. ✅ Proper architecture (Repository + Contract + Service)
3. ✅ FormRequest validation with Persian messages
4. ✅ Activity logging
5. ✅ SLA and licensing integration
6. ✅ API layer for customer-site consumption
7. ✅ Customer portal
8. ✅ Production-tested

**Migration Strategy:** Extract contracts, services, and patterns from B into the Package Module (A), then have both projects consume from the package.

---

*End of Divergence Audit — Ready for TASK 2: Canonical Authority Decision*
