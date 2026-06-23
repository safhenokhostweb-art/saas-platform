# MONITORING_FINAL_AUDIT
> Phase 5: Monitoring & Infrastructure Final Audit  
> Date: 2026-06-23  
> Status: **MONITORING_AUDIT_PASS — 1 CRITICAL FIX APPLIED**

---

## Executive Summary

| Component | Status | Notes |
|-----------|--------|-------|
| Laravel scheduler (saas-platform) | ✅ RUNNING | `/etc/cron.d/laravel-scheduler` every minute |
| Laravel scheduler (customer-site) | ✅ RUNNING | Same cron file, separate command |
| Queue workers (saas-platform) | ✅ RUNNING | 2 processes, uptime 21+ min |
| Queue workers (customer-site) | ✅ FIXED | Was FATAL — restarted in this audit |
| Reverb WebSocket | ✅ RUNNING | Uptime 3 days 23h |
| Disk monitoring | ✅ IMPLEMENTED | ScanRuntimeAnomaliesCommand (≥80%/90%) |
| Backup overdue alert | ✅ IMPLEMENTED | ScanRuntimeAnomaliesCommand (>26h) |
| Scheduler dead alert | ✅ IMPLEMENTED | ScanRuntimeAnomaliesCommand (>5min) |
| Disk usage | ✅ SAFE | 43% (8.0G/20G) |
| Daily backups | ✅ RUNNING | 7 files, Jun 17–23 |
| deploy.sh worker restart | ✅ FIXED | Added supervisorctl after queue:restart |

---

## Section 1: Scheduler Verification

### Cron Configuration

```
# /etc/cron.d/laravel-scheduler
# Added by infra:seed-production 2026-06-15

# saas-platform scheduler (runs every minute)
* * * * * root cd /var/www/saas-platform && php artisan schedule:run >> /var/log/laravel-schedule.log 2>&1

# customer-site scheduler (runs every minute)
* * * * * root cd /var/www/customer-site && php artisan schedule:run >> /var/log/laravel-customer-schedule.log 2>&1
```

**Status:** ACTIVE ✅  
**Both schedulers running every minute** — covers all 24 registered scheduled commands.

### Scheduled Commands Running in Production

| Command | Schedule | Purpose |
|---------|----------|---------|
| `monitor:platform` | Every 2 min | Platform health checks |
| `monitor:sites` | Every 5 min | Customer site health probes |
| `runtime:scan-anomalies` | Every 5 min | Queue/disk/backup/scheduler alerts |
| `db:backup --retention=7` | Daily 02:00 | Database backup |
| `lifecycle:check-expired` | Daily | Subscription expiry |
| `lifecycle:check-grace-period` | Daily | Grace period enforcement |
| `lifecycle:check-suspended` | Daily | Suspension enforcement |
| `lifecycle:check-archived` | Daily | Archive enforcement |
| `resource:calculate --push` | Daily 06:00 | Tenant resource metrics |
| `resource:cleanup` | Weekly Sun 02:00 | Old bandwidth/snapshot records |
| `subscription:process-renewals` | Daily 08:00 | Auto-renewal processing |
| + 13 more | Various | Education AI, SMS, SEO, translations |

---

## Section 2: Supervisor Process Status (Verified 2026-06-23)

### Final State After Audit

```
customer-site-worker:customer-site-worker_00   RUNNING   pid 401663, uptime 0:05:58
reverb                                         RUNNING   pid 254654, uptime 3 days, 23:44:56
saas-platform-worker:saas-platform-worker_00   RUNNING   pid 400535, uptime 0:21:02
saas-platform-worker:saas-platform-worker_01   RUNNING   pid 400540, uptime 0:21:00
```

### Critical Finding (FIXED): customer-site-worker FATAL

**Finding:** At audit start, `customer-site-worker:customer-site-worker_00` was in **FATAL** state ("Exited too quickly"). Queue jobs on customer-site were NOT being processed.

