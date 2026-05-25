# TASK 10: Canonical Tickets Architecture

## Objective
Produce the final canonical architecture document for the Tickets module. This document synthesizes all findings from Tasks 1-9 into a single authoritative architecture reference.

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    CANONICAL TICKETS MODULE                   │
│         packages/saas-platform/modules/tickets/src/           │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                    CONTRACTS                          │    │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────┐   │    │
│  │  │TicketService │  │TicketRepo    │  │TicketReply│   │    │
│  │  │Interface     │  │Interface     │  │RepoInterf.│   │    │
│  │  └──────┬───────┘  └──────┬───────┘  └─────┬────┘   │    │
│  │  ┌──────┴───────┐  ┌──────┴───────┐         │        │    │
│  │  │TicketsLicense│  │TicketsSla    │         │        │    │
│  │  │Interface     │  │Interface     │         │        │    │
│  │  └──────────────┘  └──────────────┘         │        │    │
│  │  ┌──────────────┐  ┌──────────────┐         │        │    │
│  │  │TicketsPortal │  │LicenseValid  │         │        │    │
│  │  │Interface     │  │Interface     │         │        │    │
│  │  └──────────────┘  └──────────────┘         │        │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   IMPLEMENTATIONS                      │    │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────┐   │    │
│  │  │TicketService │  │TicketRepo    │  │TicketReply│   │    │
│  │  │              │  │              │  │Repo       │   │    │
│  │  └──────┬───────┘  └──────┬───────┘  └─────┬────┘   │    │
│  │  ┌──────┴───────┐  ┌──────┴───────┐         │        │    │
│  │  │TicketsLicense│  │TicketsSla    │         │        │    │
│  │  │Service       │  │Service       │         │        │    │
│  │  └──────────────┘  └──────────────┘         │        │    │
│  │  ┌──────────────┐  ┌──────────────────┐     │        │    │
│  │  │TicketsPortal │  │TicketsModule     │     │        │    │
│  │  │Service       │  │LifecycleService  │     │        │    │
│  │  └──────────────┘  └──────────────────┘     │        │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                     MODELS                             │    │
│  │  ┌─────────────────────┐  ┌────────────────────────┐ │    │
│  │  │      Ticket         │  │      TicketReply        │ │    │
│  │  │  - SoftDeletes      │  │  - SoftDeletes          │ │    │
│  │  │  - HasUuid          │  │  - HasUuid              │ │    │
│  │  │  - HasUserStamps    │  │  - HasUserStamps        │ │    │
│  │  │  - fillable         │  │  - fillable             │ │    │
│  │  │  - casts            │  │  - casts                │ │    │
│  │  │  - relations        │  │  - relations            │ │    │
│  │  └─────────────────────┘  └────────────────────────┘ │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   EXCEPTIONS                           │    │
│  │  ┌────────────────────────────────────────────────┐   │    │
│  │  │            TicketPortalException                │   │    │
│  │  │  - publicError(): string                        │   │    │
│  │  │  - publicMessage(): string                      │   │    │
│  │  │  - httpStatus(): int                            │   │    │
│  │  └────────────────────────────────────────────────┘   │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐    │
│  │              SERVICE PROVIDER                          │    │
│  │  TicketsServiceProvider                                │    │
│  │  - register(): bind all contracts                     │    │
│  │  - boot(): routes, views, migrations, config, assets  │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                    CONFIG                              │    │
│  │  config/tickets.php                                    │    │
│  │  - statuses, priorities, pagination                    │    │
│  │  - ticket_number_prefix                                │    │
│  │  - SLA config (response_hours, resolution_hours)       │    │
│  │  - business_hours (timezone, weekdays, start, end)     │    │
│  │  - grace_days                                          │    │
│  │  - support levels (basic, pro, enterprise)             │    │
│  │  - layouts (admin, customer)                           │    │
│  │  - storage_path                                        │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Canonical Decisions

### 2.1 Data Model

| Decision | Value | Rationale |
|----------|-------|-----------|
| **Table name** | `tickets` | Package module standard |
| **Reply table** | `ticket_replies` | Package module standard |
| **Body field** | `description` (with `message` accessor) | Package uses `description`, canonical B uses `message` |
| **Primary key** | `id` (integer, auto-increment) | Keep integer IDs, add `uuid` as secondary |
| **Soft deletes** | Yes | Both implementations use it |
| **UUID** | `HasUuid` trait | Canonical trait for all modules |
| **User stamps** | `HasUserStamps` trait | Canonical trait for all modules |
| **Status constants** | `STATUS_OPEN`, `STATUS_IN_PROGRESS`, `STATUS_CLOSED` | Package standard |
| **Priority constants** | `PRIORITY_LOW`, `PRIORITY_NORMAL`, `PRIORITY_HIGH` | Package standard (add `PRIORITY_NORMAL`) |
| **Author constants** | `AUTHOR_STAFF`, `AUTHOR_CUSTOMER` | Package standard |

