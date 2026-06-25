# OFFSITE BACKUP FINAL AUDIT
> Date: 2026-06-25
> Auditor: Claude (automated verification)
> Status: **VPS_ONLY — OFFSITE MANUAL PROCEDURE DOCUMENTED**

---

## Executive Summary

The platform has **daily automated backups** for the primary platform database (`saas_platform`), running correctly since 2026-06-19. However:

1. The `customer_site` database is **NOT backed up automatically** — HIGH risk
2. All backups are **VPS-local only** — no offsite copy exists
3. Application files (themes, uploads) are **not separately archived**

This document provides the exact manual procedure to secure backups before DNS cutover.

---

## Current Backup State

### Automated Backup (saas_platform DB)

| Item | Value |
|------|-------|
| Location | `/var/www/saas-platform/storage/app/backups/` |
| Schedule | Daily at 02:00 server time (via `/etc/cron.d/laravel-scheduler`) |
| Retention | 7 days |
| Format | `.sql.gz` (gzip compressed) |
| Integrity | ✅ VERIFIED (gzip -t passed for latest backup) |

**Available backups (verified 2026-06-25):**

```
db_backup_2026-06-19_020003.sql.gz   181K
db_backup_2026-06-20_020003.sql.gz   206K
db_backup_2026-06-21_020002.sql.gz   255K
db_backup_2026-06-22_020002.sql.gz   307K
db_backup_2026-06-23_020003.sql.gz   353K
db_backup_2026-06-24_020003.sql.gz   378K
db_backup_2026-06-25_020003.sql.gz   374K   ← today
```

Total: 2.1 MB for 7 days of platform data

### customer_site Database — NOT BACKED UP

| Item | Value |
|------|-------|
| Database | `customer_site` |
| Size | 5.03 MB |
| Automated backup | ❌ NONE |
| Risk | HIGH — tenant orders, users, CRM, installments, courses, SMS campaigns |

**What `customer_site` contains:**
- Customer orders, products, courses, enrollments
- CRM contacts and deals
- SMS campaigns and recipient logs
- Installment plans and payments
- Canvas themes and page content (active site configuration)

### Storage / Uploads

| Item | Status |
|------|--------|
| Platform uploads | `/var/www/saas-platform/storage/app/public/` — VPS only |
| Customer uploads | `/var/www/customer-site/storage/app/public/` — VPS only |
| Theme ZIP archives | `/var/www/saas-platform/storage/app/archives/` — VPS only |
| Marketplace themes | In `saas_platform` DB → backed up ✅ |

---

## Risk Assessment

| Item | Risk | Status |
|------|------|--------|
| saas_platform DB | Low — daily backup exists | ✅ BACKED UP |
| customer_site DB | HIGH — no automated backup | ❌ GAP |
| Offsite copy | HIGH — VPS disk failure = total loss | ❌ VPS ONLY |
| Upload files | MEDIUM — not backed up separately | ⚠️ VPS ONLY |
| Disk failure | HIGH — all data on single VPS disk | ⚠️ NO REDUNDANCY |

---

## Pre-Launch Manual Backup Procedure

Run this **before DNS cutover** on the day of launch. Estimated time: 15 minutes.

### Step 1 — SSH to server

```bash
ssh -i ~/.ssh/deploy_saas -p 9011 root@87.107.12.81
```

### Step 2 — Backup both databases

```bash
# saas_platform database
mysqldump saas_platform | gzip > /tmp/LAUNCH_saas_platform_$(date +%Y%m%d_%H%M%S).sql.gz

# customer_site database  
mysqldump customer_site | gzip > /tmp/LAUNCH_customer_site_$(date +%Y%m%d_%H%M%S).sql.gz

# Verify both files
ls -lh /tmp/LAUNCH_*.sql.gz
gzip -t /tmp/LAUNCH_saas_platform_*.sql.gz && echo "saas_platform OK"
gzip -t /tmp/LAUNCH_customer_site_*.sql.gz && echo "customer_site OK"
```

### Step 3 — Backup uploads

