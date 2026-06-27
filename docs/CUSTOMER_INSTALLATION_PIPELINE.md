# Customer Installation Pipeline — Detailed Design

**Document ID:** CIE-PIPE-001  
**Phase:** PHASE-002  
**Status:** ARCHITECTURE_COMPLETE  
**Date:** 2026-06-27

---

## 1. Pipeline Overview

The pipeline is a **sequential, resumable, rollback-capable** stage machine.

```
CustomerInstallationOrchestrator
    │
    ├─ dispatch CustomerInstallationPipelineJob
    │
    ▼
[Stage 01] VALIDATE_SERVER
    ▼
[Stage 02] VALIDATE_PHP
    ▼
[Stage 03] VALIDATE_EXTENSIONS
    ▼
[Stage 04] VALIDATE_DISK
    ▼
[Stage 05] VALIDATE_PERMISSIONS
    ▼
[Stage 06] UPLOAD_PACKAGE
    ▼
[Stage 07] VERIFY_PACKAGE
    ▼
[Stage 08] EXTRACT_PACKAGE
    ▼
[Stage 09] GENERATE_ENVIRONMENT
    ▼
[Stage 10] CREATE_DATABASE
    ▼
[Stage 11] RUN_MIGRATIONS
    ▼
[Stage 12] RUN_SEEDER
    ▼
[Stage 13] CONFIGURE_STORAGE
    ▼
[Stage 14] CONFIGURE_QUEUE
    ▼
[Stage 15] CONFIGURE_SCHEDULER
    ▼
[Stage 16] PROVISION_LICENSE
    ▼
[Stage 17] INITIALIZE_BACKUP
    ▼
[Stage 18] CONFIGURE_MONITORING
    ▼
[Stage 19] INSTALL_THEME
    ▼
[Stage 20] CUSTOMER_READY
```

---

## 2. Stage Specification

### Stage 01 — VALIDATE_SERVER

**Purpose:** Confirm the remote server is reachable and meets OS requirements.

**Pre-condition:** `RemoteConnectionInterface::connect()` succeeds.

**Checks:**
- SSH connectivity (timeout 10s)
- OS type: Linux (Debian/Ubuntu/CentOS accepted)
- Architecture: x86_64
- Server host key fingerprint matches stored `key_fingerprint`
- Bandwidth probe: upload 1KB test file, measure throughput

**Output:** `{ os, arch, bandwidth_kbps, ping_ms }`

**Rollback:** None (no state change on remote).

**Estimated time:** 5s

---

### Stage 02 — VALIDATE_PHP

**Purpose:** Confirm PHP CLI is present and meets version requirement (≥ 8.1).

**Checks:**
- `which php` or `php -v` resolves
- Version ≥ 8.1.0
- PHP-FPM service present (`systemctl is-active php8.*-fpm` or equivalent)

**Output:** `{ php_version, php_cli_path, fpm_active }`

**Rollback:** None.

**Estimated time:** 3s

---

### Stage 03 — VALIDATE_EXTENSIONS

**Purpose:** Confirm all required PHP extensions are installed.

**Required extensions:**
```
bcmath, ctype, curl, dom, fileinfo, gd,
intl, json, mbstring, openssl, pdo, pdo_mysql,
redis, tokenizer, xml, zip, pcntl, posix
```

**Check:** `php -m` output parsed against required list.

**On failure:** Report exactly which extensions are missing. Stage fails. Installation cannot proceed.

**Output:** `{ present: [...], missing: [...] }`

**Rollback:** None.

**Estimated time:** 3s

---

### Stage 04 — VALIDATE_DISK

**Purpose:** Confirm sufficient free disk space.

**Minimum requirements:**
- Web root partition: 2 GB free
- `/tmp` partition: 512 MB free (for package extraction)

**Check:** `df -B1` parsed for relevant mount points.

**Output:** `{ web_root_free_bytes, tmp_free_bytes }`

