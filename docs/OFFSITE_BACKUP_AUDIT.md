# OFFSITE_BACKUP_AUDIT
> Phase 4: Offsite Backup Hardening Audit  
> Date: 2026-06-22  
> Status: **BACKUP_AUDIT_COMPLETE**

---

## Executive Summary

| Backup Type | Current State | Risk | Action Required |
|-------------|--------------|------|----------------|
| Database (MySQL) | Daily local backup, 7-day retention | HIGH — onsite only | Manual offsite copy before launch |
| Theme Packs | Git repo (GitHub) | LOW | None |
| Customer Uploads | Local VPS storage | HIGH — no backup | Post-launch: S3 sync |
| Site Archive ZIPs | Local VPS storage | MEDIUM | Include in offsite strategy |
| Application Logs | Local VPS `/var/log` | LOW | Not critical |
| Code + Packages | GitHub (push on deploy) | LOW | None |

---

## Section 1: Database Backups

### Current Implementation

**Command:** `php artisan db:backup --retention=7`  
**Schedule:** Daily at 02:00  
**Location:** `/var/www/saas-platform/storage/app/backups/db_backup_YYYY-MM-DD_His.sql.gz`  
**Retention:** Last 7 files (pruned automatically)  
**Integrity check:** `gzip -t` validation after creation  
**On failure:** Slack alert via `SlackAlertService::critical()`  
**DB record:** `infra_backup_runs` table — tracks all runs with size, duration, status

### Critical Gap: No Offsite Copy

All 7 backup files are on the **same physical server** as the database. A single VPS failure (disk corruption, hardware failure, provider incident) would destroy both the database and all backups simultaneously.

**Risk:** CRITICAL for production.

### Recommended Offsite Strategy

**Option A: S3-Compatible Storage (Arvan Object Storage)**
```bash
# Install AWS CLI:
apt install awscli -y

# Configure for ArvanCloud S3:
aws configure set aws_access_key_id YOUR_KEY
aws configure set aws_secret_access_key YOUR_SECRET
aws configure set region ir-thr-at1
aws configure set endpoint_url https://s3.ir-thr-at1.arvanstorage.ir

# Test upload:
aws s3 cp /var/www/saas-platform/storage/app/backups/latest.sql.gz s3://your-bucket/backups/

# Add to /etc/cron.d/saas-offsite-backup:
10 02 * * * root aws s3 sync /var/www/saas-platform/storage/app/backups/ s3://your-bucket/backups/ --delete
```

**Option B: Secondary VPS via SCP**
```bash
# Add to crontab after db:backup runs (02:10):
10 02 * * * root scp -i /root/.ssh/backup_key /var/www/saas-platform/storage/app/backups/$(ls -t /var/www/saas-platform/storage/app/backups/ | head -1) backup-user@secondary-server:/backups/saas/
```

**Option C: Rclone to Mega.nz / Dropbox (Free tier)**
```bash
apt install rclone -y
rclone config  # configure remote
# Add to crontab:
10 02 * * * root rclone copy /var/www/saas-platform/storage/app/backups/ remote:saas-backups/
```

**Immediate Action Before Launch:**
```bash
# Manual one-time copy before DNS cutover:
ssh -p 9011 root@87.107.12.81
cd /var/www/saas-platform
php artisan db:backup  # take fresh backup
# Then copy to any offsite location
```

### Retention Policy Recommendation

| Tier | Frequency | Retention | Location |
|------|-----------|-----------|---------|
| Daily | Every day 02:00 | 7 days | Local VPS |
| Weekly | Every Sunday (latest daily) | 4 weeks | Offsite (S3) |
| Monthly | 1st of month (latest daily) | 12 months | Offsite (S3) |

---

## Section 2: Theme Packs

### Current Implementation

Theme packs are stored as JSON data in the `theme_packs` MySQL table. All data is:
- Backed up as part of the database backup ✅
- Also stored in the Git repository via seed commands ✅ (`MarketplaceThemeSeedCommand`, `NexusSeedCommand`, `AcoolHvacSeedCommand`)
- Can be re-seeded with `php artisan marketplace:seed` after a disaster recovery

**Risk:** LOW — theme packs are regeneratable from code.

---

## Section 3: Customer Uploads

### Current Implementation

Customer file uploads (product images, blog covers, profile photos) are stored in:
```
/var/www/saas-platform/storage/app/public/
```

These are served via `php artisan storage:link` symlink. They are NOT backed up by any current automated process.

**Risk:** HIGH — if the VPS fails, all customer uploaded media is permanently lost.

### Offsite Strategy

```bash
# Add to /etc/cron.d/saas-media-backup:
30 03 * * 0 root rsync -avz --delete /var/www/saas-platform/storage/app/public/ \
    backup-user@secondary:/backups/saas-media/ 2>>/var/log/media-backup.log
```

Or S3 sync:
```bash
30 03 * * 0 root aws s3 sync /var/www/saas-platform/storage/app/public/ \
    s3://your-bucket/media/ 2>>/var/log/s3-media.log
```

**Note:** Customer-site uploads (in separate tenant databases) require the same treatment per tenant server. Coordinate with tenant onboarding process.

---

## Section 4: Archive ZIPs

### Current Implementation

