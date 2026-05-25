# Tickets Module — Canonical Authority Decision (PHASE 7.0 TASK 2)

**Date:** 2026-05-24  
**Author:** AI Architecture Audit  
**Status:** ✅ FORMAL DECISION — Implementation B declared Canonical Authority

---

## 1. DECLARATION

**Implementation B (saas-platform project-level)** is hereby declared the **Canonical Authority** for the Tickets/Support module.

All future extraction, migration, and standardization efforts SHALL reference Implementation B as the single source of truth for:
- Data schema (model fields, migration structure)
- Service architecture (Repository + Contract + Service pattern)
- Validation approach (FormRequest classes with Persian messages)
- Business logic (licensing, SLA, activity logging)
- API contracts (endpoint structure, payload format)
- View patterns (layout, data access, formatting)

---

## 2. DECISION RATIONALE

### 2.1 Feature Completeness Score

| # | Feature | A: Package | B: Project | C: customer-site |
|---|---------|:----------:|:----------:|:----------------:|
| 1 | Repository Pattern | ❌ | ✅ | N/A |
| 2 | Service Interface/Contract | ❌ | ✅ | N/A |
| 3 | Repository Interface/Contract | ❌ | ✅ | N/A |
| 4 | FormRequest Validation | ❌ (inline) | ✅ | ✅ |
| 5 | Activity Logging | ❌ | ✅ | N/A |
| 6 | SupportLicenseService | ❌ | ✅ | N/A |
| 7 | SupportSlaService | ❌ | ✅ | N/A |
| 8 | SupportPortalService (API) | ❌ | ✅ | N/A |
| 9 | API Controller | ❌ | ✅ | N/A |
| 10 | Customer Controller | ❌ | ✅ | N/A |
| 11 | API FormRequests | ❌ | ✅ | N/A |
| 12 | Customer FormRequests | ❌ | ✅ | N/A |
| 13 | Chat UI (Alpine.js) | ❌ (simple list) | ✅ | ✅ |
| 14 | SLA Display in Views | ❌ | ✅ | ❌ (hardcoded 0) |
| 15 | Assign Control in Views | ❌ | ✅ | ❌ |
| 16 | Status Control (dropdown) | ❌ (buttons) | ✅ | ❌ |
| 17 | Settings Page | ❌ | ✅ | ❌ |
| 18 | Guide Include | ❌ | ✅ | ✅ |
| 19 | Module Features Component | ❌ | ✅ | ✅ |
| 20 | Jalali Date Formatting | ❌ (Gregorian) | ✅ | ✅ |
| 21 | NumberHelper Formatting | ❌ (number_format) | ✅ | ✅ |
| 22 | Cache Layer | ❌ | ✅ | N/A |
| 23 | Site Column in Table | ❌ | ✅ | ✅ |
| 24 | Assignee Display | ❌ | ✅ | ❌ |
| 25 | Support Level Field | ❌ | ✅ | N/A |
| 26 | Tags Field | ❌ | ✅ | ✅ |
| 27 | Category Select (predefined) | ❌ (text input) | ✅ | ✅ |
| 28 | Exception Class | ❌ | ✅ | N/A |
| 29 | SLA Breach Stats | ❌ | ✅ | ❌ |
| 30 | Business Hours Logic | ❌ | ✅ | N/A |
| | **TOTAL** | **0/30** | **30/30** | **10/30** |

**Score: B (30/30) > C (10/30) > A (0/30)**

### 2.2 Architecture Quality Assessment

