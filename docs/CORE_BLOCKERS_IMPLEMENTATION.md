# CORE BLOCKERS IMPLEMENTATION
> CORE_BLOCKERS_IMPLEMENTATION — 2026-06-27
> Status: COMPLETE
> Scope: All blockers from CORE_CERTIFICATION_PHASE_1

---

## P0 — Critical Blockers

### P0-1: Platform Database Restore

**New file:** `app/Jobs/RestorePlatformDatabaseJob.php`
- `$tries = 1` (no retry — destructive)
- `$timeout = 3600` (1 hour)
- `$queue = 'restore'`
- `failed()` method: releases lock + calls `artisan up` + Slack alert

**Modified:** `app/Services/Infrastructure/RestoreService.php` — `executePlatformDatabaseRestore()`
- Step 1: Download from Arvan (streaming)
- Step 2: Verify gzip integrity (`gzip -t`)
- Step 3: Enable maintenance mode (`artisan down`)
- Step 4: Create pre-restore snapshot (MYSQL_PWD env var)
- Step 5: DROP + CREATE DATABASE
- Step 6: Restore: `gunzip -c backup.sql.gz | mysql saas_platform`
- Step 7: Run migrations (`artisan migrate --force`)
- Step 8: Rebuild caches (config + route + view)
- Step 9: Disable maintenance mode (`artisan up`)
- Cleanup: delete temp file + release lock + activity log
- On any error: `artisan up` called in catch block (ensures maintenance mode is always cleared)

**Modified:** `app/Http/Controllers/Admin/RestoreWizardController.php`
- `RestorePlatformDatabaseJob::dispatch()` now dispatched for `TYPE_PLATFORM_DATABASE`
- All restore jobs now dispatch `->onConnection('restore')->onQueue('restore')`

---

### P0-2: Queue Reliability

**Modified:** `config/queue.php`
- Added `restore` connection: `driver=redis, queue=restore, retry_after=7200` (2 hours)
- Default connections unchanged to avoid regressions

**Modified:** `app/Http/Controllers/Admin/RestoreWizardController.php`
- All 4 restore types now dispatch `->onConnection('restore')->onQueue('restore')`
- No restore job will use the default 90s `retry_after` connection

**Modified:** `app/Services/Installer/InstallerValidator.php`
- `checkQueue()`: `sync` driver now returns `status => 'fail'` (was `warn`)
- Message explicitly states: "QUEUE_CONNECTION=redis را در .env تنظیم کنید"
- `database` driver remains `warn` (functional but not optimal)
- Only `redis` gets `pass`

---

### P0-3: Restore Atomicity

**Modified:** `app/Services/Infrastructure/RestoreService.php`
- `executeTenantDatabaseRestore()`: delete+copy wrapped in `DB::transaction()`
- `deleteTenantData()`: rewritten to use `DB::statement()` with parameterized query
- `copyTenantData()`: rewritten to use `DB::statement()` with parameterized query
- `getTenantTableNames()`: uses `DB::select()` with `?` binding — no string interpolation in SQL
- Cross-database transaction works because both databases are on the same MySQL server
- If `copyTenantData` fails after `deleteTenantData`: full rollback — tenant data restored to pre-delete state

---

## P1 — High Blockers

### P1-4: Tenant Database Backup

