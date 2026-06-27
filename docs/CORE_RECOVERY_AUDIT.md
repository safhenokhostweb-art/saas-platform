# CORE RECOVERY AUDIT
> CORE_CERTIFICATION_PHASE_1 — 2026-06-27
> Scope: All recovery scenarios — backup → validate → restore → verify
> Method: Source code trace of each recovery path

---

## Recovery Path 1 — Platform Database Disaster

**Scenario:** Production MySQL database is corrupted or accidentally dropped.

**Expected Path:**
1. Admin navigates to DR Center
2. Selects latest platform DB backup from Arvan
3. Runs dry-run (shows 12-step plan)
4. Confirms restore
5. System: acquires lock → maintenance mode → creates pre-restore snapshot → downloads backup → verifies gzip → drops+recreates DB → restores → runs migrations → removes maintenance mode → health check → releases lock
6. Admin verifies application is healthy

**Audit Finding: CRITICAL GAP**

Step 4→5 is broken. `RestoreService::executePlatformDatabaseRestore()` **does not exist**. The dry-run correctly describes all 12 steps in `describeRestoreSteps(TYPE_PLATFORM_DATABASE)`, but the actual execution method is absent from RestoreService.

**Evidence:**
- `RestoreService.php` contains:
  - `executeTenantDatabaseRestore()` — ✅ Implemented
  - `executePlatformStorageRestore()` — ✅ Implemented
  - `executePlatformDatabaseRestore()` — ❌ Missing
- `RestorePlatformStorageJob.php` — calls `executePlatformStorageRestore()` ✅
- No `RestorePlatformDatabaseJob.php` file exists

**Recovery Path Status: FAIL — Platform DB restore is non-functional**

**Required Fix:** Implement `executePlatformDatabaseRestore()` and `RestorePlatformDatabaseJob` (marked Phase 3 in roadmap — this is a P0 gap).

---

## Recovery Path 2 — Tenant Database Disaster

**Scenario:** A tenant's data is accidentally deleted or corrupted.

**Expected Path:**
1. Admin selects tenant and backup file
2. Dry-run shows 12 steps including temp DB staging
3. Restore executes: download → gzip verify → pre-restore snapshot → create temp DB → restore backup to temp → delete tenant rows from production → copy from temp → drop temp → flush cache
4. Lock released

**Audit Finding: WARNING**

The tenant DB restore execution path is implemented. Key gaps:

**GAP-RST-T01 HIGH:** `deleteTenantData()` + `copyTenantData()` run without a DB transaction. Failure between steps 5 and 7 leaves the production database with partial tenant data (some tables deleted, some not yet copied).

**Evidence:**
```php
// RestoreService.php lines 417-424:
$this->deleteTenantData($connArgs, $customerDb, $siteId);  // step 5
$this->push($token, $events, 72, '...');
$this->copyTenantData($connArgs, $tmpDb, $customerDb, $siteId); // step 7
// No transaction wrapping these two calls
```

**GAP-RST-T02 MEDIUM:** `copyTenantData()` uses `SELECT *` — assumes column structure between backup dump and current production schema is identical. After any migration that adds/removes columns between backup date and restore date, this causes INSERT failures or schema mismatches that are silently ignored (`if ($c !== 0) continue`).

**GAP-RST-T03 MEDIUM:** Pre-restore snapshot (`createDatabaseSnapshot`) is not verified after creation. If the snapshot is corrupt (partial gzip), the rollback capability is false assurance.

**GAP-RST-T04 LOW:** No tenant backup command exists to produce the backup files used in this restore flow. Tenant restore depends on backup files that are never created by the scheduler.

**Recovery Path Status: WARNING — Functional but transactional integrity gap**

---

## Recovery Path 3 — Platform Storage Disaster

**Scenario:** Platform public storage files are accidentally deleted.

**Expected Path:**
1. Admin selects storage backup from Arvan
2. Restore: download tar.gz → verify tar integrity → check disk space → extract to storage/app/public → fix permissions → clear cache

**Audit Finding: WARNING**

The platform storage restore is implemented and functional. Gaps:

**GAP-RST-S01 MEDIUM:** `merge` strategy uses `rsync --ignore-existing` — this means files added after the backup (new uploads) are NOT overwritten. However, it also means that if the disaster was a file corruption (overwrite with corrupted content), the merge strategy will NOT restore the original (corrupted file still present, backup not applied).

**GAP-RST-S02 LOW:** No disk space check before download — only after download. If download fills the disk, the integrity check fails and the backup is deleted, but disk is already full.

**Recovery Path Status: WARNING — functional but merge strategy edge case**

---

## Recovery Path 4 — Baseline Restore (Installer Recovery)

**Scenario:** The SaaS platform installation is corrupted.

**Expected Path:**
1. Admin views /admin/installer
2. Verifies baseline manifest (SHA-256 checksums)
3. Downloads baseline_meta.json and manifest.json for forensic analysis
4. Uses baseline (database.sql.gz + .env.backup) to manually restore

