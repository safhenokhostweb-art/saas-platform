# CORE CERTIFICATION MATRIX
> CORE_CERTIFICATION_PHASE_1 — 2026-06-27
> Auditor: Claude Code (claude-sonnet-4-6)
> Method: Full source-code inspection, no assumptions

---

## Certification Scope

| # | Subsystem | Files Audited |
|---|-----------|---------------|
| 1 | Installer Foundation | BaselineManifestService, UpgradeAdapter, InstallationReportService, InstallerAdminController, PlatformInstallerController |
| 2 | Backup Engine | ArvanBackupService, BackupDatabaseCommand, BackupStorageCommand |
| 3 | Restore Foundation | RestoreService, RestoreTenantDatabaseJob, RestorePlatformStorageJob |
| 4 | Disaster Recovery Center | DrReadinessScoreService, DrIntegrityService, ScanDrHealthCommand |
| 5 | Tenant Isolation | Site model, RestoreService tenant flow, platform rules |
| 6 | License System | LicenseServerService, PlatformLicense model |
| 7 | Queue System | config/queue.php, Supervisor deploy log |
| 8 | Scheduler | Console/Kernel.php, ScanRuntimeAnomaliesCommand |
| 9 | Finance Core | FinanceLedgerService, FinanceLedgerEntry model |
| 10 | Security | SlackAlertService, path-traversal guards, RestoreService exec() |
| 11 | Performance | Cross-service N+1 patterns, caching gaps |
| 12 | Scalability | Queue config, retry_after, batch limits |

---

## Subsystem Certification Results

### 1. Installer Foundation

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | LOW |
| **Score** | 82/100 |
| **Can Core Be Frozen?** | YES — risks are non-blocking |

**Strengths:**
- INITIAL baseline never overwritten (`resolveBaselineName()` immutable)
- SHA-256 streaming checksums — memory-safe for large databases
- Self-covering manifest checksum (body → SHA-256 → field added)
- Two-phase baseline: Phase 1 (core), Phase 2 (reports + lock)
- Path traversal guard: `realpath()` + `str_starts_with($real, $root)`
- Read-only lock: `chmod(0444)` / `chmod(0555)` via RecursiveIterator
- Admin panel is fully read-only (no delete / overwrite endpoints)

**Detected Weaknesses:**
- W-INS-01: `install.lock` written without atomic file locking — two simultaneous requests could produce partial JSON
- W-INS-02: No Slack/monitoring alert if `createInstallationBaseline()` fails in `processFreshFinish()`
- W-INS-03: `REQUIRED_FILES` check does not include `manifest.json` itself — a Phase 1 partial failure (manifest missing) would only be caught at full verify, not at `requiredFilesPresent()` step
- W-INS-04: `makeReadOnly()` is a best-effort on Linux; no verification that chmod succeeded

**Required Fixes:**
- FIX-INS-01: Add `manifest.json` to the `REQUIRED_FILES` array or add a separate manifest-present check before files-present check
- FIX-INS-02: Wrap `install.lock` write with `flock()` exclusive lock

---

### 2. Backup Engine

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | MEDIUM |
| **Score** | 72/100 |
| **Can Core Be Frozen?** | NO — Arvan retention leak and tenant backup gap are blocking |

**Strengths:**
- `--single-transaction --quick --lock-tables=false` — zero downtime backup
- 3 retries on Arvan upload with size verification (local vs remote)
- Streaming upload via `Storage::put()` — memory-safe
- Non-blocking Arvan failure (local backup primary)
- Slack alert on backup failure
- `filesize < 32` threshold prevents empty-file false success

**Detected Weaknesses:**
- W-BAK-01 CRITICAL: **No Arvan retention pruning** — `pruneOldBackups()` only prunes local files. Arvan accumulates indefinitely. Storage cost is unbounded.
- W-BAK-02 HIGH: **No tenant database backup** — `DrIntegrityService::checkTenantDb()` comments "tenant backup not implemented yet". All tenant databases are unprotected.
- W-BAK-03 MEDIUM: `listFiles()` makes N individual `size()` S3 API calls — O(N) HTTP requests per DR scan
- W-BAK-04 MEDIUM: No checksum on download — `download()` has no SHA-256 verify after transfer; corruption during download is undetected
- W-BAK-05 LOW: `filesize < 32` threshold too low — partial mysqldump gzip (truncated mid-table) could be larger than 32 bytes but still corrupt
- W-BAK-06 LOW: Storage backup is weekly (Sunday only) — no configurable frequency; a week of file changes could be lost

