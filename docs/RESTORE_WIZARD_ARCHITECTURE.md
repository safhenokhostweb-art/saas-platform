# RESTORE WIZARD ARCHITECTURE
> Date: 2026-06-26
> Status: FINAL — ARCHITECTURE DECIDED
> Entry: /admin/disaster-recovery/restore

---

## Overview

The Restore Wizard is a Laravel admin panel module that allows authorized platform admins to
restore any backup type from Arvan Object Storage without SSH access or manual commands.

**Backed by:** ArvanBackupService (existing), RestoreService (new), SSE progress streaming.

**4 restore flows:**
1. Tenant Database Restore
2. Platform Database Restore
3. Storage Files Restore
4. Full Disaster Restore (new server setup)

---

## Common Architecture

### Entry Route

```
GET  /admin/disaster-recovery          → Restore dashboard (browse all backup types)
GET  /admin/disaster-recovery/browse   → AJAX: list files from Arvan for a given path
POST /admin/disaster-recovery/restore  → Initiate restore job
GET  /admin/disaster-recovery/stream   → SSE: stream progress of running restore
GET  /admin/disaster-recovery/status   → AJAX: check if restore is running
POST /admin/disaster-recovery/abort    → Abort in-progress restore
```

### Middleware

```php
Route::middleware(['auth', 'admin', 'is_platform_admin'])->group(function () {
    // all disaster-recovery routes
});
```

No non-admin user can access any restore route under any circumstance.

### Progress Streaming

All restore jobs stream progress via SSE (Server-Sent Events):

```
GET /admin/disaster-recovery/stream?token={restore_token}
Content-Type: text/event-stream

data: {"step": 1, "message": "در حال دانلود فایل بکاپ...", "progress": 10}
data: {"step": 2, "message": "تأیید یکپارچگی gzip...", "progress": 20}
data: {"step": 3, "message": "ایجاد snapshot قبل از بازیابی...", "progress": 30}
...
data: {"step": "done", "result": "success", "message": "بازیابی با موفقیت انجام شد"}
```

Progress state stored in Redis: `restore_progress_{token}` with TTL 2 hours.

### Pre-Restore Safety Snapshot

Before any destructive DB operation:

```bash
mysqldump -u root {database_name} | gzip > /tmp/pre-restore-{timestamp}.sql.gz
```

This snapshot is kept in `/tmp/` for 24 hours. Never uploaded to Arvan.
If restore fails, admin can recover from this snapshot manually.

### Activity Log

Every restore operation writes to `activity_logs`:

```php
ActivityLog::create([
    'user_id'     => auth()->id(),
    'action'      => 'disaster_recovery_restore',
    'subject'     => "restore_type={$type} file={$filename}",
    'before_data' => ['row_counts' => $beforeCounts],
    'after_data'  => ['row_counts' => $afterCounts, 'result' => $result],
    'ip_address'  => request()->ip(),
]);
```

---

## Flow 1 — Tenant Database Restore

**Use case:** A specific tenant's data is corrupted or accidentally deleted.
Affects only rows with `site_id = N` in `customer_site` DB.

### Inputs

```
site_id     (integer, required)   — which tenant to restore
backup_file (string, required)    — Arvan path: customer-sites/tenant-{id}/database/{filename}
confirmation (boolean, required)  — checkbox: "تأیید می‌کنم که این عملیات داده‌های tenant X را بازنویسی می‌کند"
```

### Validation

| Check | Rule | Reject If |
|-------|------|-----------|
| site exists | `Site::find($siteId)` | site not found |
| backup file exists | `ArvanBackupService::exists($path)` | file not in Arvan |
| backup file size | `ArvanBackupService::size($path) > 32` | file too small (corrupted) |
| no active restore | `Redis::exists('restore_in_progress')` | another restore is running |
| confirmation checked | `$request->confirmed === true` | confirmation not given |

### Execution Steps

```
1. Set Redis lock: restore_in_progress = tenant-{siteId} (TTL 30min)
2. Download backup from Arvan: /tmp/restore-tenant-{siteId}-{timestamp}.sql.gz
3. Verify gzip integrity: gzip -t {file}
4. Create pre-restore snapshot: mysqldump customer_site --where="site_id={siteId}" | gzip
5. Load backup into temp DB: CREATE DATABASE restore_tmp_{siteId}
   gunzip < backup.sql.gz | mysql restore_tmp_{siteId}
6. Verify tenant data present: SELECT COUNT(*) FROM orders WHERE site_id = {siteId}
7. Delete existing tenant rows: DELETE FROM {table} WHERE site_id = {siteId}
   (for all 30+ tenant tables in order: child tables first, parent tables last)
8. Copy from temp DB to customer_site: mysqldump restore_tmp + mysql customer_site
9. Drop temp DB: DROP DATABASE restore_tmp_{siteId}
10. Flush caches: Cache::store('customers')->flush()
11. Release Redis lock
12. Write activity log
```