**Audit Finding: WARNING**

The baseline system is read-only and correctly protects manifests. However, there is no automated restore-from-baseline flow — the admin must manually:
1. Download database.sql.gz from the baseline directory
2. Manually import to MySQL
3. Manually restore .env.backup

**GAP-BSL-01 LOW:** Baseline download endpoint (`downloadBaselineMeta`) only serves `baseline_meta.json`, not `database.sql.gz`. There is no download endpoint for the actual database backup in the baseline — admin must access it via server filesystem or SSH.

**Recovery Path Status: WARNING — forensic tools present but restore requires manual intervention**

---

## Recovery Path 5 — Redis Failure

**Scenario:** Redis goes down.

**Impact Areas:**
1. Queue: jobs stop being dispatched/processed → async operations halt
2. Restore lock: `restore_in_progress` Redis key is inaccessible → `acquireLock()` returns false (Redis exception) → no restore can start
3. Progress streaming: `restore_progress_{token}` Redis list → SSE stream shows no progress
4. Cache: `dr_arvan_platform_db` cache → DrReadinessScoreService makes fresh Arvan API calls on each check

**Audit Finding:**

`RestoreService::isLocked()` calls `Redis::get('restore_in_progress')` without try/catch. Redis connection failure would throw an exception, propagating to the caller and breaking the restore wizard UI.

```php
// RestoreService.php line 29:
public function isLocked(): bool
{
    return (bool) Redis::get('restore_in_progress'); // throws on Redis failure
}
```

**GAP-RED-01 MEDIUM:** `isLocked()`, `acquireLock()`, `releaseLock()` have no exception handling for Redis failure.

**GAP-RED-02 MEDIUM:** `DrReadinessScoreService::evalRedis()` correctly catches Redis::ping() failures (returns 0/5 score). But `evalArvanSync()` uses `Cache::remember()` which also uses Redis — cache failure here would throw uncaught exception, causing the entire DR score calculation to fail.

**Recovery Path Status: WARNING — single Redis node is a recovery dependency**

---

## Recovery Path 6 — Installer Baseline Verification

**Scenario:** Detect that installation files have been tampered with post-install.

**Path:**
1. Admin clicks "Verify" on /admin/installer
2. AJAX POST to /admin/installer/verify
3. BaselineManifestService::verify() runs 5-step check
4. Returns PASS if all 5 checks pass

**Audit Finding: PASS**

The verification is correctly implemented:
- Step 1: Directory exists
- Step 2: Required files present
- Step 3: manifest.json present
- Step 4: Manifest self-checksum verified (body SHA-256 matches manifest_checksum field)
- Step 5: All file checksums match current file contents

The streaming hash approach (`hash_file()`) is memory-safe for large database dumps. Path traversal is prevented.

**Recovery Path Status: PASS**

---

## Missing Recovery Scenarios

| Scenario | Status | Gap |
|----------|--------|-----|
| Platform DB corruption | FAIL | executePlatformDatabaseRestore() not implemented |
| Tenant DB corruption | WARNING | Transactional integrity gap |
| Platform storage loss | WARNING | merge vs overwrite strategy edge case |
| Redis failure | WARNING | No exception handling in lock methods |
| Queue worker death | WARNING | retry_after=90 causes duplicate job on long restores |
| Scheduler death | WARNING | SCHEDULER_DEAD fires false positive daily |
| Arvan storage full | MISSING | No alert for Arvan quota/cost from unbounded retention |
| Baseline verification FAIL | PASS | 5-step check is correct |
| License recovery | PASS | findByKey dual-lookup handles hash migration |
| Installer baseline restore | WARNING | No automated restore from baseline |

---

## Recovery Readiness Score (per DrReadinessScoreService weights)

| Component | Score | Max | Status |
|-----------|-------|-----|--------|
| Platform DB backup (recent) | depends on runtime | 20 | — |
| Platform storage backup | depends on runtime | 10 | — |
| Tenant backups | 0/10 (never implemented) | 10 | MISSING |
| Arvan sync | depends on runtime | 15 | — |
| Restore logs | 0/10 (fresh install) | 10 | MISSING |
| Queue health | inferred | 10 | UNKNOWN |
| Scheduler | inferred | 10 | UNKNOWN |
| Redis | live check | 5 | — |
| Disk space | live check | 5 | — |
| Latest DR simulation | 0/5 (never run) | 5 | MISSING |

**Structural maximum achievable score** (ignoring runtime state): ~60/100 because tenant backup (10pts) and restore logs (10pts) and simulation (5pts) cannot be earned without implementations that don't exist yet.

---

## Recovery Audit Verdict

**Recovery Score: 68/100**

**Critical gaps:**
1. Platform DB restore execution not implemented (highest severity)
2. Tenant backups never created (no command in scheduler)
3. Queue retry_after causes duplicate restore execution for large databases

**No recovery path exists today for a production platform database corruption event that requires automated restore.** Manual restoration via SSH + mysqldump is the only option.