**New file:** `app/Console/Commands/BackupTenantDatabaseCommand.php`
- Signature: `db:backup-tenants {--site=} {--retention=7} {--dry-run}`
- Queries all active sites (lifecycle_status = 'active' OR NULL)
- For each site: `mysqldump --where="site_id={N}"` (only that tenant's rows)
- Uses `MYSQL_PWD` env var — password never in CLI
- Records each backup to `InfraBackupRun` with `site_id`
- Uploads to Arvan at `customer-sites/tenant-{id}/database/{filename}`
- Prunes Arvan retention per tenant (keepCount = $retention)
- Deletes local file after Arvan upload (Arvan is durable copy)
- Slack warning on partial failure

**Modified:** `app/Console/Kernel.php`
- Added `db:backup-tenants --retention=7` daily at `02:30` (after platform backup at 02:00)
- `withoutOverlapping()` + `appendOutputTo()` storage/logs/tenant-backup.log

---

### P1-5: Backup Retention (Arvan Prune)

**Modified:** `app/Services/Infrastructure/ArvanBackupService.php`
- Added `prune(string $prefix, int $keepCount = 7): array` method
- Lists files (newest-first), deletes files beyond keepCount
- Returns `['pruned' => N, 'kept' => M]`
- Individual delete failures are logged as warnings but don't stop pruning

**Modified:** `app/Console/Commands/BackupDatabaseCommand.php`
- After Arvan upload: calls `$arvan->prune($arvan->platformDbPath(''), $retention)`
- Logs prune results

**Modified:** `app/Console/Commands/BackupStorageCommand.php`
- After Arvan upload: calls `$arvan->prune($arvan->platformStoragePath(''), 4)` (keep 4 weekly = 1 month)
- Logs prune results

**New command:** `BackupTenantDatabaseCommand` — prunes per-tenant Arvan prefix after each upload

---

### P1-6: Scheduler Reliability (SCHEDULER_DEAD Fix)

**Modified:** `app/Console/Kernel.php`
- Added `$schedule->call(fn() => Cache::put('scheduler_last_run', now(), 120))->everyMinute()`
- Writes `scheduler_last_run` cache key (TTL 120s) on every scheduler tick
- This is exactly what `InstallerValidator::checkScheduler()` already looks for

**Modified:** `app/Console/Commands/ScanRuntimeAnomaliesCommand.php`
- `SCHEDULER_DEAD` now checks `cache('scheduler_last_run')` (not `InfraSchedulerRun`)
- Alert fires if heartbeat is null AND `< now()-3min` (3-minute grace period for clock skew)
- If heartbeat is null (never run), no alert — avoids false positive on fresh installs
- Old `InfraSchedulerRun` import removed (no longer needed here)

---

## P2 — Security Improvements

### P2-7: MYSQL_PWD (No Password in CLI Args)

**Modified:** `app/Services/Infrastructure/RestoreService.php`
- `createDatabaseSnapshot()`: uses `MYSQL_PWD=...` env prefix instead of `-p...` arg
- `executePlatformDatabaseRestore()`: all `mysql` and `gunzip | mysql` calls use `MYSQL_PWD=`
- `executeTenantDatabaseRestore()`: all `mysql` calls use `MYSQL_PWD=`

**Modified:** `app/Console/Commands/BackupDatabaseCommand.php`
- `mysqldump` call changed from `-p$passwordArg` to `MYSQL_PWD=... mysqldump`

**Modified:** `app/Console/Commands/BackupTenantDatabaseCommand.php`
- Uses `MYSQL_PWD=` from the start (new command, never had the vulnerability)

---

## P2-8: Scalability Improvements

### listFiles() N+1 Fix

**Modified:** `app/Services/Infrastructure/ArvanBackupService.php`
- `listFiles()`: replaced `files()` + N×`size()` + N×`lastModified()` with `listContents()`
- `listContents()` returns `League\Flysystem\FileAttributes` with `fileSize()` and `lastModified()` from the S3 ListObjects response — single API call
- Fallback to individual calls only if metadata is null in listing response
- Reduces from O(2N+1) HTTP requests to O(1) HTTP request per prefix

### DB Index

**New migration:** `2026_06_27_000001_add_composite_index_to_infra_backup_runs.php`
- Adds `infra_backup_runs_dr_query_idx` on `(site_id, backup_type, status, started_at)`
- Covers the DR health scan query pattern executed hourly

---

## Files Changed Summary

| File | Change Type | Blocker |
|------|-------------|---------|
| `config/queue.php` | Modified | P0-2 |
| `app/Services/Infrastructure/RestoreService.php` | Rewritten | P0-1, P0-3, P2-7 |
| `app/Jobs/RestorePlatformDatabaseJob.php` | New | P0-1 |
| `app/Http/Controllers/Admin/RestoreWizardController.php` | Modified | P0-1, P0-2 |
| `app/Services/Installer/InstallerValidator.php` | Modified | P0-2 |
| `app/Services/Infrastructure/ArvanBackupService.php` | Modified | P1-5, P2-8 |
| `app/Console/Commands/BackupDatabaseCommand.php` | Modified | P1-5, P2-7 |
| `app/Console/Commands/BackupStorageCommand.php` | Modified | P1-5 |
| `app/Console/Commands/BackupTenantDatabaseCommand.php` | New | P1-4 |
| `app/Console/Kernel.php` | Modified | P1-4, P1-6 |
| `app/Console/Commands/ScanRuntimeAnomaliesCommand.php` | Modified | P1-6 |
| `database/migrations/2026_06_27_000001_*.php` | New | P2-8 |
