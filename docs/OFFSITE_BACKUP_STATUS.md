# OFFSITE_BACKUP_STATUS
> Phase 4: Offsite Backup Status Report  
> Date: 2026-06-23  
> Status: **BACKUP_RUNNING_OFFSITE_PENDING**

---

## Current Backup State (Verified via SSH, 2026-06-23)

### Database Backups

**7 daily backup files confirmed on production:**

```
-rwxrwxr-x 1 www-data www-data 129K Jun 17 05:30 db_backup_2026-06-17_020002.sql.gz
-rwxrwxr-x 1 www-data www-data 155K Jun 18 05:30 db_backup_2026-06-18_020003.sql.gz
-rwxrwxr-x 1 www-data www-data 181K Jun 19 05:30 db_backup_2026-06-19_020003.sql.gz
-rwxrwxr-x 1 www-data www-data 206K Jun 20 05:30 db_backup_2026-06-20_020003.sql.gz
-rwxrwxr-x 1 www-data www-data 255K Jun 21 05:30 db_backup_2026-06-21_020002.sql.gz
-rwxrwxr-x 1 www-data www-data 307K Jun 22 05:30 db_backup_2026-06-22_020002.sql.gz
-rw-r--r-- 1 root     root     353K Jun 23 05:30 db_backup_2026-06-23_020003.sql.gz
```

**Status:** ✅ Backup command running daily at 02:00  
**Retention:** 7 files (automated pruning working)  
**Growth rate:** 129KB → 353KB over 7 days (healthy, consistent growth)  
**Integrity:** `gzip -t` validated by `BackupDatabaseCommand` on creation  
**Failure alert:** Slack alert wired via `SlackAlertService::critical()` on failure  
**DB record:** `infra_backup_runs` table tracking all runs  
**Disk:** 43% used (8.0G / 20G) — 12GB available ✅

### Customer Uploads

**Location:** `/var/www/customer-site/storage/app/public/`  
**Backup:** None — no automated backup  
**Risk:** HIGH for production data loss on VPS failure

### Archive ZIPs

**Location:** `/var/www/saas-platform/storage/app/archives/{site_id}.zip`  
**Backup:** None — included in DB backup for metadata, but ZIP files are not backed up  
**Risk:** MEDIUM — archived sites lose their ZIP on VPS failure

---

## Offsite Backup: NOT YET IMPLEMENTED

**Gap:** All 7 daily database backups are on the same physical server as the database. A VPS failure (hardware, disk corruption, provider incident) would destroy both the database AND all backups simultaneously.

**This gap was documented in `docs/OFFSITE_BACKUP_AUDIT.md` (2026-06-22) and remains open.**

No new backup infrastructure was built in this audit — consistent with the audit scope (no speculative changes).

---

## Pre-Launch Actions Required

### Minimum: Manual Offsite Copy Before DNS Cutover

```bash
# SSH to VPS:
ssh -p 9011 root@87.107.12.81

# Take fresh backup:
cd /var/www/saas-platform && php artisan db:backup

# Copy to local machine via SCP:
scp -P 9011 root@87.107.12.81:/var/www/saas-platform/storage/app/backups/db_backup_2026-06-23_*.sql.gz ./
```

### Recommended: ArvanCloud S3 Sync

```bash
# Install AWS CLI on VPS:
apt install awscli -y

# Configure for ArvanCloud S3:
aws configure set aws_access_key_id YOUR_ARVAN_KEY
aws configure set aws_secret_access_key YOUR_ARVAN_SECRET
aws configure set region ir-thr-at1
aws configure set endpoint_url https://s3.ir-thr-at1.arvanstorage.ir

# Test upload:
aws s3 cp /var/www/saas-platform/storage/app/backups/db_backup_2026-06-23_020003.sql.gz \
    s3://your-bucket/backups/

# Add to /etc/cron.d/saas-offsite-backup:
10 02 * * * root aws s3 sync /var/www/saas-platform/storage/app/backups/ \
    s3://your-bucket/backups/ --delete --endpoint-url https://s3.ir-thr-at1.arvanstorage.ir
```

### Post-Launch: Media Backup

```bash
# Weekly rsync of customer uploads:
30 03 * * 0 root rsync -avz /var/www/customer-site/storage/app/public/ \
    backup-user@secondary:/backups/customer-media/

# Include lifecycle archives:
30 03 * * 0 root rsync -avz /var/www/saas-platform/storage/app/archives/ \
    backup-user@secondary:/backups/saas-archives/
```

---

## Monitoring: Backup Overdue Alert (IMPLEMENTED)

`ScanRuntimeAnomaliesCommand` now fires Slack critical alert if no successful backup in 26h:

```php
$lastBackup = InfraBackupRun::where('status', 'success')->latest()->first();
if (!$lastBackup || $lastBackup->created_at->lt(now()->subHours(26))) {
    $slack->critical('BACKUP_OVERDUE', 'No database backup in the last 26 hours');
}
```

This runs every 5 minutes. If the daily backup fails or the cron stops, the team will be alerted within 26 hours.

---

## Risk Assessment

| Risk | Severity | Status |
|------|----------|--------|
| DB backup only on VPS | CRITICAL | OPEN — manual action required before launch |
| Customer uploads not backed up | HIGH | OPEN — post-launch action |
| Archive ZIPs not backed up | MEDIUM | OPEN — post-launch action |
| Backup running daily | ✅ | VERIFIED |
| Backup overdue alert | ✅ | IMPLEMENTED |
| Disk space (43%) | ✅ SAFE | No action needed |

---

## Verdict

**BACKUP_RUNNING_OFFSITE_PENDING**  
Daily database backups are running and verified. Integrity checking, Slack alerting, and overdue detection are all operational.  
Critical gap: no offsite copy. This requires a one-time manual copy before DNS cutover and a scheduled offsite sync (S3 or secondary VPS) before launch.

---

*OFFSITE_BACKUP_STATUS — generated 2026-06-23*
