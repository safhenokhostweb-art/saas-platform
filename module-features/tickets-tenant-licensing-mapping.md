# TASK 6: Tickets Tenant & Licensing Mapping

## Objective
Map all tenant/licensing/SLA logic from Implementation B (canonical authority) into the package module's contract/service architecture. This is an **analysis document only** — no migration yet.

---

## 1. Licensing Services Overview

### 1.1 SupportLicenseService (Implementation B)
**Path:** `app/Services/SupportLicenseService.php`
**Namespace:** `App\Services`

| Method | Purpose | Package Target |
|--------|---------|----------------|
| `isEligibleForUpdates(Site)` | Check if site is eligible for updates | `TicketsLicensingService` |
| `getEffectiveSupportEnd(Site)` | Get effective support end date (expires + grace) | `TicketsLicensingService` |
| `isInGracePeriod(Site)` | Check if site is in grace period | `TicketsLicensingService` |
| `getStatus(Site)` | Get support status: active/grace/expired/none | `TicketsLicensingService` |
| `getLevel(Site)` | Get support level: basic/pro/enterprise | `TicketsLicensingService` |
| `getLevelFromConfig(?string)` | Normalize support level from config | `TicketsLicensingService` |
| `hasSupportStarted(Site)` | Check if support has started | `TicketsLicensingService` |
| `canAccessSupport(Site)` | Check if site can access support tickets | `TicketsLicensingService` |

### 1.2 SupportSlaService (Implementation B)
**Path:** `app/Services/SupportSlaService.php`
**Namespace:** `App\Services`

| Method | Purpose | Package Target |
|--------|---------|----------------|
| `getResponseHours(string)` | Get max response hours by support level | `TicketsSlaService` |
| `getResolutionHours(string)` | Get max resolution hours by support level | `TicketsSlaService` |
| `isBusinessHoursEnabled()` | Check if business hours are enabled | `TicketsSlaService` |
| `addBusinessHours(Carbon, int)` | Calculate deadline with business hours | `TicketsSlaService` |
| `isResponseBreached(SupportTicket)` | Check if response SLA is breached | `TicketsSlaService` |
| `isResolutionBreached(SupportTicket)` | Check if resolution SLA is breached | `TicketsSlaService` |
| `hasAnyBreach(SupportTicket)` | Check if any SLA is breached | `TicketsSlaService` |
| `getResponseDeadline(SupportTicket)` | Get response deadline Carbon | `TicketsSlaService` |
| `getResolutionDeadline(SupportTicket)` | Get resolution deadline Carbon | `TicketsSlaService` |

### 1.3 SupportPortalService (Implementation B)
**Path:** `app/Services/SupportPortalService.php`
**Namespace:** `App\Services`

| Method | Purpose | Package Target |
|--------|---------|----------------|
| `listTickets(string, string)` | List tickets by license key + domain | `TicketsPortalService` |
| `createTicket(array)` | Create ticket via API | `TicketsPortalService` |
| `addCustomerReply(int, array)` | Add customer reply via API | `TicketsPortalService` |
| `unreadCount(string, string)` | Get unread count by license key + domain | `TicketsPortalService` |
| `resolveLicensedSite(string, string, bool)` | Resolve site from license + domain | `TicketsPortalService` (private) |

---

## 2. Config Dependencies

### 2.1 `config/support.php` (Implementation B)
Expected config structure (not yet created in package):

```php
<?php
return [
    'grace_days' => env('SUPPORT_GRACE_DAYS', 7),
    'levels' => ['basic', 'pro', 'enterprise'],
    'sla' => [
        'basic' => [
            'response_hours' => 24,
            'resolution_hours' => 72,
        ],
        'pro' => [
            'response_hours' => 8,
            'resolution_hours' => 48,
        ],
        'enterprise' => [
            'response_hours' => 2,
            'resolution_hours' => 24,
        ],
    ],
    'business_hours' => [
        'enabled' => env('SUPPORT_BUSINESS_HOURS_ENABLED', true),
        'timezone' => env('SUPPORT_TIMEZONE', 'Asia/Tehran'),
        'weekdays' => [1, 2, 3, 4, 5], // Mon-Fri
        'start' => '09:00',
        'end' => '17:00',
    ],
];
```

