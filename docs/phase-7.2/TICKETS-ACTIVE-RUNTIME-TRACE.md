# PHASE 7.2 — Tickets Active Runtime Trace

**Generated:** 2026-05-25  
**Scope:** saas-platform active route/controller/view/service binding trace  
**Purpose:** Authoritative map of every live request path before canonicalization

---

## 1. Active Route Authority (Before PHASE 7.2)

### 1.1 saas-platform — Admin Panel Routes

| HTTP   | URL Pattern                              | Route Name                          | Controller (Legacy)                        |
|--------|------------------------------------------|-------------------------------------|--------------------------------------------|
| GET    | `/admin/support-tickets`                 | `admin.support-tickets.index`       | `Admin\SupportTicketController@index`      |
| GET    | `/admin/support-tickets/create`          | `admin.support-tickets.create`      | `Admin\SupportTicketController@create`     |
| POST   | `/admin/support-tickets`                 | `admin.support-tickets.store`       | `Admin\SupportTicketController@store`      |
| GET    | `/admin/support-tickets/{id}`            | `admin.support-tickets.show`        | `Admin\SupportTicketController@show`       |
| PATCH  | `/admin/support-tickets/{id}/status`     | `admin.support-tickets.update-status` | `Admin\SupportTicketController@updateStatus` |
| POST   | `/admin/support-tickets/{id}/replies`    | `admin.support-tickets.replies.store` | `Admin\SupportTicketController@storeReply` |
| PATCH  | `/admin/support-tickets/{id}/assign`     | `admin.support-tickets.assign`      | `Admin\SupportTicketController@assign`     |

**Data source:** `support_tickets` table (legacy)  
**View namespace:** `resources/views/admin/support-tickets/` (app-level, NOT package)  
**Defined in:** `routes/web.php` lines 430–433

### 1.2 saas-platform — Customer Portal Routes

| HTTP   | URL Pattern                                   | Route Name                             | Controller (Legacy)                              |
|--------|-----------------------------------------------|----------------------------------------|--------------------------------------------------|
| GET    | `/dashboard/support-tickets`                  | `customer.support-tickets.index`       | `Customer\CustomerSupportTicketController@index` |
| GET    | `/dashboard/support-tickets/create`           | `customer.support-tickets.create`      | `Customer\CustomerSupportTicketController@create`|
| POST   | `/dashboard/support-tickets`                  | `customer.support-tickets.store`       | `Customer\CustomerSupportTicketController@store` |
| GET    | `/dashboard/support-tickets/{id}`             | `customer.support-tickets.show`        | `Customer\CustomerSupportTicketController@show`  |
| POST   | `/dashboard/support-tickets/{id}/replies`     | `customer.support-tickets.replies.store` | `Customer\CustomerSupportTicketController@storeReply` |

**Data source:** `support_tickets` table (legacy, direct Eloquent)  
**View namespace:** `resources/views/admin/support-tickets/` (shared with admin — VIOLATION)  
**Defined in:** `routes/web.php` lines 179–185

### 1.3 saas-platform — API Routes

| HTTP   | URL Pattern                              | Route Name | Controller (Legacy)                |
|--------|------------------------------------------|------------|------------------------------------|
| GET    | `/api/support-tickets`                   | —          | `Api\SupportTicketController@index`|
| POST   | `/api/support-tickets`                   | —          | `Api\SupportTicketController@store`|
| POST   | `/api/support-tickets/{id}/reply`        | —          | `Api\SupportTicketController@reply`|
| GET    | `/api/support-tickets/unread-count`      | —          | `Api\SupportTicketController@unreadCount` |

**Auth mechanism:** `license_key` + `domain` header validation (via `SupportPortalService`)  
**Data source:** `support_tickets` table (via `SupportPortalService`)  
**Consumer:** customer-site `SupportApiClient` (HTTP calls, no direct DB)  
**Defined in:** `routes/api.php` or inline in `routes/web.php`

---

## 2. Canonical Route Authority (PHASE 7.1 — Already Registered)

Canonical routes are loaded by `TicketsServiceProvider::registerRoutes()` via `loadRoutesFrom()`.  
These are live but not yet the primary UI entry point.

### 2.1 Admin Canonical Routes