| Criterion | A: Package | B: Project | C: customer-site |
|-----------|:----------:|:----------:|:----------------:|
| **Pattern** | Direct queries in Service | Repository + Contract + Service | API proxy (no local DB) |
| **Validation** | Inline `$request->validate()` | Dedicated FormRequest classes | Dedicated FormRequest classes |
| **Error Handling** | Basic exception | `SupportPortalException` + Persian messages | Session flash messages |
| **Caching** | None | Cache with TTL 900s + clearCache() | N/A |
| **Activity Logging** | None | `ActivityLogger::log()` on all mutations | N/A |
| **Licensing** | None | `SupportLicenseService` (basic/pro/enterprise) | `License::current()` model |
| **SLA** | None | `SupportSlaService` with business hours | Hardcoded to 0 |
| **API Layer** | None | Full REST API with license validation | HTTP client consuming the API |
| **Customer Portal** | None | Full customer controller with site scoping | N/A |
| **File Uploads** | `tickets/` path | `support-tickets/` path | `support-tickets/` path |
| **Date Format** | Gregorian `format()` | `Jalali::format()` | `Jalali::formatFa()` |
| **Number Format** | `number_format()` | `NumberHelper::formatFa()` | `NumberHelper::formatFa()` |
| **View Layout** | `@extends('admin.layout')` | `@extends('layouts.admin')` | `@extends('admin.layout')` |
| **Data Access** | Eloquent `$ticket->field` | Eloquent `$supportTicket->field` | Array `$ticket['field']` |
| **Missing Use Statements** | ⚠️ `LengthAwarePaginator`, `ValidationException` | ✅ All present | N/A |

### 2.3 Production Readiness

| Criterion | A: Package | B: Project |
|-----------|:----------:|:----------:|
| **Tested in Production** | ❌ Unknown | ✅ Yes |
| **Error Handling** | ❌ Basic | ✅ Comprehensive with Persian messages |
| **Security** | ❌ No FormRequest validation | ✅ FormRequest with authorization |
| **Performance** | ❌ No caching | ✅ Cache layer with TTL |
| **Audit Trail** | ❌ No logging | ✅ ActivityLogger integration |
| **SLA Compliance** | ❌ Not implemented | ✅ Full SLA tracking with business hours |
| **License Enforcement** | ❌ Not implemented | ✅ SupportLicenseService integration |

---

## 3. CANONICAL AUTHORITY BOUNDARIES

### 3.1 What Implementation B Defines as Canonical

| Domain | Canonical Source | File(s) |
|--------|-----------------|---------|
| **Ticket Model Schema** | `App\Models\SupportTicket` | `app/Models/SupportTicket.php` |
| **Reply Model Schema** | `App\Models\SupportTicketReply` | `app/Models/SupportTicketReply.php` |
| **Service Interface** | `SupportTicketServiceInterface` | `app/Contracts/Services/SupportTicketServiceInterface.php` |
| **Repository Interface** | `SupportTicketRepositoryInterface` | `app/Contracts/Repositories/SupportTicketRepositoryInterface.php` |
| **Reply Repository Interface** | `SupportTicketReplyRepositoryInterface` | `app/Contracts/Repositories/SupportTicketReplyRepositoryInterface.php` |
| **Service Implementation** | `SupportTicketService` | `app/Services/SupportTicketService.php` |
| **Repository Implementation** | `SupportTicketRepository` | `app/Repositories/SupportTicketRepository.php` |
| **Reply Repository Implementation** | `SupportTicketReplyRepository` | `app/Repositories/SupportTicketReplyRepository.php` |
| **Admin Controller** | `SupportTicketController` | `app/Http/Controllers/Admin/SupportTicketController.php` |
| **API Controller** | `Api\SupportTicketController` | `app/Http/Controllers/Api/SupportTicketController.php` |
| **Customer Controller** | `CustomerSupportTicketController` | `app/Http/Controllers/Customer/CustomerSupportTicketController.php` |
| **Admin FormRequests** | 4 FormRequest classes | `app/Http/Requests/Admin/*SupportTicket*` |
| **API FormRequests** | 3 FormRequest classes | `app/Http/Requests/Api/*SupportTicket*` |
| **Customer FormRequests** | 2 FormRequest classes | `app/Http/Requests/Customer/*SupportTicket*` |
| **License Service** | `SupportLicenseService` | `app/Services/SupportLicenseService.php` |
| **SLA Service** | `SupportSlaService` | `app/Services/SupportSlaService.php` |
| **Portal Service** | `SupportPortalService` | `app/Services/SupportPortalService.php` |
| **Exception** | `SupportPortalException` | `app/Exceptions/SupportPortalException.php` |
| **Admin Views** | 5 Blade templates | `resources/views/admin/support-tickets/*` |
| **Customer Views** | 4 Blade templates | `resources/views/customer/support-tickets/*` |
| **Admin Routes** | web.php | `routes/web.php` (admin.support-tickets.*) |
| **API Routes** | api.php | `routes/api.php` (api.support-tickets.*) |
| **Customer Routes** | web.php | `routes/web.php` (customer.support-tickets.*) |

