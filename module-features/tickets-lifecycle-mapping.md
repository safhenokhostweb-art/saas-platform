# TASK 7: Tickets Lifecycle Mapping

## Objective
Map all lifecycle events for the Tickets module to the canonical `ModuleLifecycleInterface` contract. This is an **analysis document only** — no migration yet.

---

## 1. Canonical Lifecycle Contract

**Interface:** `SaaSPlatform\Modules\Forms\Contracts\ModuleLifecycleInterface`
**Reference Implementation:** `SaaSPlatform\Modules\Forms\Services\ModuleLifecycleService`

| Method | Purpose |
|--------|---------|
| `install(): bool` | First-time module installation |
| `uninstall(): bool` | Full module removal |
| `activate(int $siteId): bool` | Tenant-specific activation |
| `deactivate(int $siteId): bool` | Tenant-specific deactivation |
| `update(string $from, string $to): bool` | Version upgrade |
| `rollback(string $current, string $target): bool` | Version rollback |
| `getVersion(): string` | Current module version |
| `getSlug(): string` | Module slug identifier |

---

## 2. Tickets Module Lifecycle Mapping

### 2.1 Current State
The Tickets package module (Implementation A) has **NO lifecycle management**. The `TicketsServiceProvider` only handles:
- Route registration
- View registration
- Migration loading
- Config merging
- Asset publishing/symlinking

There is no `ModuleLifecycleInterface` implementation, no schema version tracking, no install/uninstall logic, and no tenant activation/deactivation.

### 2.2 Proposed TicketsModuleLifecycleService

```php
namespace SaaSPlatform\Modules\Tickets\Services;

use SaaSPlatform\Modules\Forms\Contracts\ModuleLifecycleInterface;

class TicketsModuleLifecycleService implements ModuleLifecycleInterface
{
    public const VERSION = '1.0.0';
    public const SLUG = 'tickets';

    public function install(): bool { /* ... */ }
    public function uninstall(): bool { /* ... */ }
    public function activate(int $siteId): bool { /* ... */ }
    public function deactivate(int $siteId): bool { /* ... */ }
    public function update(string $fromVersion, string $toVersion): bool { /* ... */ }
    public function rollback(string $currentVersion, string $targetVersion): bool { /* ... */ }
    public function getVersion(): string { return self::VERSION; }
    public function getSlug(): string { return self::SLUG; }
}
```

---

## 3. Lifecycle Event Details

### 3.1 `install()`
**Purpose:** First-time installation of the Tickets module.

| Step | Description | Implementation |
|------|-------------|----------------|
| 1 | Ensure schema version table exists | Create `module_schema_versions` if not exists (shared with Forms) |
| 2 | Publish config | `vendor:publish --tag=tickets-config` |
| 3 | Publish views | `vendor:publish --tag=tickets-views` |
| 4 | Publish migrations | `vendor:publish --tag=tickets-migrations` |
| 5 | Run migrations | `artisan migrate --force` |
| 6 | Publish assets | `vendor:publish --tag=tickets-assets` |
| 7 | Record schema version | Insert into `module_schema_versions` |
| 8 | Clear cache | `artisan optimize:clear` |

### 3.2 `uninstall()`
**Purpose:** Complete removal of the Tickets module.

| Step | Description | Implementation |
|------|-------------|----------------|
| 1 | Drop module tables | `tickets`, `ticket_replies` |
| 2 | Remove published views | Delete `resources/views/vendor/tickets` |
| 3 | Remove published config | Delete `config/tickets.php` |
| 4 | Remove published assets | Delete `public/vendor/tickets` |
| 5 | Remove schema version | Delete from `module_schema_versions` |

### 3.3 `activate(int $siteId)`
**Purpose:** Enable tickets for a specific tenant/site.

| Step | Description | Implementation |
|------|-------------|----------------|
| 1 | Check licensing | Verify site has active support license via `TicketsLicensingService` |
| 2 | Log activation | Record in `module_schema_versions` notes |
| 3 | Create default settings | Initialize any site-specific ticket settings |