| HTTP   | URL Pattern                          | Route Name                    | Controller (Canonical)                           |
|--------|--------------------------------------|-------------------------------|--------------------------------------------------|
| GET    | `/admin/tickets`                     | `tickets.admin.index`         | `Admin\TicketController@index`                   |
| GET    | `/admin/tickets/create`              | `tickets.admin.create`        | `Admin\TicketController@create`                  |
| POST   | `/admin/tickets`                     | `tickets.admin.store`         | `Admin\TicketController@store`                   |
| GET    | `/admin/tickets/{id}`                | `tickets.admin.show`          | `Admin\TicketController@show`                    |
| PATCH  | `/admin/tickets/{id}/status`         | `tickets.admin.status.update` | `Admin\TicketStatusController@update`            |
| PATCH  | `/admin/tickets/{id}/assign`         | `tickets.admin.assign`        | `Admin\TicketAssignmentController@update`        |
| PATCH  | `/admin/tickets/{id}/priority`       | `tickets.admin.priority.update` | `Admin\TicketPriorityController@update`        |
| POST   | `/admin/tickets/{id}/replies`        | `tickets.admin.replies.store` | `Admin\TicketReplyController@store`              |
| GET    | `/admin/tickets/sla/dashboard`       | `tickets.admin.sla.index`     | `Admin\TicketSlaController@index`                |

**Data source:** `tickets` table (canonical)  
**View namespace:** `tickets::admin.*` (package-level)

### 2.2 Site Canonical Routes

| HTTP   | URL Pattern              | Route Name                    | Controller (Canonical)                         |
|--------|--------------------------|-------------------------------|------------------------------------------------|
| GET    | `/tickets`               | `tickets.site.index`          | `Site\TicketPortalController@index`            |
| GET    | `/tickets/{id}`          | `tickets.site.show`           | `Site\TicketPortalController@show`             |
| GET    | `/tickets/create`        | `tickets.site.create`         | `Site\TicketSubmissionController@create`       |
| POST   | `/tickets`               | `tickets.site.store`          | `Site\TicketSubmissionController@store`        |
| POST   | `/tickets/{id}/reply`    | `tickets.site.replies.store`  | `Site\TicketSubmissionController@storeReply`   |

**Data source:** `tickets` table (canonical)  
**View namespace:** `tickets::site.*` (package-level)

### 2.3 API Canonical Routes

| HTTP   | URL Pattern                      | Route Name                    | Controller (Canonical)              |
|--------|----------------------------------|-------------------------------|-------------------------------------|
| GET    | `/api/tickets`                   | `tickets.api.index`           | `Api\TicketApiController@index`     |
| POST   | `/api/tickets`                   | `tickets.api.store`           | `Api\TicketApiController@store`     |
| GET    | `/api/tickets/stats`             | `tickets.api.stats`           | `Api\TicketApiController@stats`     |
| GET    | `/api/tickets/{id}`              | `tickets.api.show`            | `Api\TicketApiController@show`      |
| POST   | `/api/tickets/{id}/replies`      | `tickets.api.replies.store`   | `Api\TicketApiController@addReply`  |

**Auth mechanism:** `auth:sanctum`  
**Data source:** `tickets` table (canonical)

---

## 3. Container Binding Trace

### 3.1 Legacy Bindings (app-level)

| Contract / Class              | Implementation                        | Registered In                  |
|-------------------------------|---------------------------------------|--------------------------------|
| `SupportPortalService`        | `App\Services\SupportPortalService`   | `AppServiceProvider` / direct  |
| `SupportTicketRepository`     | `App\Repositories\...`                | `AppServiceProvider` (deprecated) |
| `SupportTicket` (Model)       | `App\Models\SupportTicket`            | Direct Eloquent                |
| `SupportTicketReply` (Model)  | `App\Models\SupportTicketReply`       | Direct Eloquent                |

### 3.2 Canonical Bindings (package-level, from TicketsServiceProvider)

| Contract                          | Implementation                                    |
|-----------------------------------|---------------------------------------------------|
| `TicketServiceInterface`          | `TicketService`                                   |
| `TicketRepositoryInterface`       | `TicketRepository`                                |
| `TicketReplyRepositoryInterface`  | `TicketReplyRepository`                           |
| `TicketStatusRepositoryInterface` | `TicketStatusRepository`                          |
| `TicketSlaRepositoryInterface`    | `TicketSlaRepository`                             |
| `ModuleLifecycleInterface`        | `TicketModuleLifecycle`                           |
| `TicketLifecycleService`          | singleton                                         |
| `TicketAssignmentService`         | singleton                                         |
| `TicketStatusService`             | singleton                                         |
| `TicketPriorityService`           | singleton                                         |
| `TicketSlaService`                | singleton                                         |
| `TicketNotificationService`       | singleton                                         |
| `TicketPortalService`             | singleton                                         |
| `TicketActivityService`           | singleton                                         |
| `TicketGateService`               | singleton                                         |
| `TicketMonitoringService`         | singleton                                         |
| `TicketGateService`               | singleton                                         |
| `TicketCanonicalValidationService`| singleton                                         |