**Rollback:** None.

**Estimated time:** 2s

---

### Stage 05 — VALIDATE_PERMISSIONS

**Purpose:** Confirm the deployment user can write to target directories.

**Checks:**
- `/var/www/` writable (or configured web root)
- `/etc/supervisor/conf.d/` readable (for queue config — warn if not writable)
- `/etc/cron.d/` writable (warn if not)
- Test file write + delete in target web root

**Output:** `{ web_root_writable, supervisor_writable, cron_writable, warnings: [...] }`

**Rollback:** Remove test file.

**Estimated time:** 3s

---

### Stage 06 — UPLOAD_PACKAGE

**Purpose:** Transfer the verified release ZIP to the remote server.

**Steps:**
1. Download release ZIP from Arvan to platform temp directory (reuse `ArvanBackupService::download()`)
2. Record local SHA-256 checksum
3. SFTP upload to `/tmp/customer_install_{uuid}.zip` on remote
4. Report progress via Redis channel (for SSE streaming)

**Uses:** `CustomerPackageUploadJob` for large file transfer.

**Output:** `{ remote_path, local_sha256, upload_duration_ms, size_bytes }`

**Rollback:** `rm -f /tmp/customer_install_{uuid}.zip` on remote.

**Estimated time:** 30–120s (network-dependent)

---

### Stage 07 — VERIFY_PACKAGE

**Purpose:** Confirm the uploaded package integrity before extraction.

**Steps:**
1. `sha256sum /tmp/customer_install_{uuid}.zip` on remote
2. Compare against `customer_installation_packages.sha256`
3. Verify remote checksum matches `PackageManifest.sha256`

**Uses:** `BaselineManifestService` logic pattern (SHA-256 comparison).

**On failure:** Stage fails immediately. Pipeline aborts. Do not extract a corrupted package.

**Output:** `{ remote_sha256, expected_sha256, verified: true/false }`

**Rollback:** Delete the uploaded ZIP.

**Estimated time:** 5s

---

### Stage 08 — EXTRACT_PACKAGE

**Purpose:** Extract the release ZIP to the target web root.

**Steps:**
1. `mkdir -p /var/www/{customer_slug}/`
2. `unzip -q /tmp/customer_install_{uuid}.zip -d /var/www/{customer_slug}/`
3. `rm -f /tmp/customer_install_{uuid}.zip`
4. Set ownership: `chown -R www-data:www-data /var/www/{customer_slug}/`
5. Set permissions: `find . -type f -exec chmod 644 {} \;` and `find . -type d -exec chmod 755 {} \;`

**Output:** `{ web_root, extracted_files_count, duration_ms }`

**Rollback:** `rm -rf /var/www/{customer_slug}/`

**Estimated time:** 15s

---

### Stage 09 — GENERATE_ENVIRONMENT

**Purpose:** Write the customer `.env` file.

**Delegates to:** `CustomerEnvironmentGenerator` (wraps `EnvironmentWriter`)

**Generated keys:**
```
APP_NAME={company}
APP_ENV=production
APP_KEY=base64:{generated}
APP_URL=https://{domain}
DB_HOST=127.0.0.1
DB_DATABASE={db_name}
DB_USERNAME={db_user}
DB_PASSWORD={db_password}
REDIS_HOST=127.0.0.1
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
MAIL_HOST={smtp.host}
MAIL_PORT={smtp.port}
MAIL_USERNAME={smtp.user}
MAIL_PASSWORD={smtp.pass}
MAIL_ENCRYPTION={smtp.encryption}
PLATFORM_URL=https://panel.safhenokhost.ir
LICENSE_KEY={license_key}
SITE_TIMEZONE={timezone}
SITE_LANGUAGE={language}
SITE_CURRENCY={currency}
MODULE_EDUCATION_ENABLED={true/false}
MODULE_FORMS_ENABLED={true/false}
MODULE_TICKETS_ENABLED={true/false}
```