### Rollback Strategy

If any step fails:
1. Release Redis lock
2. Notify admin via Slack: `SlackAlertService::critical('Tenant restore failed')`
3. Pre-restore snapshot remains in `/tmp/` for manual recovery
4. DO NOT attempt automatic rollback (could cause double data loss)

### Queue Strategy

Dispatched as `RestoreTenantDatabaseJob` on the `restore` queue.
Queue worker must have `--tries=1` (no auto-retry on restore jobs — too dangerous).

```php
class RestoreTenantDatabaseJob implements ShouldQueue
{
    public $tries = 1;
    public $timeout = 600; // 10 minutes
    public $queue = 'restore';
}
```

---

## Flow 2 — Platform Database Restore

**Use case:** `saas_platform` DB is corrupted. Affects ALL tenants (sites table, licenses, etc.).

### Inputs

```
backup_file       (string, required)   — Arvan path: saas-platform/database/{filename}
confirmation_text (string, required)   — must exactly equal "RESTORE-PLATFORM"
```

### Validation

| Check | Rule | Reject If |
|-------|------|-----------|
| backup file exists | `ArvanBackupService::exists($path)` | not in Arvan |
| backup size | `> 1024 bytes` | file too small |
| confirmation text | `$text === 'RESTORE-PLATFORM'` | wrong text |
| no active restore | Redis lock check | another restore running |

### Execution Steps

```
1. Set Redis lock: restore_in_progress = platform-db (TTL 60min)
2. php artisan down --message="بازیابی دیتابیس در حال انجام"
3. supervisorctl stop all
4. Create pre-restore snapshot: mysqldump saas_platform | gzip > /tmp/pre-restore-platform-{ts}.sql.gz
5. Download backup from Arvan: /tmp/restore-platform-{timestamp}.sql.gz
6. Verify gzip: gzip -t {file}
7. DROP DATABASE saas_platform; CREATE DATABASE saas_platform (utf8mb4_unicode_ci)
8. gunzip < backup.sql.gz | mysql saas_platform
9. php artisan migrate --force
10. php artisan config:cache && php artisan route:cache && php artisan view:cache
11. supervisorctl start all
12. php artisan up
13. GET /health/ready → verify ready:true
14. Release Redis lock
15. Write activity log + Slack alert
```

### Rollback Strategy

Platform DB restore is NOT queued — it runs synchronously in maintenance mode.
If restore fails at step 8 or later:
1. Attempt to restore from pre-restore snapshot in `/tmp/`
2. If pre-restore snapshot also fails → platform stays DOWN
3. Admin must SSH and restore manually from Arvan using DISASTER_RECOVERY_PLAYBOOK.md Scenario D

### Queue Strategy

**Not queued.** Runs synchronously with `php artisan down` active.
Response is a long-running SSE stream from the controller action itself.
JavaScript polls `/admin/disaster-recovery/stream?token={token}` until `done` event.

---

## Flow 3 — Storage Files Restore

**Use case:** Platform or tenant uploaded files are lost (VPS disk corruption, accidental deletion).

### Inputs

```
backup_file    (string, required)   — Arvan path: saas-platform/storage/{filename}
restore_scope  (string, required)   — 'platform' or 'tenant-{siteId}'
merge_strategy (string, required)   — 'overwrite' or 'merge'
```

Merge strategy:
- `overwrite`: extract tar.gz, all existing files replaced
- `merge`: extract to temp dir, only copy files missing from destination (`rsync --ignore-existing`)

### Validation

| Check | Rule |
|-------|------|
| backup file exists | `ArvanBackupService::exists()` |
| disk space | At least `backup_size × 2` free in `/var/www/` |
| restore scope valid | 'platform' or 'tenant-N' where N is existing site_id |
| merge_strategy valid | must be 'overwrite' or 'merge' |

### Execution Steps

```
1. Download backup: /tmp/restore-storage-{timestamp}.tar.gz
2. Verify tar integrity: tar -tzf {file} > /dev/null
3. Check disk space: df -h
4. If overwrite: tar -xzf {file} -C /var/www/saas-platform/storage/app/public/
   If merge: mkdir /tmp/restore-storage-tmp/
             tar -xzf {file} -C /tmp/restore-storage-tmp/
             rsync -a --ignore-existing /tmp/restore-storage-tmp/ /var/www/saas-platform/storage/app/public/
             rm -rf /tmp/restore-storage-tmp/
5. Fix permissions: chown -R www-data:www-data /var/www/.../storage/
6. Cleanup: rm /tmp/restore-storage-{timestamp}.tar.gz
7. php artisan cache:clear
8. Write activity log
```

### Rollback Strategy

Storage restore has no automatic rollback. The `merge` strategy is inherently safer.
Advise using `merge` by default unless doing full recovery after data loss.

Pre-restore snapshot: Not needed for storage (no row data affected). Files overwritten
cannot be recovered unless a newer weekly backup exists.

### Queue Strategy