### 2.2 Site Model Fields (Implementation B)
The `Site` model must have these fields for licensing to work:
- `support_expires_at` (datetime, nullable)
- `support_starts_at` (datetime, nullable)
- `support_level` (string, nullable) — values: basic, pro, enterprise

---

## 3. External Service Dependencies

### 3.1 LicenseServerService (Implementation B)
**Path:** `app/Services/LicenseServerService.php`
Used by `SupportPortalService::resolveLicensedSite()` to validate license key + domain.

**Package Target:** `TicketsPortalService` will depend on a `LicenseValidationInterface` contract.

```php
interface LicenseValidationInterface
{
    public function validate(string $licenseKey, string $domain): array;
}
```

---

## 4. Package Contract Definitions

### 4.1 TicketsLicensingServiceInterface
```php
namespace SaaSPlatform\Modules\Tickets\Contracts\Services;

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

### 4.2 TicketsSlaServiceInterface
```php
namespace SaaSPlatform\Modules\Tickets\Contracts\Services;

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

### 4.3 TicketsPortalServiceInterface
```php
namespace SaaSPlatform\Modules\Tickets\Contracts\Services;

interface TicketsPortalServiceInterface
{
    public function listTickets(string $licenseKey, string $domain): Collection;
    public function createTicket(array $data): Ticket;
    public function addCustomerReply(int $ticketId, array $data): Ticket;
    public function unreadCount(string $licenseKey, string $domain): int;
}
```

### 4.4 LicenseValidationInterface
```php
namespace SaaSPlatform\Modules\Tickets\Contracts\Services;

interface LicenseValidationInterface
{
    public function validate(string $licenseKey, string $domain): array;
}
```

---

## 5. Service Provider Bindings

When the package is ready for migration, `TicketsServiceProvider::register()` will be updated with:

```php
$this->app->bind(TicketsLicensingServiceInterface::class, TicketsLicensingService::class);
$this->app->bind(TicketsSlaServiceInterface::class, TicketsSlaService::class);
$this->app->bind(TicketsPortalServiceInterface::class, TicketsPortalService::class);
$this->app->bind(LicenseValidationInterface::class, \App\Services\LicenseServerService::class);
```

---

## 6. Divergence Notes

| Aspect | Implementation B (Canonical) | Package Module (Current) | Action |
|--------|------------------------------|--------------------------|--------|
| Licensing | Full `SupportLicenseService` with grace period, levels, status | None | Create `TicketsLicensingService` in package |
| SLA | Full `SupportSlaService` with business hours, breach detection | None | Create `TicketsSlaService` in package |
| Portal API | Full `SupportPortalService` with license validation | None | Create `TicketsPortalService` in package |
| Config | `config/support.php` with SLA, levels, business hours | `config/tickets.php` (basic) | Add SLA config to `config/tickets.php` |
| Site fields | `support_expires_at`, `support_starts_at`, `support_level` | Not referenced | Document as required fields |
| License validation | `LicenseServerService` | None | Create `LicenseValidationInterface` |

---

## 7. Migration Readiness

**Status:** 🔴 NOT READY — Analysis complete, contracts defined, but no code migration yet.

**Pre-requisites for migration:**
1. Create `TicketsLicensingService` + interface in package
2. Create `TicketsSlaService` + interface in package
3. Create `TicketsPortalService` + interface in package
4. Create `LicenseValidationInterface` in package
5. Add SLA config to `config/tickets.php`
6. Update `TicketsServiceProvider` with new bindings
7. Update `Ticket` model to include `support_level` field
8. Update migrations to add `support_level` column