```bash
# Archive upload directories
tar -czf /tmp/LAUNCH_uploads_saas_$(date +%Y%m%d).tar.gz \
    /var/www/saas-platform/storage/app/public/ 2>/dev/null

tar -czf /tmp/LAUNCH_uploads_customer_$(date +%Y%m%d).tar.gz \
    /var/www/customer-site/storage/app/public/ 2>/dev/null

ls -lh /tmp/LAUNCH_*.tar.gz
```

### Step 4 — Transfer to local machine (run on LOCAL machine)

```powershell
# Run on your Windows machine
$SSH_KEY = "$env:USERPROFILE\.ssh\deploy_saas"
$DATE = Get-Date -Format "yyyyMMdd"
$DEST = "C:\backups\launch-$DATE\"
New-Item -ItemType Directory -Force -Path $DEST

# Download all launch backups
scp -i $SSH_KEY -P 9011 root@87.107.12.81:/tmp/LAUNCH_*.sql.gz $DEST
scp -i $SSH_KEY -P 9011 root@87.107.12.81:/tmp/LAUNCH_*.tar.gz $DEST

# Verify downloads
Get-ChildItem $DEST | Format-Table Name, Length, LastWriteTime
```

### Step 5 — Also transfer daily backups

```powershell
# Download the 7-day rolling backups as well
scp -i $SSH_KEY -P 9011 "root@87.107.12.81:/var/www/saas-platform/storage/app/backups/*.sql.gz" $DEST
```

### Step 6 — Upload to cloud storage (recommended)

```powershell
# Option A: Copy to external hard drive
Copy-Item $DEST "D:\offsite-backup\launch-$DATE\" -Recurse

# Option B: Upload to Arvan Object Storage (S3-compatible)
# aws s3 cp $DEST s3://your-bucket/launch-backup/ --recursive
# arvan s3 cp $DEST s3://your-bucket/launch-backup/ --recursive

# Option C: Upload to any SFTP/FTP offsite server
```

### Step 7 — Cleanup temporary files on server

```bash
# After confirming local copies are safe
rm /tmp/LAUNCH_*.sql.gz /tmp/LAUNCH_*.tar.gz
```

---

## Post-Launch Recommendations

### Immediate (before DNS cutover)
- [ ] Run Step 1–6 above
- [ ] Confirm both SQL files are > 0 bytes and gzip-valid

### Short-term (within 1 week of launch)
- [ ] Add automated `customer_site` backup to `BackupDatabaseCommand`
- [ ] Configure Arvan Object Storage or any S3-compatible bucket
- [ ] Add offsite sync to deploy.sh or BackupDatabaseCommand

### Recommended BackupDatabaseCommand Enhancement

Edit `app/Console/Commands/BackupDatabaseCommand.php` to add:

```php
// Back up customer_site DB as well
$customerDbName = config('database.connections.mysql.database', 'customer_site');
// ... run mysqldump for customer_site DB
```

---

## Restoration Procedure

If restoration is needed:

```bash
# Restore saas_platform
gunzip < db_backup_YYYYMMDD.sql.gz | mysql saas_platform

# Restore customer_site
gunzip < LAUNCH_customer_site_YYYYMMDD.sql.gz | mysql customer_site

# Restore uploads (run as www-data)
tar -xzf LAUNCH_uploads_saas_YYYYMMDD.tar.gz -C /
tar -xzf LAUNCH_uploads_customer_YYYYMMDD.tar.gz -C /

# Flush caches
cd /var/www/saas-platform && php artisan cache:clear && php artisan config:cache
cd /var/www/customer-site && php artisan cache:clear && php artisan config:cache

# Restart workers
supervisorctl restart all
```

---

## Certification

| Item | Status |
|------|--------|
| saas_platform daily backup | ✅ OPERATIONAL (7/7 days verified) |
| Backup integrity | ✅ PASS (gzip -t verified) |
| customer_site backup | ❌ NOT AUTOMATED |
| Offsite copy | ❌ VPS ONLY |
| Manual procedure documented | ✅ DOCUMENTED ABOVE |

**Verdict: VPS_ONLY — Manual procedure must be executed before DNS cutover.**

> Action required before go-live: Execute Steps 1–6 of the manual backup procedure above.
