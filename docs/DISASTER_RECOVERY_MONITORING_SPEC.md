# DISASTER RECOVERY MONITORING SPEC
> Version: 1.0
> Date: 2026-06-26
> Status: DESIGN — Not yet implemented

---

## Monitoring Objectives

| Objective | Threshold | Data Source |
|-----------|-----------|-------------|
| DB backup freshness | < 24h | `InfraBackupRun` |
| Storage backup freshness | < 7 days | `InfraBackupRun` |
| Arvan sync verification | Latest file present | `ArvanBackupService::exists()` |
| Restore success rate | > 90% (30d) | `RestoreLog` |
| Tenant backup coverage | All active tenants | `Site` + `InfraBackupRun` |

---

## 1. Backup Monitoring

### 1.1 Platform DB Backup

**Data query:**
```php
$lastDb = InfraBackupRun::whereNull('site_id')
    ->where('backup_type', 'database')
    ->orderByDesc('started_at')
    ->first();
```

**Displayed metrics:**
- `Last run time` — `$lastDb->completed_at->diffForHumans()`
- `Status` — `$lastDb->status` (success / failed / running)
- `File size` — `$lastDb->file_size_mb` MB
- `Duration` — `$lastDb->duration_seconds`s
- `Arvan sync` — `ArvanBackupService::exists(platformDbPath($filename))`
- `Age (hours)` — `now()->diffInHours($lastDb->completed_at)`

**Health status computation:**
```php
function dbBackupHealth(InfraBackupRun $run, ArvanBackupService $arvan): string {
    if ($run->isFailed())                          return 'FAILED';
    if (now()->diffInHours($run->completed_at) > 24) return 'STALE';
    $filename = basename($run->file_path);
    if (!$arvan->exists("saas-platform/database/{$filename}")) return 'ARVAN_MISSING';
    return 'OK';
}
```

**UI color mapping:**
```
OK            → green badge
STALE         → yellow badge
FAILED        → red badge
ARVAN_MISSING → orange badge
MISSING       → red badge (no InfraBackupRun exists at all)
```

### 1.2 Platform Storage Backup

Same structure, different query:
```php
InfraBackupRun::whereNull('site_id')
    ->where('backup_type', 'storage')
    ->orderByDesc('started_at')
    ->first();
```

Stale threshold: **7 days** (not 24h — storage backup runs weekly).

Arvan path: `ArvanBackupService::platformStoragePath($filename)` = `saas-platform/storage/{filename}`

### 1.3 Backup Size History

**Query (last 30 successful DB runs):**
```php
InfraBackupRun::whereNull('site_id')
    ->where('backup_type', 'database')
    ->where('status', 'success')
    ->orderByDesc('started_at')
    ->limit(30)
    ->get(['file_size_mb', 'started_at'])
```

**Anomaly detection (display-only):**
- Compute rolling mean and stddev of `file_size_mb`
- Flag any run where size < (mean - 2σ) as `SIZE_ANOMALY`
- These are displayed with a warning icon in the chart tooltip

### 1.4 Backup Failure Log

**Query:**
```php
InfraBackupRun::where('status', 'failed')
    ->orderByDesc('started_at')
    ->limit(10)
    ->get(['backup_type', 'site_id', 'error_message', 'started_at'])
```

Displayed in a collapsible error log panel. Each entry shows:
- Type (database / storage)
- Tenant (if tenant backup) or "Platform"
- Error message (truncated to 200 chars, expandable)
- Timestamp

---

## 2. Restore Monitoring

### 2.1 Recent Restore Events

**Query:**
```php
RestoreLog::with(['site', 'executor'])
    ->orderByDesc('created_at')
    ->limit(20)
    ->get()
```

**Displayed columns:**
| Column | Source |
|--------|--------|
| Restore type | `$log->typeLabelFa()` |
| Target | `$log->site->name ?? 'Platform'` |
| Status | `$log->statusLabelFa()` (colored badge) |
| Dry run | boolean icon |
| Duration | `$log->completed_at->diffInSeconds($log->started_at)`s |
| Executor | `$log->executor->name ?? 'System'` |
| Time | `$log->created_at->diffForHumans()` |
| Detail | Link → `disaster-recovery.logs.show` |

### 2.2 Restore Statistics (30d)

Computed from `RestoreLog::where('created_at', '>=', now()->subDays(30))`:

```php
$stats = [
    'total'        => $q->count(),
    'success'      => $q->where('status', 'success')->count(),
    'failed'       => $q->where('status', 'failed')->count(),
    'dry_runs'     => $q->where('dry_run', true)->count(),
    'success_rate' => $total > 0 ? round(($success / $total) * 100) : 100,
    'avg_duration' => $q->whereNotNull('completed_at')
                        ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg')
                        ->value('avg'),
];
```

Displayed as 4 metric cards: Total / Success / Failed / Avg Duration.

### 2.3 Rollback Events

```php
RestoreLog::whereNotNull('rollback_snapshot_path')
    ->orderByDesc('created_at')
    ->limit(5)
    ->get(['restore_type', 'site_id', 'rollback_snapshot_path', 'created_at'])
```