**Required Fixes:**
- FIX-BAK-01 CRITICAL: Implement Arvan retention prune (e.g., keep last N files per prefix)
- FIX-BAK-02 HIGH: Implement tenant database backup command
- FIX-BAK-03 MEDIUM: Compute SHA-256 at backup time; store in `infra_backup_runs`; verify on download

---

### 3. Restore Foundation

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | HIGH |
| **Score** | 68/100 |
| **Can Core Be Frozen?** | NO — platform DB restore execution is not implemented |

**Strengths:**
- Redis lock (`NX + TTL=30min`) prevents concurrent restores
- Pre-restore snapshot before any destructive operation
- Dry-run mode with full step preview (no DB/file side effects)
- `gzip -t` and `tar -tzf` integrity verification before restore
- Temporary DB staging for tenant restore (never directly overwrites production)
- Activity log on every restore operation

**Detected Weaknesses:**
- W-RST-01 CRITICAL: **`executePlatformDatabaseRestore()` is not implemented** — dry-run describes 12 steps, but the actual execution method does not exist in RestoreService. Platform DB restore is non-functional.
- W-RST-02 HIGH: `deleteTenantData()` runs without a DB transaction — partial deletion leaves inconsistent tenant state on error
- W-RST-03 HIGH: `copyTenantData()` uses `SELECT *` — schema drift after migrations (new/renamed columns) causes silent data loss or INSERT failures
- W-RST-04 MEDIUM: `createDatabaseSnapshot()` writes to `sys_get_temp_dir()` — on production `/tmp` can be on a small tmpfs; large DB snapshots may fail with out-of-space
- W-RST-05 MEDIUM: Queue `retry_after => 90` seconds — restore jobs for large databases run far longer; expired TTL triggers duplicate retry execution
- W-RST-06 MEDIUM: Lock TTL alert math in `ScanDrHealthCommand` uses hardcoded `3600s` (age = 3600 - remaining), but tenant restore lock TTL is 1800s — alert fires 30min early for tenant restores
- W-RST-07 LOW: Password passed as `-p$password` CLI argument — visible in `ps aux` on Linux

**Required Fixes:**
- FIX-RST-01 CRITICAL: Implement `executePlatformDatabaseRestore()` or mark Phase 3 (Restore Engine) as officially required
- FIX-RST-02 HIGH: Wrap `deleteTenantData()` + `copyTenantData()` in a single DB transaction
- FIX-RST-03 HIGH: Extend queue `retry_after` to at least 3600 for the `restore` queue

---

### 4. Disaster Recovery Center

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | MEDIUM |
| **Score** | 70/100 |
| **Can Core Be Frozen?** | YES — alerts work, scoring works; weaknesses are edge cases |

**Strengths:**
- 10-component weighted scoring (0-100 scale)
- Hourly DR health scan via Supervisor scheduler
- Slack alert deduplication (6h silence window per alert type)
- Size-match integrity check for platform DB (1MB tolerance)
- Multi-level degradation: OK/WARN/STALE/CRITICAL/MISSING

**Detected Weaknesses:**
- W-DR-01 HIGH: `evalQueue()` infers queue health from backup completion — never pings a live worker or checks queue depth; stale inference
- W-DR-02 HIGH: `evalRestoreLogs()` penalizes 0/10 if no restore done in 30 days — a healthy fresh system with no restore history always scores 0 here (biased scoring for new installs)
- W-DR-03 MEDIUM: `evalArvanSync()` caches Arvan file listing for 5 minutes — Arvan gaps are hidden for up to 5 minutes during hourly health scan
- W-DR-04 MEDIUM: `checkPlatformStorage()` only checks existence on Arvan, not size match
- W-DR-05 MEDIUM: `checkTenantStorage()` returns OK if any file exists on Arvan — no correlation with backup run records
- W-DR-06 MEDIUM: ALERT-007 lock-stuck calculation uses hardcoded 3600s (see W-RST-06)
- W-DR-07 LOW: No ALERT-002 for consecutive failed backup attempts (only stale success is alerted)

**Required Fixes:**
- FIX-DR-01: Fix `evalRestoreLogs()` to give partial credit (5/10) if no restore needed (fresh install < 90 days)
- FIX-DR-02: Fix ALERT-007 age calculation to use the actual lock TTL

---