**Root Cause:** After the previous deploy, `deploy.sh` cleared the bootstrap cache and regenerated it (Linux paths). However, the old FATAL status persisted because:
1. Supervisord marks a process as FATAL after repeated crashes (autorestart exhausted)
2. FATAL processes do NOT auto-restart — they require manual `supervisorctl start`
3. The deploy.sh's `php artisan queue:restart` signals running workers, but cannot start FATAL ones
4. The worker never recovered from the Windows-path config cache era (from a very old deploy)

**Fix Applied (this audit):**
```bash
supervisorctl start "customer-site-worker:*"
# Result: customer-site-worker:customer-site-worker_00: started
# Status: RUNNING pid 401663, uptime 0:00:05
```

**Permanent Fix Applied to deploy.sh:**
```bash
# Added after `php artisan queue:restart`:
supervisorctl start "${PROJECT}-worker:*" 2>/dev/null || true
supervisorctl restart "${PROJECT}-worker:*" 2>/dev/null || true
```

This ensures FATAL workers are recovered on every deploy.

---

## Section 3: Disk Monitoring

### Current Disk State

```
Filesystem      Size  Used Avail Use% Mounted on
/dev/vda1        20G  8.0G   11G  43% /
```

**Status:** SAFE — 57% free ✅

### Monitoring Implementation

`ScanRuntimeAnomaliesCommand.checkOperational()` includes:

```php
$usedPct = round((1 - $freeBytes / $totalBytes) * 100, 1);
if ($usedPct >= 90) {
    $slack->critical('DISK_CRITICAL', "Disk usage at {$usedPct}%...");
} elseif ($usedPct >= 80) {
    $slack->high('DISK_WARNING', "Disk usage at {$usedPct}%...");
}
```

Runs every 5 minutes. Current at 43% — no alert will fire. ✅

---

## Section 4: Queue Monitoring

### Queue Depth

The saas-platform queue uses Redis with `redis:default`. No pending jobs detected.

```bash
# Monitor command:
php artisan queue:monitor redis:default --max=100
```

### Failed Jobs Alert

`RuntimeAnomalyEngine` (via `ScanRuntimeAnomaliesCommand`) checks for failed jobs older than 1 hour and fires Slack critical alert.

---

## Section 5: Backup Success Monitoring

```php
// ScanRuntimeAnomaliesCommand:
$lastBackup = InfraBackupRun::where('status', 'success')->latest()->first();
if (!$lastBackup || $lastBackup->created_at->lt(now()->subHours(26))) {
    $slack->critical('BACKUP_OVERDUE', '...');
}
```

**Latest backup:** `db_backup_2026-06-23_020003.sql.gz` (353KB) — created at 02:00:03 ✅  
**No overdue alert will fire** for next 26 hours from backup time.

---

## Section 6: Scheduler Dead Detection

```php
// ScanRuntimeAnomaliesCommand:
$lastRun = InfraSchedulerRun::latest()->first();
if ($lastRun && $lastRun->created_at->lt(now()->subMinutes(5))) {
    $slack->critical('SCHEDULER_DEAD', '...');
}
```

This fires if the cron daemon stops running `schedule:run`. The cron file is active in `/etc/cron.d/laravel-scheduler`.

---

## Section 7: Health Endpoint

`GET /health/ready` returns:

```json
{
  "ready": true,
  "checks": {
    "database": true,
    "cache": true,
    "queue": true,
    "storage": true
  }
}
```

Note: `curl http://localhost/health/ready` returns 404 because Nginx requires `Host: panel.safhenokhost.ir`. Access via domain name works.

---

## Remaining Gaps (Post-Launch)

| Gap | Priority | Status |
|-----|----------|--------|
| Redis queue depth alert (>500 pending) | MEDIUM | Deferred |
| SMS delivery rate alert (<70%) | MEDIUM | Deferred |
| telemetry_events table pruning | LOW | Deferred |
| Archive ZIP integrity check | LOW | Deferred |
| DB size trending alert (>2GB) | LOW | Deferred |

---

## Verdict

**MONITORING_AUDIT_PASS**  
One critical fix applied: customer-site queue worker restarted after FATAL state. All 4 production processes now RUNNING. Scheduler active for both repos. Disk/backup/scheduler anomaly detection implemented and running every 5 minutes.

---

*MONITORING_FINAL_AUDIT — generated 2026-06-23*
