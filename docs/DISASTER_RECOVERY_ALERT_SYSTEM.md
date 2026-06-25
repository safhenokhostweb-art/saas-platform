# DISASTER RECOVERY ALERT SYSTEM
> Version: 1.0
> Date: 2026-06-26
> Status: DESIGN — Not yet implemented

---

## Alert Channels

| Channel | Mechanism | Config |
|---------|-----------|--------|
| Slack | `SlackAlertService` (already exists) | `SLACK_ALERT_WEBHOOK` env |
| In-app feed | `dr_alert_log` table (optional) | No extra config |
| Dashboard banner | DR Center index page | Always shown |

The system is layered: Slack fires immediately, the DR Center dashboard always shows latest state, and the optional `dr_alert_log` table provides persistent in-app history.

---

## Alert Definitions

### ALERT-001 — DB Backup Stale (CRITICAL)

| Field | Value |
|-------|-------|
| Trigger | No successful `InfraBackupRun` (type=database) in > 26h |
| Why 26h | Scheduler runs daily at 02:00 + 2h grace window |
| Severity | CRITICAL |
| Channel | Slack + Dashboard banner |
| Auto-resolve | When next successful backup completes |

**Detection query:**
```php
$lastSuccess = InfraBackupRun::whereNull('site_id')
    ->where('backup_type', 'database')
    ->where('status', 'success')
    ->orderByDesc('started_at')
    ->first();

if (!$lastSuccess || $lastSuccess->completed_at->lt(now()->subHours(26))) {
    // ALERT-001 fires
}
```

**Slack payload:**
```
🔴 CRITICAL: Database Backup Stale
Platform database backup has not completed in over 26 hours.
Last success: {time} ({hours}h ago)
Action: Check scheduler logs at /admin/disaster-recovery/center
```

---

### ALERT-002 — DB Backup Failed (CRITICAL)

| Field | Value |
|-------|-------|
| Trigger | `InfraBackupRun::endFailed()` called — already fires in `BackupDatabaseCommand` via `SlackAlertService::critical()` |
| Severity | CRITICAL |
| Channel | Slack (already wired) + Dashboard banner |
| Note | Already implemented in `BackupDatabaseCommand`. DR Center only displays it. |

**DR Center adds:** Display in alert feed. The Slack trigger already exists — no code change to backup command.

---

### ALERT-003 — Arvan Sync Failed (HIGH)

| Field | Value |
|-------|-------|
| Trigger | `InfraBackupRun::status=success` but Arvan does NOT contain the file |
| Severity | HIGH |
| Channel | Slack (already partially wired via warning in BackupDatabaseCommand) + Dashboard banner |
| Detection | `DrIntegrityService::checkPlatform()` detects sync gap |

**Gap detection:**
```php
// Fired by ScanDrHealthCommand (new scheduled command — see below)
$filename = basename($latestRun->file_path);
$remotePath = "saas-platform/database/{$filename}";
if (!$arvan->exists($remotePath)) {
    $slack->high('Arvan Sync Gap Detected', "Backup {$filename} not found on Arvan", [...]);
}
```

---

### ALERT-004 — Storage Backup Stale (HIGH)

| Field | Value |
|-------|-------|
| Trigger | No successful `InfraBackupRun` (type=storage) in > 8 days |
| Why 8d | Storage backup is weekly (7d) + 1d grace |
| Severity | HIGH |
| Channel | Slack + Dashboard banner |

---

### ALERT-005 — Restore Failed (HIGH)

| Field | Value |
|-------|-------|
| Trigger | `RestoreTenantDatabaseJob::failed()` or `RestorePlatformStorageJob::failed()` fires |
| Severity | HIGH |
| Channel | Slack (wired in job `failed()` method — Phase 1) + Dashboard banner |
| Note | Already partially implemented in Phase 1 jobs. DR Center displays it. |

**DR Center adds:** Persistent display in alert feed with link to restore log detail.

---

### ALERT-006 — Tenant Backup Missing (MEDIUM)

| Field | Value |
|-------|-------|
| Trigger | Active tenant (`lifecycle_status=active`) has no `InfraBackupRun` record and no Arvan files |
| Severity | MEDIUM |
| Channel | Dashboard banner only (no Slack for individual tenants — would be noisy) |
| Note | Informational — tenant backups are a future feature. Alert makes the gap visible. |

---

### ALERT-007 — Restore Lock Stuck (HIGH)

| Field | Value |
|-------|-------|
| Trigger | Redis key `restore_in_progress` exists for > 2 hours |
| Severity | HIGH |
| Channel | Slack + Dashboard banner |
| Auto-resolve | When lock is released |

**Detection:**
```php
// In ScanDrHealthCommand
$locked = Redis::exists('restore_in_progress');
if ($locked) {
    $ttl = Redis::ttl('restore_in_progress');
    $lockAge = $lockTtl - $ttl; // original TTL - remaining = age
    if ($lockAge > 7200) { // 2h
        $slack->high('Restore Lock Stuck', "Redis restore_in_progress lock has been held for {$lockAge}s", [...]);
    }
}
```

---

## ScanDrHealthCommand (New — Scheduled)

```
app/Console/Commands/ScanDrHealthCommand.php
```

This command runs on schedule and fires alerts for ALERT-001, ALERT-003, ALERT-004, ALERT-007. It does NOT modify any data — read-only health check.

**Signature:** `dr:scan-health`

**Schedule:** Every hour (`->hourly()` in `app/Console/Kernel.php`)