### 2.2 Backend Architecture

| Decision | Value | Rationale |
|----------|-------|-----------|
| **Controller pattern** | Service-based (no direct queries) | Canonical rule |
| **Validation** | FormRequest classes | Canonical rule |
| **Repository pattern** | Yes — `TicketRepository`, `TicketReplyRepository` | Canonical B uses it |
| **Service layer** | `TicketService` implements `TicketServiceInterface` | Canonical contract |
| **Licensing** | `TicketsLicensingService` implements `TicketsLicensingServiceInterface` | Extracted from B |
| **SLA** | `TicketsSlaService` implements `TicketsSlaServiceInterface` | Extracted from B |
| **Portal API** | `TicketsPortalService` implements `TicketsPortalServiceInterface` | Extracted from B |
| **License validation** | `LicenseValidationInterface` (injected) | Decoupled from external service |
| **Lifecycle** | `TicketsModuleLifecycleService` implements `ModuleLifecycleInterface` | Canonical lifecycle |
| **Exceptions** | `TicketPortalException` | Extracted from B |

### 2.3 Frontend

| Decision | Value | Rationale |
|----------|-------|-----------|
| **Layout** | Config-based: `config('tickets.layouts.admin')` | Override-safe (like Forms module) |
| **View namespace** | `tickets::` | Package standard |
| **CSS framework** | Tailwind CSS v3.4+ | Canonical rule |
| **Icons** | SVG inline, `w-5 h-5 flex-shrink-0` | Canonical rule |
| **Form fields** | `px-4 py-3 rounded-xl` | Canonical rule |
| **Cards** | `p-6 rounded-2xl shadow-sm` | Canonical rule |
| **Primary button** | `bg-gradient-to-r from-indigo-600 to-purple-600` | Canonical rule |
| **Stat cards** | 5 cards: total, open, in_progress, closed, sla_breached | Canonical B standard |
| **Chat UI** | Alpine.js `supportChat()` component | Canonical B standard |
| **Date format** | `Jalali::formatFa()` | Persian date standard |
| **Number format** | `NumberHelper::formatFa()` | Persian number standard |
| **Module features** | `@asset_module('tickets', 'css/tickets.css')` | Package asset standard |

### 2.4 Routing

| Decision | Value | Rationale |
|----------|-------|-----------|
| **Admin prefix** | `admin/tickets` | Package standard |
| **Customer prefix** | `dashboard/support-tickets` | Canonical B standard |
| **API prefix** | `api/support-tickets` | Canonical B standard |
| **Route type** | `Route::resource` + custom routes | Canonical rule |

### 2.5 Config

| Decision | Value | Rationale |
|----------|-------|-----------|
| **Config file** | `config/tickets.php` | Package standard |
| **SLA config** | Added to `config/tickets.php` | Merged from `config/support.php` |
| **Layout config** | `layouts.admin`, `layouts.customer` | Override-safe |
| **Storage path** | `storage_path` configurable | Prevents path conflicts |

---

## 3. File Structure

```
packages/saas-platform/modules/tickets/
├── config/
│   └── tickets.php                    # Module configuration
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
│       └── partials/
│           ├── ticket-stats.blade.php
│           ├── ticket-filters.blade.php
│           └── ticket-chat.blade.php
├── Routes/
│   └── web.php
└── src/
    ├── Contracts/
    │   ├── Services/
    │   │   ├── TicketServiceInterface.php
    │   │   ├── TicketsLicensingServiceInterface.php
    │   │   ├── TicketsSlaServiceInterface.php
    │   │   ├── TicketsPortalServiceInterface.php
    │   │   └── LicenseValidationInterface.php
    │   └── Repositories/
    │       ├── TicketRepositoryInterface.php
    │       └── TicketReplyRepositoryInterface.php
    ├── Exceptions/
    │   └── TicketPortalException.php
    ├── Models/
    │   ├── Ticket.php
    │   └── TicketReply.php
    ├── Providers/
    │   └── TicketsServiceProvider.php
    ├── Repositories/
    │   ├── TicketRepository.php
    │   └── TicketReplyRepository.php
    └── Services/
        ├── TicketService.php
        ├── TicketsLicensingService.php
        ├── TicketsSlaService.php
        ├── TicketsPortalService.php
        └── TicketsModuleLifecycleService.php
```

---

## 4. Contract Definitions