### 5. Tenant Isolation

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | MEDIUM |
| **Score** | 78/100 |
| **Can Core Be Frozen?** | YES — structural isolation is sound; weaknesses are in restore flow |

**Strengths:**
- `site_id` column on every content table (structural, not optional)
- `whereNull('site_id')` for platform admin (correct MySQL behavior)
- Tenant restore uses separate temporary DB — never overwrites production directly
- `getTenantTables()` auto-discovers via information_schema (not hardcoded)
- Package boundary enforced: no `App\` imports in `packages/`

**Detected Weaknesses:**
- W-ISO-01 MEDIUM: `getTenantTables()` uses string interpolation `TABLE_SCHEMA='{$db}'` — not parameterized; though `$db` comes from config (not user input), this is fragile pattern
- W-ISO-02 MEDIUM: `deleteTenantData()` has no transaction — partial deletion leaves inconsistent cross-table state (shared with W-RST-02)
- W-ISO-03 MEDIUM: `copyTenantData()` skips tables where dest check fails — pivot tables or tables without `site_id` in backup are silently lost
- W-ISO-04 LOW: No integration test verifying that a tenant restore cannot contaminate another tenant's rows

**Required Fixes:**
- FIX-ISO-01: Add integration test for cross-tenant contamination guard during restore
- FIX-ISO-02: Transaction wrapping for delete + copy operations (shared with FIX-RST-02)

---

### 6. License System

| Attribute | Value |
|-----------|-------|
| **Status** | PASS |
| **Risk Level** | LOW |
| **Score** | 88/100 |
| **Can Core Be Frozen?** | YES |

**Strengths:**
- HMAC-SHA256 with APP_KEY as secret — rainbow tables ineffective
- Lazy rehash from SHA256 to HMAC on first validate (no migration needed)
- Key prefix (8 chars) for O(log N) DB lookup instead of full table scan
- Domain validation, expiry check, revocation check, site active check
- Full try/catch — never throws to caller
- Key never stored in cleartext
- Schema::hasTable guard for graceful degradation

**Detected Weaknesses:**
- W-LIC-01 MEDIUM: No rate limiting on `/api/v1/license/validate` endpoint — brute-force attempt possible
- W-LIC-02 LOW: No revocation audit trail (who revoked, when, reason)
- W-LIC-03 LOW: `domain_allowed` is a single string — no wildcard or multiple domain support
- W-LIC-04 LOW: Lazy rehash failure is logged as warning but the rehash will be retried on every subsequent validate call until it succeeds

**Required Fixes:**
- FIX-LIC-01: Add throttle middleware (e.g., 60/min) to license validate route

---

### 7. Queue System

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | HIGH |
| **Score** | 65/100 |
| **Can Core Be Frozen?** | NO — default sync driver and retry_after gap are blocking |

**Strengths:**
- Supervisor manages two saas-platform workers + customer-site worker
- `QUEUE_FAILED_DRIVER=database-uuids` for failed job persistence
- Restore jobs are properly dispatched to the `restore` queue
- `withoutOverlapping()` on all scheduled commands prevents queue storms

**Detected Weaknesses:**
- W-QUE-01 CRITICAL: **`QUEUE_CONNECTION` defaults to `sync`** — if env var is missing on production, restore jobs run synchronously in the HTTP request cycle, blocking the web server for minutes
- W-QUE-02 HIGH: **`retry_after => 90` seconds** — restore jobs, large DB backups, and storage backups run far longer than 90 seconds; expired retry_after triggers duplicate execution without cleanup
- W-QUE-03 MEDIUM: `saas-worker: ERROR (no such group)` in deploy log — Supervisor config mismatch; deploy.sh references a group `saas-worker` that doesn't exist (actual group is `saas-platform-worker`); causes deploy errors on every deploy
- W-QUE-04 MEDIUM: No queue-specific `timeout` setting for long-running jobs
- W-QUE-05 LOW: No queue priority — restore jobs compete with education AI, lifecycle, and billing jobs

**Required Fixes:**
- FIX-QUE-01 CRITICAL: Ensure `QUEUE_CONNECTION=redis` is set in production `.env`; add validation to installer
- FIX-QUE-02 HIGH: Add dedicated `restore` connection config with `retry_after => 7200` (2h)
- FIX-QUE-03 MEDIUM: Fix deploy.sh Supervisor group name to `saas-platform-worker`

---

### 8. Scheduler

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | MEDIUM |
| **Score** | 70/100 |
| **Can Core Be Frozen?** | YES — schedule is functional; dead-scheduler detection has false-positive bug |

**Strengths:**
- 15+ scheduled commands with proper daily/weekly/monthly cadences
- `withoutOverlapping()` on all critical commands
- `appendOutputTo()` log files for auditability
- `onFailure()` handler on billing command
- Proper lifecycle order (expired → grace → suspend → archive → delete)

**Detected Weaknesses:**
- W-SCH-01 HIGH: **`SCHEDULER_DEAD` false-positive** — `ScanRuntimeAnomaliesCommand` fires `SCHEDULER_DEAD` if `InfraSchedulerRun::latest()->first()` has `created_at < now()-5min`. Since `InfraSchedulerRun` is only written during DB backup (daily at 02:00), this fires as CRITICAL on every 5-minute scan for the other 23h 55min of each day
- W-SCH-02 MEDIUM: No tenant database backup in Kernel.php — tenant data never backed up by scheduler
- W-SCH-03 LOW: `monitor:platform` runs every 2 minutes — 720 times per day; verify this is not creating DB write pressure via telemetry

**Required Fixes:**
- FIX-SCH-01 CRITICAL: Fix SCHEDULER_DEAD logic — use a dedicated heartbeat record (e.g., write `InfraSchedulerRun` from every scheduled command, not just backup), or increase the staleness window to 25 hours

---

### 9. Finance Core

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | MEDIUM |
| **Score** | 75/100 |
| **Can Core Be Frozen?** | YES — no critical vulnerabilities; structural gaps are manageable |

**Strengths:**
- `safeCreate()` handles idempotency via unique constraint (code 23000) for automatic entries
- Never throws exceptions — payment flows never break on ledger failure
- Multiple event sources: theme purchase, bundle, invoice, lifecycle, API push
- Correct income/expense separation with category labels

**Detected Weaknesses:**
- W-FIN-01 MEDIUM: `createManual()` does not use `safeCreate()` — duplicate manual entries are possible (no idempotency for manual entry)
- W-FIN-02 MEDIUM: `fromCustomerSitePush()` accepts `amount` without maximum bound — API abuse could inject arbitrarily large amounts
- W-FIN-03 MEDIUM: No reconciliation mechanism — ledger total cannot be verified against payment gateway reports
- W-FIN-04 LOW: `kpis()` runs 8 separate DB `sum()` queries — not aggregated in a single query
- W-FIN-05 LOW: `monthlyTrend()` runs 2×N DB queries per call (income + expense per month) — should be a single GROUP BY query

**Required Fixes:**
- FIX-FIN-01: Add `amount` max validation in `fromCustomerSitePush()` (e.g., max 10B IRR)
- FIX-FIN-02: Refactor `kpis()` into a single aggregated query with conditional SUM

---

### 10. Security

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | MEDIUM |
| **Score** | 76/100 |
| **Can Core Be Frozen?** | YES — no known exploitable vulnerabilities; improvements are hardening |

**Strengths:**
- HMAC-SHA256 license key hashing (APP_KEY as secret)
- Path traversal guard on all admin download/verify endpoints
- Activity logging on restore, license, and install operations
- Laravel CSRF on all state-changing routes
- No SQL injection via Eloquent ORM (main codebase)
- Slack alerts on critical events
- `APP_DEBUG=false` validated at production

**Detected Weaknesses:**
- W-SEC-01 HIGH: **MySQL password in CLI argument** — `RestoreService` passes password as `-p$password` to `exec()` calls — password visible in `ps aux` on Linux
- W-SEC-02 HIGH: **No rate limiting on license validate endpoint** — enables key enumeration
- W-SEC-03 MEDIUM: `exec("gunzip < {$localPath} | mysql ...")` — `$localPath` constructed from `sys_get_temp_dir()` + timestamp, but the pipe command is not wrapped in a shell-quoted compound; a localPath containing `$(...)` could be dangerous if ever influenced by user input
- W-SEC-04 MEDIUM: `getTenantTables()` uses string-interpolated SQL (`TABLE_SCHEMA='{$db}'`) — not parameterized
- W-SEC-05 LOW: No Content-Security-Policy headers on admin panel (not audited in detail — would need middleware audit)

**Required Fixes:**
- FIX-SEC-01 HIGH: Replace `-p$password` with `MYSQL_PWD=$password mysql ...` or `--defaults-file`
- FIX-SEC-02 HIGH: Add `throttle:60,1` to license validate route
- FIX-SEC-03 MEDIUM: Parameterize `getTenantTables()` query using PDO

---

### 11. Performance

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | MEDIUM |
| **Score** | 70/100 |
| **Can Core Be Frozen?** | YES — no blocking performance issues at current scale |

**Strengths:**
- `Cache::remember()` for Arvan file listing in DR score (5-min TTL)
- Streaming upload/download for large files (no full memory load)
- `withoutOverlapping()` prevents scheduler thundering herd

**Detected Weaknesses:**
- W-PER-01 HIGH: `listFiles()` makes individual `size()` API call per file — O(N) HTTP requests to Arvan on each DR scan (hourly)
- W-PER-02 MEDIUM: `monthlyTrend()` — 2×N separate DB queries instead of single GROUP BY
- W-PER-03 MEDIUM: `kpis()` — 8 separate SUM queries instead of one aggregate
- W-PER-04 MEDIUM: `DrIntegrityService::checkPlatform()` makes fresh Arvan API calls each time — no caching (while DrReadinessScoreService caches the same listing separately)
- W-PER-05 LOW: No DB indexes documented on `(site_id, backup_type, status, started_at)` in `infra_backup_runs` — heavy query pattern without index could degrade as backup history grows

**Required Fixes:**
- FIX-PER-01: Fetch object metadata in batch from Arvan list response instead of individual `size()` calls
- FIX-PER-02: Add composite index on `infra_backup_runs (site_id, backup_type, status, started_at)`
- FIX-PER-03: Share Arvan cache between DrReadinessScoreService and DrIntegrityService

---

### 12. Scalability

| Attribute | Value |
|-----------|-------|
| **Status** | WARNING |
| **Risk Level** | MEDIUM |
| **Score** | 65/100 |
| **Can Core Be Frozen?** | NO — retry_after gap is a correctness issue at scale, not just performance |

**Strengths:**
- Multi-worker Supervisor config (2 saas-platform workers)
- Tenant isolation design supports horizontal read scaling
- `resource:calculate --push` runs at off-peak (06:00)
- Lifecycle processing is ordered correctly (expired → archive → delete)

**Detected Weaknesses:**
- W-SCA-01 CRITICAL: `retry_after => 90` applies to all queues including restore — at scale with large databases, this guarantees duplicate restore job execution
- W-SCA-02 HIGH: No dedicated queue for long-running jobs (restore, large backup) vs short jobs (lifecycle, billing)
- W-SCA-03 MEDIUM: `resource:bill-overage` has no batch size limit — for 1000+ sites, a single billing run could lock tables
- W-SCA-04 MEDIUM: `resource:calculate` runs on all sites in one command — should be partitioned
- W-SCA-05 LOW: No Redis cluster support configured — single Redis is a scalability ceiling

**Required Fixes:**
- FIX-SCA-01 CRITICAL: Create separate queue connection `restore` with `retry_after => 7200`
- FIX-SCA-02 HIGH: Add queue priority: restore → backup → billing → lifecycle → ai

---

## Summary Table

| # | Subsystem | Status | Risk | Score | Freeze? |
|---|-----------|--------|------|-------|---------|
| 1 | Installer Foundation | WARNING | LOW | 82/100 | YES |
| 2 | Backup Engine | WARNING | MEDIUM | 72/100 | NO |
| 3 | Restore Foundation | WARNING | HIGH | 68/100 | NO |
| 4 | Disaster Recovery Center | WARNING | MEDIUM | 70/100 | YES |
| 5 | Tenant Isolation | WARNING | MEDIUM | 78/100 | YES |
| 6 | License System | PASS | LOW | 88/100 | YES |
| 7 | Queue System | WARNING | HIGH | 65/100 | NO |
| 8 | Scheduler | WARNING | MEDIUM | 70/100 | YES |
| 9 | Finance Core | WARNING | MEDIUM | 75/100 | YES |
| 10 | Security | WARNING | MEDIUM | 76/100 | YES |
| 11 | Performance | WARNING | MEDIUM | 70/100 | YES |
| 12 | Scalability | WARNING | MEDIUM | 65/100 | NO |

**Overall Core Health: 73/100**

**Verdict: CORE_NOT_READY_FOR_FREEZE**

Subsystems blocking freeze: Backup Engine (W-BAK-01 Arvan retention, W-BAK-02 no tenant backup), Restore Foundation (W-RST-01 platform DB restore not implemented), Queue System (W-QUE-01 sync default, W-QUE-02 retry_after), Scalability (W-SCA-01 retry_after duplicate).
