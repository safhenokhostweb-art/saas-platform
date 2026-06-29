# CUSTOMER DELETE PIPELINE

> Part of ENTERPRISE_DELETE_ENGINE (PHASE-003H)
> Last Updated: 2026-06-29

---

## Pipeline Stages

The pipeline executes 14 stages in order. Each stage is independently documented
with its purpose, criticality, idempotency guarantee, and verification method.

---

### Stage 1: PRE_DELETE_AUDIT

**Purpose**: Document the current state of every resource before any deletion begins.
**Critical**: YES — pipeline aborts if audit fails (audit data needed for all subsequent stages)
**Idempotent**: YES — read-only, safe to re-run
**What it does**:
- Records slug, domain, web root, DB credentials, supervisor/cron/nginx paths
- Probes remote server to confirm which resources actually exist
- Stores snapshot in `customer_deletions.audit_snapshot`

---

### Stage 2: FINAL_BACKUP

**Purpose**: Take a final mysqldump before deletion so data can be recovered if needed.
**Critical**: NO — non-fatal if backup fails
**Idempotent**: YES — creates a new timestamped file each time
**Policy-controlled**: Skipped when `DELETE_EVERYTHING`
**What it does**:
- `mysqldump {db_name} | gzip > {webRoot}/final_backup_{timestamp}.sql.gz`

---

### Stage 3: STOP_SERVICES

**Purpose**: Gracefully stop the Supervisor worker before removing its config.
**Critical**: NO
**Idempotent**: YES — `supervisorctl stop` on an already-stopped program is a no-op
**What it does**:
- `supervisorctl stop {slug}_worker`
- 2s sleep to let in-progress jobs drain

---

### Stage 4: REMOVE_QUEUE

**Purpose**: Remove Supervisor configuration and log for the customer worker.
**Critical**: NO
**Idempotent**: YES — `rm -f` is idempotent
**What it does**:
- `rm -f /etc/supervisor/conf.d/{slug}_worker.conf`
- `rm -f /var/log/supervisor/{slug}_worker.log`
- `supervisorctl reread && supervisorctl update`

---

### Stage 5: REMOVE_SCHEDULER

**Purpose**: Remove the customer's Laravel scheduler cron entry.
**Critical**: NO
**Idempotent**: YES
**What it does**:
- `rm -f /etc/cron.d/{slug}`

---

### Stage 6: REMOVE_WEBSERVER

**Purpose**: Remove the Nginx virtual host so the domain stops serving traffic.
**Critical**: NO
**Idempotent**: YES
**What it does**:
- `rm -f /etc/nginx/sites-enabled/{slug}`
- `rm -f /etc/nginx/sites-available/{slug}`
- `nginx -s reload`

---

### Stage 7: REMOVE_STORAGE

**Purpose**: Remove storage symlinks and the installed.lock sentinel before files are wiped.
**Critical**: NO
**Idempotent**: YES
**What it does**:
- `rm -f {webRoot}/public/storage`
- `rm -f {webRoot}/storage/installed.lock`
- `rm -rf {webRoot}/bootstrap/cache`

---

### Stage 8: REMOVE_FILES

**Purpose**: Remove the entire customer web root directory.
**Critical**: NO
**Idempotent**: YES — `rm -rf` on a non-existent path is a no-op
**What it does**:
- Records disk usage before removal (audit)
- `rm -rf /var/www/{slug}/`
- `rm -f /tmp/{slug}_*.zip /tmp/{slug}_*.tar.gz /tmp/{slug}.cie_installing`

---

### Stage 9: REMOVE_DATABASE

**Purpose**: Drop the customer's MySQL database.
**Critical**: NO
**Idempotent**: YES — `DROP DATABASE IF EXISTS`
**What it does**:
- `mysql -u root -e "DROP DATABASE IF EXISTS \`{dbName}\`"`
- Falls back to socket auth if `MYSQL_PWD=''` fails

---

### Stage 10: REMOVE_DATABASE_USER

**Purpose**: Drop the MySQL user created exclusively for this customer.
**Critical**: NO
**Idempotent**: YES — `DROP USER IF EXISTS`
**What it does**:
- `mysql -u root -e "DROP USER IF EXISTS '{dbUser}'@'localhost'; FLUSH PRIVILEGES;"`

---

### Stage 11: REMOVE_LICENSE

**Purpose**: Revoke the customer's license so their application fails auth immediately.
**Critical**: NO
**Idempotent**: YES — `PlatformLicense::delete()` on an absent record is a no-op
**What it does**:
- `PlatformLicense::where('license_key', $key)->delete()`
- `Site::find($siteId)->update(['license_key' => null])`

---

### Stage 12: REMOVE_PLATFORM_RECORDS

**Purpose**: Remove Site and Customer records from the platform database.
**Critical**: NO
**Idempotent**: YES — delete on absent records is a no-op
**Wrapped in**: `DB::transaction()`
**What it does**:
- `Site::find($siteId)->delete()`
- `Customer::find($customerId)->delete()` (only if no other sites)
- `CustomerInstallation::update(['status' => 'deleted'])` (soft-mark, logs preserved)

---

### Stage 13: REMOVE_RECOVERY_METADATA

**Purpose**: Policy-controlled cleanup of backup artifacts.
**Critical**: NO
**Idempotent**: YES
**Policy-controlled**:
- `NEVER_DELETE_BACKUP` → skipped
- `DELETE_LOCAL_BACKUPS` → deletes `LocalRecoveryLog` records + local files
- `DELETE_REMOTE_BACKUPS` → deletes Arvan S3 object
- `DELETE_EVERYTHING` → deletes both

---

### Stage 14: VERIFY_DELETE

**Purpose**: Comprehensive verification that nothing remains.
**Critical**: YES — failure means the deletion was incomplete
**Idempotent**: YES — verification is read-only
**Checks performed**:

| Check | Remote | Critical |
|-------|--------|----------|
| Web root removed | ✓ | YES |
| Nginx config removed | ✓ | YES |
| Nginx symlink removed | ✓ | NO |
| Supervisor config removed | ✓ | NO |
| Supervisor program gone | ✓ | NO |
| Cron entry removed | ✓ | NO |
| Database dropped | ✓ | YES |
| DB user dropped | ✓ | NO |
| Site record deleted | DB | YES |
| PlatformLicense deleted | DB | YES |
| Installation marked deleted | DB | NO |
| Temporary files cleaned | ✓ | NO |

---

## Failure Behavior

| Stage type | On failure |
|-----------|-----------|
| Critical | Pipeline aborts, `status=failed`, `failure_reason` recorded |
| Non-critical | Warning logged, pipeline continues to next stage |

A partial deletion (some stages failed, some succeeded) is resumable:
1. Fix the underlying issue
2. Initiate a new deletion (same installation_id is allowed if old deletion has `status=failed`)
3. The new run re-runs only the stages that weren't completed

---

## SSE Progress Stream

The progress view connects to `/deletions/{id}/stream` via `EventSource`.
Every stage transition emits a JSON event:

```json
{
  "deletion_id": 42,
  "stage": "REMOVE_FILES",
  "status": "completed",
  "message": "مرحله REMOVE_FILES کامل شد در 1823ms",
  "timestamp": "2026-06-29T..."
}
```

A terminal event closes the stream:
```json
{"event": "terminal", "status": "complete"}
```

The view auto-reloads on terminal `complete` to show the verification report.
