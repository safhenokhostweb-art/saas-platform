# DISASTER RECOVERY CENTER — ARCHITECTURE
> Version: 1.0
> Date: 2026-06-26
> Status: DESIGN — Not yet implemented
> Phase: 2 (follows Phase 1: Restore Wizard — OPERATIONAL)

---

## Overview

The Disaster Recovery Center (DR Center) is a read-only monitoring dashboard that aggregates backup health, restore history, integrity status, and alerts for both the platform and all tenants. It does **not** initiate restores (that is Restore Wizard's job) and does **not** modify backup flows.

**URL:** `/admin/disaster-recovery/center`
**Controller:** `App\Http\Controllers\Admin\DrCenterController`
**View prefix:** `resources/views/admin/disaster-recovery/center/`

---

## Data Sources (Read-Only — No Architecture Changes)

| Data | Model / Source | Already Exists |
|------|---------------|----------------|
| Backup run history | `InfraBackupRun` (`infra_backup_runs`) | ✅ |
| Scheduler run log | `InfraSchedulerRun` (`infra_scheduler_runs`) | ✅ |
| Restore history | `RestoreLog` (`restore_logs`) | ✅ (Phase 1) |
| Arvan file listing | `ArvanBackupService::listFiles()` | ✅ (Phase 1) |
| Alert dispatch | `SlackAlertService` | ✅ |
| Tenant list | `Site` (`sites`) | ✅ |
| Activity log | `ActivityLog` (`activity_logs`) | ✅ |

**No new migrations required** — all data sources exist. The only optional addition is a `dr_alert_log` table for persistent in-app alert history (see Alert System doc).

---

## Controller: DrCenterController

```
app/Http/Controllers/Admin/DrCenterController.php
```

### Methods

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | GET `/center` | Main dashboard (platform overview) |
| `tenantHealth(Request)` | GET `/center/tenant/{site}` | Per-tenant backup health |
| `backupTimeline()` | GET `/center/timeline` | JSON — backup run history (chart data) |
| `integrityCheck(Request)` | POST `/center/integrity` | Trigger on-demand Arvan checksum |
| `alertHistory()` | GET `/center/alerts` | Paginated in-app alert log |

All routes mounted under existing `admin.disaster-recovery.*` prefix group.

---

## Section Architecture

### Section 1 — Platform Backup Health

**View:** `center/partials/platform-health.blade.php`

**Data computed in:** `DrCenterController::index()` using `InfraBackupRun` and `ArvanBackupService`.

```
Platform DB Backup
├── Last run: 2026-06-26 02:00:01 (3h ago)           ← InfraBackupRun::latestPlatform()
├── Status: SUCCESS                                    ← $run->status
├── File size: 48.2 MB                                 ← $run->file_size_mb
├── Duration: 12s                                      ← $run->duration_seconds
├── Arvan sync: VERIFIED                               ← ArvanBackupService::exists()
└── Stale check: OK (< 24h threshold)                 ← now()->diffInHours($run->completed_at)

Platform Storage Backup
├── Last run: 2026-06-25 03:00:10 (23h ago)
├── Status: SUCCESS
├── File size: 214.7 MB
└── Stale check: OK (< 7d threshold)
```

**Health Status Logic:**
```php
$dbHealth = match(true) {
    $lastDbRun === null                    => 'MISSING',
    $lastDbRun->isFailed()                 => 'FAILED',
    $lastDbRun->completed_at < now()->subHours(24) => 'STALE',
    !$arvan->exists($remotePath)           => 'ARVAN_MISSING',
    default                                => 'OK',
};
```

---

### Section 2 — Restore Monitor

**View:** `center/partials/restore-monitor.blade.php`

**Data:** `RestoreLog::orderByDesc('created_at')->limit(20)->get()`

```
Recent Restore Events
┌──────────────────────────────────────────────────────────────────┐
│ Type              │ Target    │ Status  │ Duration │ Executor │ Time │
│ tenant_database   │ Tenant #3 │ SUCCESS │ 2m 14s   │ admin    │ 1h   │
│ platform_database │ Platform  │ FAILED  │ 45s      │ admin    │ 3d   │
└──────────────────────────────────────────────────────────────────┘

Rollback Events
└── 0 rollback snapshots created in last 30 days
    (RestoreLog::whereNotNull('rollback_snapshot_path'))
```

**Metrics computed:**
- Total restores (30d)
- Success rate (%)
- Average duration
- Failed restores requiring attention

---

### Section 3 — Backup Size History (Chart)

**Endpoint:** `GET /center/timeline` → returns JSON for Chart.js

**Data:** `InfraBackupRun::where('backup_type', 'database')->where('status', 'success')->orderByDesc('started_at')->limit(30)->get(['file_size_mb', 'started_at'])`

```json
{
  "labels": ["Jun 25", "Jun 24", "Jun 23", ...],
  "datasets": [
    { "label": "DB Backup (MB)", "data": [48.2, 47.9, 48.1, ...] },
    { "label": "Storage Backup (MB)", "data": [214.7, 213.2, ...] }
  ]
}
```

**Frontend:** Lightweight Chart.js line chart (already used elsewhere in admin — no new dep).

---

### Section 4 — Integrity System

**View:** `center/partials/integrity.blade.php`

**On-demand check:** `POST /center/integrity` — triggers `DrIntegrityService::check()` (synchronous, returns within 10s).

**DrIntegrityService (new, lightweight):**
```
app/Services/Infrastructure/DrIntegrityService.php
```

```php
class DrIntegrityService {
    public function checkPlatform(): array {
        // 1. List files in saas-platform/database/ on Arvan
        // 2. Compare count against InfraBackupRun success records (last 7d)
        // 3. Verify latest file exists and size matches InfraBackupRun::file_size_mb
        // 4. Return {db: {status, latest_file, arvan_count, db_count, size_match}}
    }

    public function checkTenant(int $siteId): array {
        // Same for customer-sites/tenant-{siteId}/database/
    }
}
```

**Integrity Result Display:**
```
Integrity Check — Platform
├── DB backups on Arvan: 7 files            ← ArvanBackupService::listFiles()
├── DB backup runs (success, 7d): 7         ← InfraBackupRun::success()->count()
├── Latest file present: YES                ← ArvanBackupService::exists()
├── Size match: YES (48.2 MB)               ← ArvanBackupService::size() vs InfraBackupRun
└── Status: INTEGRITY_OK
```

**Missing backup detection:**
- DB: no success run in last 26h → MISSING alert
- Storage: no success run in last 8d → MISSING alert

---

### Section 5 — Tenant Visibility

**View:** `center/partials/tenant-health.blade.php`

**Data:** All active `Site` records + `InfraBackupRun::where('site_id', $id)` per tenant.

```
Tenant Backup Health

┌──────────────────────────────────────────────────────┐
│ Tenant        │ DB Backup   │ Storage  │ Arvan  │ ⚠️  │
│ customer-site │ 3h ago OK   │ 23h OK   │ SYNCED │ 0  │
│ Tenant #2     │ MISSING     │ MISSING  │ -      │ 2  │
└──────────────────────────────────────────────────────┘
```

Each row links to `GET /center/tenant/{site}` for per-tenant detail:
- Full backup run history (paginated)
- All restore events for this tenant
- Arvan path: `customer-sites/tenant-{id}/database/` file listing
- Health score: 0–100 based on recency + arvan sync + no failures

---

## View Structure

```
resources/views/admin/disaster-recovery/center/
  index.blade.php          ← Main dashboard layout (5 sections)
  partials/
    platform-health.blade.php
    restore-monitor.blade.php
    size-chart.blade.php
    integrity.blade.php
    tenant-health.blade.php
    alert-feed.blade.php
  tenant-detail.blade.php  ← Per-tenant drill-down
  alerts.blade.php         ← Alert history (paginated)
```

---

## Route Addition (Non-Breaking)

Add inside existing `disaster-recovery` route group:

```php
Route::prefix('center')->name('center.')->group(function () {
    Route::get('/',                 [DrCenterController::class, 'index'])->name('index');
    Route::get('/tenant/{site}',    [DrCenterController::class, 'tenantHealth'])->name('tenant');
    Route::get('/timeline',         [DrCenterController::class, 'backupTimeline'])->name('timeline');
    Route::post('/integrity',       [DrCenterController::class, 'integrityCheck'])->name('integrity');
    Route::get('/alerts',           [DrCenterController::class, 'alertHistory'])->name('alerts');
});
```

**Route names:** `admin.disaster-recovery.center.*`

---

## AdminMenu Addition

```php
// In app/Helpers/AdminMenu.php
'dr_center' => 'مرکز DR',
// Under: زیرساخت / DevOps group, after restore_wizard
```

---

## New Files Summary

| File | Type | Note |
|------|------|------|
| `app/Http/Controllers/Admin/DrCenterController.php` | Controller | Read-only, no writes |
| `app/Services/Infrastructure/DrIntegrityService.php` | Service | On-demand integrity check |
| `resources/views/admin/disaster-recovery/center/index.blade.php` | View | Main dashboard |
| `resources/views/admin/disaster-recovery/center/tenant-detail.blade.php` | View | Per-tenant |
| `resources/views/admin/disaster-recovery/center/alerts.blade.php` | View | Alert log |
| `resources/views/admin/disaster-recovery/center/partials/*.blade.php` | Partials | 6 partials |

**Modified files:**
- `routes/web.php` — add center route group (inside existing disaster-recovery group)
- `app/Helpers/AdminMenu.php` — add `dr_center` entry
- `resources/views/admin/partials/sidebar-icon.blade.php` — add DR center icon

**No migration needed** unless persistent alert log is implemented (optional — see Alert System doc).

---

## Performance Constraints

- `DrCenterController::index()` must complete in < 3s
- Arvan file listing calls are cached: `Cache::remember('dr_arvan_platform_db', 300, ...)` (5-min TTL)
- Tenant health table is computed lazily — only loads on tab click (AJAX partial)
- Timeline chart data is a separate AJAX call (non-blocking)
- Integrity check is on-demand only (POST button click) — never auto-runs on page load

---

*Architecture version: 1.0 — 2026-06-26*