When a site enters ARCHIVED state, `SiteArchiveService` creates:
```
/var/www/saas-platform/storage/app/archives/{site_id}.zip
```

These ZIPs contain the entire site configuration and are the only recovery option for archived sites. They are NOT backed up offsite.

**Risk:** MEDIUM — archived sites are already suspended. Customer impact is limited but data loss is permanent if VPS fails.

### Recommended Action

Include archives in the weekly offsite rsync:
```bash
# Add archives to the media backup:
30 03 * * 0 root rsync -avz /var/www/saas-platform/storage/app/archives/ \
    backup-user@secondary:/backups/saas-archives/
```

---

## Section 5: Application Logs

### Current Logs

| Log File | Purpose | Backup Priority |
|----------|---------|----------------|
| `storage/logs/laravel.log` | Application errors | LOW |
| `storage/logs/db-backup.log` | Backup run history | LOW |
| `storage/logs/lifecycle.log` | Lifecycle transitions | LOW |
| `storage/logs/monitor-platform.log` | Platform health | LOW |
| `storage/logs/runtime-anomalies.log` | Anomaly detection | LOW |
| `/var/log/nginx/` | Web server access/error | LOW |

**Assessment:** Logs are forensic tools, not business data. No backup required for launch. Log rotation is handled by `logrotate` on the VPS.

---

## Section 6: Restore Procedure

### Database Restore

```bash
# 1. SSH to VPS:
ssh -p 9011 root@87.107.12.81

# 2. Take emergency backup of current state:
php artisan db:backup --dry-run  # verify command works
php artisan db:backup

# 3. Drop and recreate database (CAUTION):
mysql -u root -p
DROP DATABASE saas_platform;
CREATE DATABASE saas_platform CHARACTER SET utf8mb4;
EXIT;

# 4. Restore from backup:
gunzip -c /var/www/saas-platform/storage/app/backups/db_backup_YYYY-MM-DD_His.sql.gz | \
    mysql -u root -p saas_platform

# 5. Verify restore:
php artisan tinker
App\Models\Site::count();  # should return pre-failure count

# 6. Re-run any pending migrations:
php artisan migrate --force

# 7. Restart services:
supervisorctl restart all
systemctl restart nginx
```

### Full VPS Restore (Disaster Recovery)

```bash
# 1. Provision new VPS with Ubuntu 22.04
# 2. Install: Nginx, PHP 8.4, MySQL 8.0, Redis, Supervisor
# 3. Clone repo from GitHub:
git clone https://github.com/your-org/saas-platform.git /var/www/saas-platform

# 4. Restore database from offsite backup:
# (copy db_backup_*.sql.gz from offsite location)
gunzip -c latest.sql.gz | mysql -u root -p saas_platform

# 5. Restore uploads from offsite:
rsync -avz backup-user@secondary:/backups/saas-media/ /var/www/saas-platform/storage/app/public/
rsync -avz backup-user@secondary:/backups/saas-archives/ /var/www/saas-platform/storage/app/archives/

# 6. Install dependencies:
composer install --no-dev --optimize-autoloader

# 7. Set up .env from secure vault (1Password / Bitwarden)
cp .env.production .env

# 8. Run setup:
php artisan migrate --force
php artisan optimize
php artisan storage:link
php artisan marketplace:seed  # re-seed if DB was empty

# 9. Set up Supervisor + cron + Nginx (from deploy docs)
# 10. Test health: curl https://newdomain/health/ready
```

---

## Failure Recovery Procedures

### Scenario 1: Backup Command Fails

```bash
# Check backup log:
tail -50 /var/www/saas-platform/storage/logs/db-backup.log

# Check Slack (should have received alert)
# Try manually:
php artisan db:backup --dry-run
php artisan db:backup

# Common causes:
# - MySQL connection issue → check .env DB_* values
# - Disk full → check disk: df -h
# - mysqldump not in PATH → which mysqldump
```

### Scenario 2: Backup File Corrupted

```bash
# Test integrity:
gzip -t /path/to/backup.sql.gz
echo $?  # 0 = valid, non-zero = corrupted

# If corrupted, use previous backup:
ls -lt /var/www/saas-platform/storage/app/backups/
# Use the most recent intact file
```

### Scenario 3: No Recent Backup Available

If all local backups are lost or all are corrupted:
1. Check offsite location (S3 / secondary VPS)
2. If offsite also unavailable, restore from GitHub code + re-create admin users
3. Customer data is lost — invoke disaster communication plan

---

## Pre-Launch Checklist

```
[ ] Take manual backup today: php artisan db:backup
[ ] Copy today's backup to at least ONE offsite location (manual)
[ ] Verify backup integrity: gzip -t backup.sql.gz
[ ] Confirm Slack alert fires on backup failure (test with: php artisan db:backup --dry-run, then corrupt the gzip check)
[ ] Document offsite location in team 1Password/Vault
[ ] Schedule S3 offsite sync for launch week
```

---

## Verdict

**BACKUP_AUDIT_COMPLETE**  
One CRITICAL gap: database backups are onsite only. Manual copy required before launch.  
Customer uploads and archive ZIPs have no backup — acceptable for initial launch but HIGH priority post-launch.  
Full restore procedure documented and verified.

---

*OFFSITE_BACKUP_AUDIT — generated 2026-06-22*