**Output:** `{ env_path, keys_written, app_key_generated }`

**Rollback:** `rm -f /var/www/{customer_slug}/.env`

**Estimated time:** 3s

---

### Stage 10 — CREATE_DATABASE

**Purpose:** Create the customer's MySQL database and user.

**Steps:**
1. Generate unique `db_name` = `cust_{slug}_{random6}`
2. Generate random `db_password` (32 chars)
3. Execute on remote MySQL: `CREATE DATABASE IF NOT EXISTS {db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
4. `CREATE USER '{db_user}'@'localhost' IDENTIFIED BY '{db_password}'`
5. `GRANT ALL PRIVILEGES ON {db_name}.* TO '{db_user}'@'localhost'`
6. Record credentials encrypted in installation state

**Output:** `{ db_name, db_user }` (no password in output/logs)

**Rollback:**
```sql
DROP DATABASE IF EXISTS {db_name};
DROP USER IF EXISTS '{db_user}'@'localhost';
```

**Estimated time:** 3s

---

### Stage 11 — RUN_MIGRATIONS

**Purpose:** Run all Laravel migrations on the remote customer site.

**Steps:**
1. `cd /var/www/{customer_slug} && php artisan migrate --force 2>&1`
2. Parse output for errors
3. Verify: `php artisan migrate:status` — all migrations must show [Ran]

**Output:** `{ migrations_run, status: pass/fail, output }`

**Rollback:** `php artisan migrate:rollback --step=9999 --force` (best-effort, then DROP DATABASE)

**Estimated time:** 20s

---

### Stage 12 — RUN_SEEDER

**Purpose:** Seed initial platform data for the customer.

**Steps:**
1. `php artisan db:seed --class=CustomerPlatformSeeder --force`
2. Seeder creates: default admin user (email from wizard), default plan, platform settings, initial SMTP config

**Output:** `{ seeder_class, records_created, duration_ms }`

**Rollback:** No rollback (DB will be dropped if pipeline fails past this point).

**Estimated time:** 10s

---

### Stage 13 — CONFIGURE_STORAGE

**Purpose:** Set up storage symlinks and required directories.

**Steps:**
1. `php artisan storage:link`
2. `mkdir -p storage/app/public storage/logs storage/framework/{cache,sessions,views}`
3. `chmod -R 775 storage bootstrap/cache`
4. `chown -R www-data:www-data storage bootstrap/cache`

**Output:** `{ symlink_created, directories_created, permissions_set }`

**Rollback:** None (storage setup is idempotent).

**Estimated time:** 5s

---

### Stage 14 — CONFIGURE_QUEUE

**Purpose:** Install and start a Supervisor worker for the customer's queue.

**Steps:**
1. Generate Supervisor config file: `/etc/supervisor/conf.d/cust_{slug}_worker.conf`
2. Content:
   ```
   [program:cust_{slug}_worker]
   command=php /var/www/{customer_slug}/artisan queue:work redis --sleep=3 --tries=3
   autostart=true
   autorestart=true
   user=www-data
   numprocs=1
   redirect_stderr=true
   stdout_logfile=/var/log/supervisor/cust_{slug}_worker.log
   ```
3. `supervisorctl reread && supervisorctl update`
4. `supervisorctl status cust_{slug}_worker` — confirm RUNNING

**Output:** `{ config_path, worker_status }`

**Rollback:**
```bash
rm -f /etc/supervisor/conf.d/cust_{slug}_worker.conf
supervisorctl reread && supervisorctl update
```

**Estimated time:** 8s

---

### Stage 15 — CONFIGURE_SCHEDULER

**Purpose:** Add a cron entry for the customer site's scheduler.

**Steps:**
1. Cron entry: `* * * * * www-data cd /var/www/{customer_slug} && php artisan schedule:run >> /dev/null 2>&1`
2. Write to `/etc/cron.d/cust_{slug}`
3. `chmod 644 /etc/cron.d/cust_{slug}`

**Output:** `{ cron_path, entry }`

**Rollback:** `rm -f /etc/cron.d/cust_{slug}`

**Estimated time:** 3s

---

### Stage 16 — PROVISION_LICENSE

**Purpose:** Register the customer license in the platform and push it to the customer site.

**Steps:**
1. Create `Site` record in platform DB: domain, company, owner, email, modules
2. Create `License` record with generated `license_key`
3. Write `LICENSE_KEY` to customer `.env`
4. Call `php artisan license:validate` on remote — must return OK
5. Store `site_id` on `customer_installations` record

**Output:** `{ site_id, license_key_prefix (first 8 chars only) }`

**Rollback:**
- Delete `Site` record
- Delete `License` record
- Remove `LICENSE_KEY` from `.env`

**Estimated time:** 5s

---

### Stage 17 — INITIALIZE_BACKUP

**Purpose:** Register the customer site's backup policy with the platform backup engine.

**Steps:**
1. Configure backup schedule in customer `.env`: `BACKUP_ENABLED=true`, `BACKUP_RETENTION={days}`
2. Store backup config in platform: Arvan prefix `customer-sites/{site_id}/`
3. Trigger first manual backup: `php artisan db:backup --site={site_id}` on remote
4. Verify backup appears in Arvan

**Uses:** `ArvanBackupService` for verification.

**Output:** `{ backup_enabled, first_backup_key, backup_size_bytes }`

**Rollback:** Deactivate backup config (non-destructive).

**Estimated time:** 30s

---

### Stage 18 — CONFIGURE_MONITORING

**Purpose:** Register the customer site with the platform's health monitoring system.

**Steps:**
1. Add `site_id` to monitoring schedule: `monitor:sites` command already scans all active sites
2. Perform initial health check: `DrReadinessScoreService::score(site_id)` — must be ≥ 70
3. Record baseline health score in `site_health_logs`
4. Confirm `/health/ready` endpoint returns `ready: true` on customer site

**Uses:** `DrReadinessScoreService`.

**Output:** `{ initial_health_score, health_endpoint_ok }`

**Rollback:** Remove site from monitoring (mark inactive).

**Estimated time:** 10s

---

### Stage 19 — INSTALL_THEME

**Purpose:** Hook point for theme installation. Non-blocking.

**Steps:**
1. If `themeSlug` is empty → mark as SKIPPED
2. Fire `CustomerThemeInstalled` event with `(themeSlug, InstallationContext)`
3. If no listener → SKIPPED (not failure)
4. If listener present → execute, catch exceptions as WARNING (not FAIL)

**Output:** `{ theme_slug, status: installed / skipped / warning, warning_message? }`

**Rollback:** Fire `CustomerThemeRollback` event (same pattern).

**Estimated time:** 0–30s

---

### Stage 20 — CUSTOMER_READY

**Purpose:** Final verification and notification.

**Steps:**
1. HTTP GET `https://{domain}/` — expect HTTP 200
2. HTTP GET `https://{domain}/health/ready` — expect `ready: true`
3. Mark `customer_installations.status = complete`
4. Set `customer_installations.completed_at = now()`
5. Fire `CustomerInstallationCompleted` event
6. Send SMS to customer phone: "سایت شما آماده است: {domain}"
7. Send Slack alert to platform ops channel: `[INSTALL] {company} @ {domain} — complete`
8. Email welcome message to customer email