### 3.2 What Implementation A (Package) Must Become

The package module (`packages/saas-platform/modules/tickets/`) must be **rewritten** to match Implementation B's architecture, with the following adaptations:

1. **Namespace**: `SaaSPlatform\Modules\Tickets\*` (keep package namespace)
2. **Models**: Match `SupportTicket` → `Ticket`, `SupportTicketReply` → `TicketReply` with canonical fields
3. **Contracts**: Extract interfaces from B into package `Contracts/` directory
4. **Services**: Extract service implementations from B into package `Services/` directory
5. **Repositories**: Extract repository implementations from B into package `Repositories/` directory
6. **Controllers**: Extract controllers from B into package `Http/Controllers/` directory
7. **FormRequests**: Extract FormRequests from B into package `Http/Requests/` directory
8. **Views**: Extract views from B into package `Resources/views/` directory
9. **Routes**: Extract route definitions from B into package `Routes/` directory
10. **Migrations**: Update migrations to match canonical schema (add `support_level`, rename `description` → `message`)

### 3.3 What customer-site (C) Must Become

The customer-site must continue using the **API proxy pattern** but consume from the **canonical package**:

1. **API Client**: Keep `SupportApiClient` but update endpoints to match canonical API
2. **Field Names**: Align with canonical: `customer_email` (not `email`), `customer_name` (not `name`)
3. **Views**: Update to use canonical layout reference and data access patterns
4. **SLA Display**: Remove hardcoded 0, use API response data
5. **Status/Assign Controls**: Add if API exposes them

---

## 4. CANONICAL FIELD MAPPING

### 4.1 Ticket Model — Canonical Schema

| Field | Type | Source (B) | Package (A) | Resolution |
|-------|------|:----------:|:-----------:|:----------:|
| `id` | bigint, auto-increment | ✅ | ✅ | Keep |
| `ticket_number` | string, unique | ✅ | ✅ | Keep |
| `subject` | string | ✅ | ✅ | Keep |
| **`message`** | text | ✅ `message` | ❌ `description` | **RENAME to `message`** |
| `status` | string, default 'open' | ✅ | ✅ | Keep |
| `priority` | string, default 'normal' | ✅ | ✅ | Keep |
| **`support_level`** | string, nullable | ✅ | ❌ | **ADD to package** |
| `customer_id` | FK→users, nullable | ❌ | ✅ | **REMOVE from package** (not in canonical) |
| `assigned_to` | FK→users, nullable | ✅ | ✅ | Keep |
| `site_id` | FK→sites, nullable | ✅ | ✅ | Keep |
| `customer_email` | string, nullable | ✅ | ✅ | Keep |
| `customer_name` | string, nullable | ✅ | ✅ | Keep |
| `category` | string, nullable | ✅ | ✅ | Keep |
| `tags` | text, nullable | ✅ | ✅ | Keep |
| `attachments` | json, nullable | ✅ | ✅ | Keep |
| `closed_at` | timestamp, nullable | ✅ | ✅ | Keep |
| `first_response_at` | timestamp, nullable | ✅ | ✅ | Keep |
| `resolved_at` | timestamp, nullable | ✅ | ✅ | Keep |
| `created_at` | timestamp | ✅ | ✅ | Keep |
| `updated_at` | timestamp | ✅ | ✅ | Keep |
| `deleted_at` | soft-deletes | ✅ | ✅ | Keep |