---

## 4. View Authority Trace

### 4.1 Legacy Views (still active before PHASE 7.2)

| View File                                         | Used By                      | Table          | Status         |
|---------------------------------------------------|------------------------------|----------------|----------------|
| `resources/views/admin/support-tickets/index.blade.php`  | `Admin\SupportTicketController@index`  | `support_tickets` | @deprecated (7.15) |
| `resources/views/admin/support-tickets/create.blade.php` | `Admin\SupportTicketController@create` | `support_tickets` | @deprecated (7.15) |
| `resources/views/admin/support-tickets/show.blade.php`   | `Admin\SupportTicketController@show`   | `support_tickets` | @deprecated (7.15) |

### 4.2 Canonical Views (package-level)

| View Reference                          | File                                                              | Table     |
|-----------------------------------------|-------------------------------------------------------------------|-----------|
| `tickets::admin.tickets.index`          | `packages/.../Resources/views/admin/tickets/index.blade.php`     | `tickets` |
| `tickets::admin.tickets.create`         | `packages/.../Resources/views/admin/tickets/create.blade.php`    | `tickets` |
| `tickets::admin.tickets.show`           | `packages/.../Resources/views/admin/tickets/show.blade.php`      | `tickets` |
| `tickets::site.tickets.index`           | `packages/.../Resources/views/site/tickets/index.blade.php`      | `tickets` |
| `tickets::site.tickets.show`            | `packages/.../Resources/views/site/tickets/show.blade.php`       | `tickets` |
| `tickets::site.tickets.create`          | `packages/.../Resources/views/site/tickets/create.blade.php`     | `tickets` |

---

## 5. Middleware Chain Trace

### 5.1 Admin Routes
```
web → auth → admin → AdminSupportTicketController (legacy)
web → auth → (no admin gate!) → Admin\TicketController (canonical — NEEDS ADMIN MIDDLEWARE)
```
**Note:** The canonical admin routes in `Routes/admin.php` use `['web', 'auth']` but NOT the `admin` middleware. This needs to be fixed.

### 5.2 Customer Portal Routes
```
web → auth → EnsureProfileIsComplete → CustomerSupportTicketController (legacy)
web → auth → TicketPortalController / TicketSubmissionController (canonical)
```

### 5.3 API Routes
```
— (no web middleware) → license_key + domain validation → Api\SupportTicketController (legacy)
api → auth:sanctum → TicketApiController (canonical)
```
**Critical:** The legacy API uses a custom auth mechanism (license_key/domain), the canonical uses Sanctum. These cannot be directly swapped without updating the customer-site `SupportApiClient`.

---

## 6. customer-site Integration Trace

customer-site has **no local ticket database**. All ticket operations go through HTTP:

```
customer-site Admin\SupportController
    └── SupportApiClient (HTTP client)
            └── POST/GET /api/support-tickets on saas-platform
                    └── Api\SupportTicketController
                            └── SupportPortalService
                                    └── support_tickets table
```

**Implication:** `/api/support-tickets` MUST remain active until `SupportApiClient` is updated to call `/api/tickets`. This is the primary backward-compatibility constraint for PHASE 7.2.

---

## 7. PHASE 7.2 Migration Targets

| Component                             | Action                              | Risk    |
|---------------------------------------|-------------------------------------|---------|
| Admin web routes (GET)                | 301 redirect → `/admin/tickets/*`   | Low     |
| Admin web routes (POST/PATCH)         | Redirect to canonical controllers   | Medium  |
| Customer portal routes (GET)          | 301 redirect → `/tickets/*`         | Low     |
| Customer portal routes (POST)         | Redirect to canonical controllers   | Medium  |
| `/api/support-tickets` routes         | Keep active (bridge in TASK 6)      | Low     |
| Legacy view files                     | Move to `_legacy/` subdirectory     | Low     |
| `Admin\SupportTicketController`       | Removed from route declarations     | Medium  |
| `Customer\CustomerSupportTicketController` | Removed from route declarations | Medium  |
| `SupportApiClient` in customer-site   | Update to `/api/tickets` (deferred) | High    |