**Output:** `{ site_url, health_check_ok, http_status, notifications_sent }`

**Rollback:** None (completion stage).

**Estimated time:** 10s

---

## 3. Mode-Specific Pipeline Variants

### MODE_FRESH

Full pipeline: all 20 stages in order.

### MODE_RESTORE

Stages 01–09 execute normally (validate + upload + extract + env).

After Stage 09:
- **RESTORE_DATABASE:** `RestoreService::restoreTenantDatabase(backup_key, site_id)` — reuses existing service
- **RESTORE_STORAGE:** `RestoreService::restoreTenantStorage(backup_key, site_id)` — reuses existing service

Then stages 13–20 resume.

Stages 10–12 (CREATE_DATABASE, RUN_MIGRATIONS, RUN_SEEDER) are **skipped** — data comes from backup.

### MODE_CLONE

Stages 01–09 execute on the target server.

Additional stages:
- **EXPORT_SOURCE:** Trigger backup on source installation (uses `ArvanBackupService`)
- **IMPORT_TO_TARGET:** Restore from that backup on target (uses `RestoreService`)
- **ANONYMIZE_IF_REQUIRED:** Fire `CustomerCloneAnonymize` event for GDPR compliance

Then stages 13–20 resume.

### MODE_MOVE

Stages 01–09 execute on the new server.