### 3.4 `deactivate(int $siteId)`
**Purpose:** Disable tickets for a specific tenant/site.

| Step | Description | Implementation |
|------|-------------|----------------|
| 1 | Log deactivation | Record in `module_schema_versions` notes |
| 2 | Optionally close open tickets | Mark open tickets as closed (configurable) |

### 3.5 `update(string $fromVersion, string $toVersion)`
**Purpose:** Upgrade the Tickets module to a new version.

| Step | Description | Implementation |
|------|-------------|----------------|
| 1 | Run version-specific migrations | `runVersionMigrations($from, $to)` |
| 2 | Update schema version | Update `module_schema_versions` record |
| 3 | Clear cache | `artisan optimize:clear` |

### 3.6 `rollback(string $currentVersion, string $targetVersion)`
**Purpose:** Roll back a Tickets module update.

| Step | Description | Implementation |
|------|-------------|----------------|
| 1 | Run rollback migrations | `runRollbackMigrations($current, $target)` |
| 2 | Update schema version | Update `module_schema_versions` record |
| 3 | Clear cache | `artisan optimize:clear` |

---

## 4. Version Migration Map

| From | To | Migration Logic |
|------|----|-----------------|
| `1.0.0` | `1.1.0` | Add `support_level` column to `tickets` table |
| `1.1.0` | `1.2.0` | Add `sla_breached_at` column to `tickets` table |

### Rollback Map

| From | To | Rollback Logic |
|------|----|----------------|
| `1.1.0` | `1.0.0` | Drop `support_level` column from `tickets` table |
| `1.2.0` | `1.1.0` | Drop `sla_breached_at` column from `tickets` table |

---

## 5. Schema Version Tracking

The Tickets module will use the **same** `module_schema_versions` table as the Forms module (shared infrastructure).

```sql
-- Shared table (already created by Forms module)
CREATE TABLE module_schema_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_slug VARCHAR(100) UNIQUE NOT NULL,
    version VARCHAR(20) NOT NULL,
    previous_version VARCHAR(20) NULL,
    status VARCHAR(50) DEFAULT 'installed',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Tickets record example:**
```json
{
    "module_slug": "tickets",
    "version": "1.0.0",
    "previous_version": null,
    "status": "installed",
    "notes": "[2026-05-24 12:00:00] Installed version 1.0.0"
}
```

---

## 6. Service Provider Integration

When the lifecycle service is ready, `TicketsServiceProvider` will be updated with:

```php
// In register():
$this->app->bind(ModuleLifecycleInterface::class, TicketsModuleLifecycleService::class);

// In boot():
if ($this->app->runningInConsole()) {
    $this->commands([
        // Lifecycle commands if needed
    ]);
}
```

---

## 7. Divergence Notes

| Aspect | Implementation B (Canonical) | Package Module (Current) | Action |
|--------|------------------------------|--------------------------|--------|
| Lifecycle contract | Not implemented (project-level) | None | Create `TicketsModuleLifecycleService` implementing `ModuleLifecycleInterface` |
| Schema version tracking | None | None | Use shared `module_schema_versions` table |
| Install logic | Manual migration run | None | Implement `install()` with publish + migrate |
| Uninstall logic | Manual table drop | None | Implement `uninstall()` with cleanup |
| Tenant activation | Manual site assignment | None | Implement `activate(int $siteId)` |
| Tenant deactivation | Manual site removal | None | Implement `deactivate(int $siteId)` |
| Version migrations | Manual `php artisan migrate` | None | Implement `update()` + `rollback()` |

---

## 8. Migration Readiness

**Status:** 🔴 NOT READY — Analysis complete, lifecycle mapped, but no code migration yet.

**Pre-requisites for migration:**
1. Create `TicketsModuleLifecycleService` implementing `ModuleLifecycleInterface`
2. Register in `TicketsServiceProvider`
3. Add version migration logic for future schema changes
4. Test install/uninstall cycle
5. Test activate/deactivate for tenant isolation