**Logic:**
```php
public function handle(
    ArvanBackupService $arvan,
    SlackAlertService $slack,
    RestoreService $restore
): int {
    $alerts = [];

    // ALERT-001: DB backup stale
    $lastDb = InfraBackupRun::whereNull('site_id')
        ->where('backup_type', 'database')
        ->where('status', 'success')
        ->orderByDesc('started_at')->first();
    if (!$lastDb || $lastDb->completed_at->lt(now()->subHours(26))) {
        $hoursAgo = $lastDb ? now()->diffInHours($lastDb->completed_at) : 'never';
        $slack->critical('DB Backup Stale', "Last success: {$hoursAgo}h ago");
        $alerts[] = ['type' => 'ALERT-001', 'severity' => 'critical', ...];
    }

    // ALERT-003: Arvan sync gap
    if ($lastDb) {
        $filename = basename($lastDb->file_path ?? '');
        if ($filename && !$arvan->exists("saas-platform/database/{$filename}")) {
            $slack->high('Arvan Sync Gap', "File {$filename} missing on Arvan");
            $alerts[] = ['type' => 'ALERT-003', 'severity' => 'high', ...];
        }
    }

    // ALERT-004: Storage backup stale
    $lastStorage = InfraBackupRun::whereNull('site_id')
        ->where('backup_type', 'storage')
        ->where('status', 'success')
        ->orderByDesc('started_at')->first();
    if (!$lastStorage || $lastStorage->completed_at->lt(now()->subDays(8))) {
        $daysAgo = $lastStorage ? now()->diffInDays($lastStorage->completed_at) : 'never';
        $slack->high('Storage Backup Stale', "Last success: {$daysAgo}d ago");
        $alerts[] = ['type' => 'ALERT-004', 'severity' => 'high', ...];
    }

    // ALERT-007: Restore lock stuck
    if ($restore->isLocked()) {
        // Check lock age via TTL math (lock was set with 3600s TTL)
        $remaining = Redis::ttl('restore_in_progress');
        $age = 3600 - $remaining;
        if ($age > 7200) {
            $slack->high('Restore Lock Stuck', "Lock held {$age}s. Current: " . $restore->getLockValue());
            $alerts[] = ['type' => 'ALERT-007', 'severity' => 'high', ...];
        }
    }

    // Optionally persist to dr_alert_log table (if migration is run)
    $this->persistAlerts($alerts);

    return self::SUCCESS;
}
```

**To wire into scheduler** — add to `app/Console/Kernel.php`:
```php
$schedule->command('dr:scan-health')->hourly();
```

---

## In-App Alert Feed (Optional — requires migration)

### Migration: `dr_alert_log`

```php
Schema::create('dr_alert_log', function (Blueprint $table) {
    $table->id();
    $table->string('alert_type', 20);           // ALERT-001 ... ALERT-007
    $table->string('severity', 10);             // critical, high, medium
    $table->text('message');
    $table->json('context')->nullable();
    $table->boolean('resolved')->default(false);
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
    $table->index(['alert_type', 'resolved']);
    $table->index('created_at');
});
```

If this migration is NOT run, `ScanDrHealthCommand::persistAlerts()` is a no-op (wrapped in try/catch). The dashboard alert feed section is hidden when table doesn't exist. The system degrades gracefully to Slack-only alerts.

### Alert Feed Display

`GET /center/alerts` (paginated, 20/page):

```
Alert History

┌────────────────────────────────────────────────────────────┐
│ Type      │ Severity │ Message             │ Time   │ State │
│ ALERT-001 │ CRITICAL │ DB Backup Stale... │ 2h ago │ OPEN  │
│ ALERT-003 │ HIGH     │ Arvan Sync Gap...  │ 1d ago │ RESOLVED │
└────────────────────────────────────────────────────────────┘
```

Filters: severity, resolved/open, date range.

---

## Alert Deduplication

`ScanDrHealthCommand` runs hourly. To avoid repeated Slack noise for the same ongoing issue:

**Dedup strategy (no DB required):**
```php
// Cache key per alert type, TTL = 6h
$cacheKey = "dr_alert_sent_{$alertType}";
if (!Cache::has($cacheKey)) {
    $slack->critical(...);
    Cache::put($cacheKey, true, 6 * 3600); // silence for 6h
}
```

This means:
- First detection → Slack fires immediately
- Repeated detections in next 6h → silent (cached)
- After 6h → fires again if still unresolved
- When resolved (e.g. backup succeeds) → `Cache::forget($cacheKey)`

---

## Alert Routing Summary

| Alert | Slack | Dashboard Banner | Alert Feed (optional) |
|-------|-------|-----------------|----------------------|
| ALERT-001 DB Stale | ✅ CRITICAL | ✅ Red banner | ✅ If table exists |
| ALERT-002 DB Failed | ✅ (existing) | ✅ | ✅ |
| ALERT-003 Arvan Gap | ✅ HIGH | ✅ Orange banner | ✅ |
| ALERT-004 Storage Stale | ✅ HIGH | ✅ Yellow banner | ✅ |
| ALERT-005 Restore Failed | ✅ (existing) | ✅ | ✅ |
| ALERT-006 Tenant Missing | ❌ | ✅ Per-tenant row | ✅ |
| ALERT-007 Lock Stuck | ✅ HIGH | ✅ Red banner | ✅ |

---

## Implementation Order

1. `ScanDrHealthCommand` — core health scanner (required)
2. Wire to `Kernel.php` scheduler (required)
3. Dashboard banners in `DrCenterController::index()` — pass `$alerts` to view
4. `dr_alert_log` migration (optional — add if in-app history is needed)
5. `/center/alerts` page (optional — only if migration is run)

---

*Alert System version: 1.0 — 2026-06-26*