Additional stages:
- **BACKUP_SOURCE:** Force backup of source customer on old server
- **RESTORE_ON_TARGET:** Full restore on new server
- **DNS_CUTOVER_READY:** Verify new server responds correctly, output DNS instructions
- **DECOMMISSION_SOURCE:** After confirmation (manual gate), remove source configuration

Then stages 13–20 resume.

**Note:** DNS cutover requires a manual gate — this is the one human confirmation step in the pipeline.

### MODE_REINSTALL

- Trigger full backup of existing customer (backup policy must be active)
- Run stages 01–20 on same server path (overwrites files, preserves DB user/credentials)
- Restore from pre-reinstall backup (stages after 09)

---

## 4. Error Handling

| Scenario | Behavior |
|----------|----------|
| Stage fails → rollback possible | Execute `CustomerInstallationRollbackJob` on rollback stack |
| Stage fails → rollback not possible | Mark installation as failed, alert ops via Slack, log full context |
| SSH disconnects mid-stage | Stage marked failed, pipeline paused, reconnect attempt on next step start |
| Package SHA-256 mismatch | Hard fail — never extract unverified package |
| DB creation fails | Hard fail — rollback attempted |
| Remote command timeout | Stage marked failed, logged |
| Rollback fails | Log rollback failure, mark status as `rollback_failed`, immediate Slack alert |

---

## 5. Resume After Failure

The pipeline state is persisted to `customer_installations.completed_stages` (JSON array) after every successful stage. If a job dies mid-pipeline:

1. Admin reviews failed stage in Installation Center
2. Clicks "Resume" → re-dispatches `CustomerInstallationPipelineJob` with `resume=true`
3. Orchestrator reads `completed_stages`, skips them, resumes from failed stage

This allows recovery from transient failures (network drop, server restart) without re-running completed stages.

---

## 6. Progress Streaming

Pipeline stages emit Redis `RPUSH` to `install_progress_{installation_id}` list.

Admin panel reads via SSE endpoint: `GET /admin/infrastructure/customer-deployment/{id}/stream`

Payload per event:
```json
{
  "stage": "UPLOAD_PACKAGE",
  "status": "running",
  "progress_pct": 45,
  "message": "آپلود بسته: ۴۵٪",
  "timestamp": "2026-06-27T14:30:00Z"
}
```

---

## 7. Estimated Time per Mode

| Mode | Min | Max | Typical |
|------|-----|-----|---------|
| MODE_FRESH | 3 min | 8 min | 5 min |
| MODE_RESTORE | 5 min | 20 min | 10 min |
| MODE_CLONE | 8 min | 30 min | 15 min |
| MODE_MOVE | 10 min | 45 min | 20 min |
| MODE_REINSTALL | 8 min | 25 min | 12 min |

Times vary by network bandwidth and database size.

---

_Generated: 2026-06-27 | Phase: PHASE-002 | CIE-PIPE-001_