### 4.1 TicketServiceInterface
```php
interface TicketServiceInterface
{
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

### 4.2 TicketRepositoryInterface
```php
interface TicketRepositoryInterface
{
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

### 4.3 TicketReplyRepositoryInterface
```php
interface TicketReplyRepositoryInterface
{
    public function getByTicketId(int $ticketId): Collection;
    public function create(array $data): TicketReply;
}
```

### 4.4 TicketsLicensingServiceInterface
```php
interface TicketsLicensingServiceInterface
{
    public function isEligibleForUpdates($site): bool;
    public function getEffectiveSupportEnd($site): ?Carbon;
    public function isInGracePeriod($site): bool;
    public function getStatus($site): string;
    public function getLevel($site): string;
    public function getLevelFromConfig(?string $level): string;
    public function hasSupportStarted($site): bool;
    public function canAccessSupport($site): bool;
}
```

### 4.5 TicketsSlaServiceInterface
```php
interface TicketsSlaServiceInterface
{
    public function getResponseHours(string $supportLevel): int;
    public function getResolutionHours(string $supportLevel): ?int;
    public function isBusinessHoursEnabled(): bool;
    public function addBusinessHours(Carbon $start, int $totalHours): Carbon;
    public function isResponseBreached($ticket): bool;
    public function isResolutionBreached($ticket): bool;
    public function hasAnyBreach($ticket): bool;
    public function getResponseDeadline($ticket): Carbon;
    public function getResolutionDeadline($ticket): ?Carbon;
}
```

### 4.6 TicketsPortalServiceInterface
```php
interface TicketsPortalServiceInterface
{
    public function listTickets(string $licenseKey, string $domain): Collection;
    public function createTicket(array $data): Ticket;
    public function addCustomerReply(int $ticketId, array $data): Ticket;
    public function unreadCount(string $licenseKey, string $domain): int;
}
```

### 4.7 LicenseValidationInterface
```php
interface LicenseValidationInterface
{
    public function validate(string $licenseKey, string $domain): array;
}
```

---

## 5. Service Provider Bindings

```php
// TicketsServiceProvider::register()
public function register(): void
{
    // Core contracts
    $this->app->bind(TicketServiceInterface::class, TicketService::class);
    $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
    $this->app->bind(TicketReplyRepositoryInterface::class, TicketReplyRepository::class);

    // Licensing & SLA
    $this->app->bind(TicketsLicensingServiceInterface::class, TicketsLicensingService::class);
    $this->app->bind(TicketsSlaServiceInterface::class, TicketsSlaService::class);
    $this->app->bind(TicketsPortalServiceInterface::class, TicketsPortalService::class);

    // External dependency (injected by consuming project)
    $this->app->bind(LicenseValidationInterface::class, \App\Services\LicenseServerService::class);

    // Lifecycle
    $this->app->bind(ModuleLifecycleInterface::class, TicketsModuleLifecycleService::class);
}
```

---

## 6. Migration Readiness Summary

| Task | Status | Document |
|------|--------|----------|
| TASK 1: Divergence Audit | ✅ COMPLETE | `tickets-divergence-audit.md` |
| TASK 2: Canonical Authority Decision | ✅ COMPLETE | `tickets-canonical-authority-decision.md` |
| TASK 3: Shared Runtime Mapping | ✅ COMPLETE | `tickets-shared-runtime-mapping.md` |
| TASK 4: Frontend Canonicalization | ✅ COMPLETE | `tickets-frontend-canonicalization.md` |
| TASK 5: Shared Contract Extraction | ✅ COMPLETE | Contracts created in package |
| TASK 6: Tenant & Licensing Mapping | ✅ COMPLETE | `tickets-tenant-licensing-mapping.md` |
| TASK 7: Lifecycle Mapping | ✅ COMPLETE | `tickets-lifecycle-mapping.md` |
| TASK 8: Drift Elimination Plan | ✅ COMPLETE | `tickets-drift-elimination-plan.md` |
| TASK 9: Migration Risk Assessment | ✅ COMPLETE | `tickets-migration-risk-assessment.md` |
| TASK 10: Canonical Architecture | ✅ COMPLETE | This document |

**Migration Status:** 🔴 NOT READY — Analysis complete, contracts extracted, but no code migration yet.

**Next Steps:**
1. Begin PHASE 2: Data Model Unification (add `message` accessor, `support_level`, traits)
2. Begin PHASE 3: Licensing & SLA (create services in package)
3. Begin PHASE 4: Lifecycle Management (create lifecycle service)
4. Begin PHASE 5: Frontend Canonicalization (update views)
5. Begin PHASE 6: Project-Level Migration (saas-platform)
6. Begin PHASE 7: Customer-Site Migration
7. Begin PHASE 8: Testing & Validation