### 4.2 Reply Model — Canonical Schema

| Field | Type | Source (B) | Package (A) | Resolution |
|-------|------|:----------:|:-----------:|:----------:|
| `id` | bigint, auto-increment | ✅ | ✅ | Keep |
| **FK field** | FK | `support_ticket_id` | `ticket_id` | **RENAME to `ticket_id`** (package wins) |
| `user_id` | FK→users, nullable | ✅ | ✅ | Keep |
| `message` | text | ✅ | ✅ | Keep |
| `author_type` | string, default 'staff' | ✅ | ✅ | Keep |
| `customer_email` | string, nullable | ✅ | ✅ | Keep |
| `customer_name` | string, nullable | ✅ | ✅ | Keep |
| `attachments` | json, nullable | ✅ | ✅ | Keep |
| `created_at` | timestamp | ✅ | ✅ | Keep |
| `updated_at` | timestamp | ✅ | ✅ | Keep |
| `deleted_at` | soft-deletes | ✅ | ✅ | Keep |

**Decision on FK field name:** Use `ticket_id` (package convention) as it's more concise and follows Laravel convention (`ticket()` relation on `ticket_id`). The canonical model will use `ticket_id` and the `ticket()` belongsTo relation.

---

## 5. CANONICAL CONTRACT SIGNATURES

### 5.1 Service Interface (Canonical)

```php
interface TicketServiceInterface {
    public function getAll(array $filters = []): LengthAwarePaginator;
    public function getById(int $id): ?Ticket;
    public function getBySiteId(int $siteId): Collection;
    public function getByIdForSite(int $id, int $siteId): ?Ticket;
    public function countUnreadBySiteId(int $siteId): int;
    public function create(array $data, ?array $files = null): Ticket;
    public function updateStatus(Ticket $ticket, string $status): Ticket;
    public function assignTo(Ticket $ticket, ?int $userId): Ticket;
    public function addReply(Ticket $ticket, array $data, ?array $files = null): Ticket;
    public function getStats(): array;
}
```

### 5.2 Repository Interface (Canonical)

```php
interface TicketRepositoryInterface {
    public function search(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 20): LengthAwarePaginator;
    public function findById(int $id, array $relations = []): ?Ticket;
    public function findByTicketNumber(string $ticketNumber): ?Ticket;
    public function findByIdForSite(int $id, int $siteId, array $relations = []): ?Ticket;
    public function getBySiteId(int $siteId, array $relations = []): Collection;
    public function countUnreadBySiteId(int $siteId): int;
    public function create(array $data): Ticket;
    public function update(Ticket $ticket, array $data): Ticket;
    public function getStats(): array;
}
```

### 5.3 Reply Repository Interface (Canonical)

```php
interface TicketReplyRepositoryInterface {
    public function getByTicketId(int $ticketId): Collection;
    public function create(array $data): TicketReply;
}
```

---

## 6. CANONICAL ROUTE STRUCTURE

### 6.1 Admin Routes (Canonical)

| Method | URI | Name | Controller Method |
|--------|-----|------|-------------------|
| GET | `admin/tickets` | `admin.tickets.index` | `index` |
| GET | `admin/tickets/create` | `admin.tickets.create` | `create` |
| POST | `admin/tickets` | `admin.tickets.store` | `store` |
| GET | `admin/tickets/{ticket}` | `admin.tickets.show` | `show` |
| PATCH | `admin/tickets/{ticket}/status` | `admin.tickets.update-status` | `updateStatus` |
| POST | `admin/tickets/{ticket}/replies` | `admin.tickets.replies.store` | `storeReply` |
| PATCH | `admin/tickets/{ticket}/assign` | `admin.tickets.assign` | `assign` |

**Decision:** Use `admin/tickets` prefix (package convention) instead of `admin/support-tickets` (project convention). The package route prefix is shorter and more canonical.

### 6.2 API Routes (Canonical)