Shows pre-restore snapshots that were created. Note: these are `/tmp/` paths on the server — existence cannot be verified from the dashboard (file system access). Displayed as audit trail only.

---

## 3. Arvan Sync Status

### 3.1 Arvan File Listing (Cached)

```php
// Cached 5 minutes to avoid excessive Arvan API calls
$platformDbFiles = Cache::remember('dr_arvan_platform_db', 300, function () use ($arvan) {
    return $arvan->listFiles('saas-platform/database/');
});

$platformStorageFiles = Cache::remember('dr_arvan_platform_storage', 300, function () use ($arvan) {
    return $arvan->listFiles('saas-platform/storage/');
});
```

**Displayed:**
- Total files on Arvan
- Latest file name + timestamp
- Latest file size
- "Refresh" button (clears cache, re-fetches)

### 3.2 Sync Gap Detection

Compare `InfraBackupRun` success count (last 7d) with Arvan file count:

```php
$dbRunsLast7d   = InfraBackupRun::success()->whereNull('site_id')
    ->where('backup_type', 'database')
    ->where('started_at', '>=', now()->subDays(7))
    ->count();

$arvanFilesLast7d = collect($platformDbFiles)
    ->filter(fn($f) => $f['last_modified'] >= now()->subDays(7)->timestamp)
    ->count();

$syncGap = $dbRunsLast7d - $arvanFilesLast7d; // > 0 means some runs didn't upload
```

If `$syncGap > 0`: show warning banner "X backup runs may not have synced to Arvan".

---

## 4. Tenant Visibility

### 4.1 Tenant Health Table

**Data assembly:**
```php
$sites = Site::where('lifecycle_status', 'active')->get();

$tenantHealth = $sites->map(function (Site $site) use ($arvan) {
    $lastDbBackup = InfraBackupRun::where('site_id', $site->id)
        ->where('backup_type', 'database')
        ->where('status', 'success')
        ->orderByDesc('started_at')
        ->first();

    $lastRestore = RestoreLog::where('site_id', $site->id)
        ->where('status', 'success')
        ->orderByDesc('created_at')
        ->first();

    $arvanFiles = Cache::remember("dr_arvan_tenant_{$site->id}", 300, function () use ($arvan, $site) {
        return $arvan->listFiles("customer-sites/tenant-{$site->id}/database/");
    });

    return [
        'site'            => $site,
        'last_db_backup'  => $lastDbBackup,
        'db_health'       => $this->tenantDbHealth($lastDbBackup, $arvan, $site->id),
        'arvan_file_count'=> count($arvanFiles),
        'last_restore'    => $lastRestore,
        'alert_count'     => $this->tenantAlertCount($site->id),
    ];
});
```

**Note on tenant backups:** `InfraBackupRun` has `site_id` column, but current `BackupDatabaseCommand` only backs up the platform DB. Tenant DB backups are a future feature. The dashboard shows "N/A" for tenants with no backup runs, not an error — this is expected state until tenant backup commands are implemented.

### 4.2 Per-Tenant Detail Page

`GET /center/tenant/{site}` renders:
- Tenant info card (domain, lifecycle, plan)
- Backup run history (paginated, `InfraBackupRun::where('site_id', $site->id)`)
- Restore history (paginated, `RestoreLog::where('site_id', $site->id)`)
- Arvan file listing: `customer-sites/tenant-{id}/database/` and `customer-sites/tenant-{id}/storage/`
- Health score: composite 0–100

**Health score formula:**
```
score = 100
- 30 if no DB backup in 7d
- 20 if any failed restore in 30d
- 20 if Arvan has 0 files
- 10 if last backup run failed
- 10 if no restore ever performed (informational)
+ 10 bonus if Arvan count matches DB run count (sync verified)
```

---

## 5. Dashboard Refresh Strategy

| Section | Refresh | Mechanism |
|---------|---------|-----------|
| Platform health cards | Manual (F5) | Server-rendered |
| Backup size chart | On page load | AJAX `GET /timeline` |
| Tenant health table | On tab click | AJAX partial (lazy) |
| Integrity check | On button click | AJAX `POST /integrity` |
| Alert feed | Auto every 60s | `setInterval` + AJAX |
| Arvan file listing | Manual "Refresh" | AJAX + cache-clear |

No WebSocket or SSE on DR Center — it is a monitoring dashboard, not a real-time stream.

---

## 6. Migration Assessment

**No migration required** for the monitoring spec. All data is already in existing tables:
- `infra_backup_runs` ← InfraBackupRun
- `infra_scheduler_runs` ← InfraSchedulerRun
- `restore_logs` ← RestoreLog (Phase 1)
- `sites` ← Site

The only optional migration is for `dr_alert_log` (in-app persistent alert history). This is described in `DISASTER_RECOVERY_ALERT_SYSTEM.md` and is explicitly optional — the Alert System functions without it (Slack-only).

---

*Spec version: 1.0 — 2026-06-26*