Dispatched as `RestoreStorageJob` on `restore` queue.
Timeout: 1800 seconds (30 minutes — large tar.gz files).

---

## Flow 4 — Full Disaster Restore

**Use case:** Entire VPS destroyed (Scenario E in DISASTER_RECOVERY_PLAYBOOK.md).
New server, fresh OS, no code, no databases, no files.

**This flow does NOT run via Laravel admin panel** (Laravel doesn't exist yet).
It runs via `installer/restore.php` — a standalone PHP file in the installer layer.

### Inputs (via standalone PHP form)

```
arvan_access_key   (string, required)  — stored in password manager, not on server
arvan_secret_key   (string, required)
arvan_endpoint     (string)            — default: https://s3.ir-thr-at1.arvanstorage.ir
arvan_bucket       (string)            — default: safhenokhost-backups
db_host            (string)            — default: 127.0.0.1
db_root_password   (string, required)
saas_app_key       (string, required)  — base64:... from original .env
saas_db_password   (string)            — if using non-root MySQL user
customer_app_key   (string, required)  — base64:... from original .env
```

### Validation

```
1. Test Arvan connectivity: ArvanClient::testConnection()
2. Test MySQL connectivity: mysqli_connect($host, 'root', $password)
3. Verify APP_KEY format: starts with 'base64:'
4. Verify Arvan bucket is accessible: ArvanClient::listObjects('saas-platform/releases/', 1)
5. Check disk space: disk_free_space('/var/www') > 5GB
```

### Execution Steps

```
Phase 1 — Infrastructure (manual prerequisite, not automated):
  Server provisioned, nginx/mysql/redis/php/supervisor installed
  (see DISASTER_RECOVERY_PLAYBOOK.md Scenario E Steps 1-3)

Phase 2 — Code Deploy (automated via restore.php):
  1. List releases in Arvan: saas-platform/releases/ → pick latest
  2. Download + extract saas-platform release ZIP → /var/www/saas-platform/
  3. List releases in Arvan: customer-sites/releases/ → pick latest
  4. Download + extract customer-site release ZIP → /var/www/customer-site/
  5. Write .env files for both apps (from form inputs)
  6. chown -R www-data:www-data /var/www/saas-platform/ /var/www/customer-site/

Phase 3 — Database Restore (automated via restore.php):
  7. CREATE DATABASE saas_platform (utf8mb4)
  8. CREATE DATABASE customer_site (utf8mb4)
  9. Download latest saas-platform DB backup: saas-platform/database/LATEST.sql.gz
  10. Download latest customer-site DB backup: customer-sites/tenant-1/database/LATEST.sql.gz
  11. Verify gzip integrity for both
  12. Restore both: gunzip < backup.sql.gz | mysql {db}

Phase 4 — Application Setup (automated via restore.php calling PHP CLI):
  13. cd /var/www/saas-platform && php artisan migrate --force
  14. php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link
  15. cd /var/www/customer-site && php artisan migrate --force
  16. php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link

Phase 5 — Storage Restore (automated via restore.php):
  17. Download latest storage backup: saas-platform/storage/LATEST.tar.gz
  18. Extract: tar -xzf → /var/www/saas-platform/storage/app/public/
  19. Fix permissions: find /var/www -type d -exec chmod 755 {} \;
  20. chown -R www-data:www-data /var/www/

Phase 6 — Service Config (manual + guided):
  21. restore.php displays Nginx config template → admin copies to /etc/nginx/sites-available/
  22. restore.php displays Supervisor config → admin copies to /etc/supervisor/conf.d/
  23. restore.php displays Cron entry → admin adds to /etc/cron.d/saas-scheduler

Phase 7 — Validation:
  24. GET http://localhost/health/ready (direct, bypass DNS) → must return ready:true
  25. restore.php displays DNS cutover instructions for ArvanCloud
```

### Rollback Strategy

Not applicable — this is a fresh-server restore. There is nothing to roll back to.
If any phase fails, admin fixes the specific step and re-runs from that phase.
`restore.php` tracks completed phases in `restore_state.json` to allow resuming.

### Queue Strategy

Not queued — standalone PHP, no queue infrastructure available.
All execution is synchronous with real-time output echoed to browser via `flush()`.

---

## Error Handling Summary

| Error | Response |
|-------|----------|
| Arvan download fails | Retry 3×; alert Slack; show manual download instructions |
| gzip -t fails | Abort; show "backup file is corrupted — select a different backup" |
| DB restore fails mid-run | Abort; keep pre-restore snapshot; show manual recovery instructions |
| Platform DB restore: can't bring back up | Stay in maintenance mode; alert Slack CRITICAL; admin must SSH |
| SSE connection drops | Client reconnects via EventSource; Redis progress state is preserved |
| Multiple restore attempts | Redis lock prevents concurrent restores; show "restore already in progress" |

---

*RESTORE_WIZARD_ARCHITECTURE — generated 2026-06-26 — FINAL*