| Method | URI | Name | Controller Method |
|--------|-----|------|-------------------|
| GET | `api/support-tickets` | `api.support-tickets.index` | `index` |
| POST | `api/support-tickets` | `api.support-tickets.store` | `store` |
| POST | `api/support-tickets/{id}/reply` | `api.support-tickets.reply` | `reply` |
| GET | `api/support-tickets/unread-count` | `api.support-tickets.unread-count` | `unreadCount` |

### 6.3 Customer Routes (Canonical)

| Method | URI | Name | Controller Method |
|--------|-----|------|-------------------|
| GET | `dashboard/support-tickets` | `customer.support-tickets.index` | `index` |
| GET | `dashboard/support-tickets/create` | `customer.support-tickets.create` | `create` |
| POST | `dashboard/support-tickets` | `customer.support-tickets.store` | `store` |
| GET | `dashboard/support-tickets/{ticket}` | `customer.support-tickets.show` | `show` |
| POST | `dashboard/support-tickets/{ticket}/replies` | `customer.support-tickets.replies.store` | `storeReply` |

---

## 7. CANONICAL VIEW ARCHITECTURE

### 7.1 Layout Reference

**Canonical:** `@extends('admin.layout')` (package convention, used by both A and C)

The project-level `@extends('layouts.admin')` must be updated to `@extends('admin.layout')` when views are extracted into the package.

### 7.2 View Namespace

**Canonical:** `tickets::*` (package view namespace)

### 7.3 View Files (Canonical)

| View | Purpose | Key Features |
|------|---------|-------------|
| `tickets::index` | Ticket list | 5 stat cards (total, open, in_progress, closed, sla_breach), filter bar, table with site/assignee columns, pagination |
| `tickets::create` | Create ticket | site_id select, subject, message, priority, customer_email, customer_name, category, tags, attachments |
| `tickets::show` | Ticket detail | Chat UI (2/3 + 1/3 grid), sidebar with info/status/assign/SLA, reply form, Alpine.js supportChat() |
| `tickets::settings` | Settings | Module settings (from B) |
| `tickets::guide` | Guide | Module guide component |

### 7.4 CSS Approach

**Canonical:** Tailwind CSS classes + `@asset_module('tickets', 'css/tickets.css')` for module-specific styles.  
No inline `<style>` blocks in canonical views.

### 7.5 Date & Number Formatting

**Canonical:** `Jalali::format()` for dates, `NumberHelper::formatFa()` for numbers.

---

## 8. CANONICAL SERVICE ARCHITECTURE

### 8.1 Dependency Graph

```
SupportTicketController (Admin)
    → SupportTicketService (implements SupportTicketServiceInterface)
        → SupportTicketRepository (implements SupportTicketRepositoryInterface)
            → SupportTicket (Model)
        → SupportTicketReplyRepository (implements SupportTicketReplyRepositoryInterface)
            → SupportTicketReply (Model)
        → SupportLicenseService
            → Site (Model)
        → ActivityLogger

Api\SupportTicketController
    → SupportPortalService
        → LicenseServerService
        → SupportLicenseService
        → SupportTicketService

CustomerSupportTicketController
    → SupportTicketService
```

### 8.2 Canonical Package Structure

```
packages/saas-platform/modules/tickets/
├── config/
│   └── tickets.php              # Canonical config
├── Database/
│   └── Migrations/
│       ├── 2026_05_23_000001_create_tickets_table.php
│       └── 2026_05_23_000002_create_ticket_replies_table.php
├── Resources/
│   ├── assets/
│   │   └── css/
│   │       └── tickets.css
│   └── views/
│       ├── index.blade.php
│       ├── create.blade.php
│       ├── show.blade.php
│       ├── settings.blade.php
│       └── guide.blade.php
├── Routes/
│   ├── web.php                  # Admin + Customer routes
│   └── api.php                  # API routes
├── src/
│   ├── Contracts/
│   │   ├── Services/
│   │   │   └── TicketServiceInterface.php
│   │   └── Repositories/
│   │       ├── TicketRepositoryInterface.php
│   │       └── TicketReplyRepositoryInterface.php
│   ├── Exceptions/
│   │   └── TicketPortalException.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── TicketsController.php          # Admin
│   │   │   ├── ApiTicketController.php        # API
│   │   │   └── CustomerTicketController.php   # Customer
│   │   └── Requests/
│   │       ├── Admin/
│   │       │   ├── StoreTicketRequest.php
│   │       │   ├── StoreTicketReplyRequest.php
│   │       │   ├── UpdateTicketStatusRequest.php
│   │       │   └── AssignTicketRequest.php
│   │       ├── Api/
│   │       │   ├── StoreTicketApiRequest.php
│   │       │   ├── StoreTicketReplyApiRequest.php
│   │       │   └── IndexTicketsRequest.php
│   │       └── Customer/
│   │           ├── StoreCustomerTicketRequest.php
│   │           └── StoreCustomerReplyRequest.php
│   ├── Models/
│   │   ├── Ticket.php
│   │   └── TicketReply.php
│   ├── Providers/
│   │   └── TicketsServiceProvider.php
│   ├── Repositories/
│   │   ├── TicketRepository.php
│   │   └── TicketReplyRepository.php
│   └── Services/
│       ├── TicketService.php
│       ├── TicketLicenseService.php
│       ├── TicketSlaService.php
│       └── TicketPortalService.php
└── module-manifest.php
```

---

## 9. DECISION ENFORCEMENT RULES

### Rule 1: No New Divergence
All new tickets/support features MUST be implemented first in the package module (A), then consumed by both projects.

### Rule 2: Project-Level Code is Temporary
All code in `app/Models/SupportTicket*`, `app/Services/Support*`, `app/Repositories/Support*`, `app/Contracts/Support*`, `app/Http/Controllers/*/Support*`, `app/Http/Requests/*/Support*`, `resources/views/admin/support-tickets/*`, `resources/views/customer/support-tickets/*` is **temporary** and will be replaced by package code.

### Rule 3: customer-site Consumes API
The customer-site MUST continue using the API proxy pattern. It MUST NOT create local ticket tables or models.

### Rule 4: Field Name Canonicalization
- `message` (not `description`) — ticket body field
- `ticket_id` (not `support_ticket_id`) — reply FK field
- `customer_email` (not `email`) — customer email field
- `customer_name` (not `name`) — customer name field

### Rule 5: Layout Canonicalization
All ticket views MUST use `@extends('admin.layout')` (not `@extends('layouts.admin')`).

### Rule 6: Route Canonicalization
- Admin prefix: `admin/tickets` (not `admin/support-tickets` or `admin/support`)
- Route names: `admin.tickets.*` (not `admin.support-tickets.*` or `admin.support.*`)

---

## 10. NEXT STEPS (TASKS 3-10)

| Task | Description | Depends On |
|------|-------------|-----------|
| **TASK 3** | Shared Runtime Mapping — Map all runtime dependencies | ✅ This decision |
| **TASK 4** | Tickets Frontend Canonicalization — Standardize views | TASK 3 |
| **TASK 5** | Shared Contract Extraction — Extract interfaces into package | TASK 3 |
| **TASK 6** | Tenant & Licensing Mapping — Map licensing/SLA to package | TASK 5 |
| **TASK 7** | Tickets Lifecycle Mapping — Map lifecycle events | TASK 5 |
| **TASK 8** | Drift Elimination Plan — Step-by-step plan | TASKS 3-7 |
| **TASK 9** | Migration Risk Assessment — Identify risks | TASK 8 |
| **TASK 10** | Canonical Tickets Architecture — Final document | TASKS 8-9 |

---

## 11. SIGN-OFF

**Canonical Authority:** Implementation B (saas-platform project-level)  
**Location:** `c:\laragon\www\saas-platform\app\*` (SupportTicket* files)  
**Decision Date:** 2026-05-24  
**Status:** ✅ FORMALIZED — Ready for TASK 3: Shared Runtime Mapping

---

*End of Canonical Authority Decision — Ready for TASK 3: Shared Runtime Mapping*
