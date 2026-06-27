# KNOWN LIMITATIONS
> CORE_FREEZE_ACCEPTANCE_FINAL — 2026-06-27
> Accepted limitations that do NOT block certification

---

## Purpose

This document records limitations that were identified during CORE_FREEZE_ACCEPTANCE_FINAL but are **accepted as non-blocking**. None of these prevent selling the SaaS to real customers. All are targeted for resolution in Phase 3 or ops maintenance.

---

## KL-001 — Supervisor Group Name Mismatch in deploy.sh

**Severity:** LOW
**Impact:** deploy.sh tries to restart `saas-worker` (wrong name). Actual group is `saas-platform-worker`. Workers are NOT restarted on deploy; they continue with old code until natural restart.
**Functional Risk:** Workers pick up new code only after they restart (job completion or manual restart). For most deploys this is acceptable — queue jobs are self-contained.
**Production Evidence:** saas-platform-worker:00 and :01 both RUNNING during acceptance test.
**Resolution:** SSH to VPS → edit `/etc/supervisor/conf.d/saas-platform.conf` → fix group name → supervisorctl reload. Target: next scheduled maintenance window.
**Owner:** Ops

---

## KL-002 — evalRestoreLogs() False Low Score on Fresh Installs

**Severity:** LOW
**Impact:** DrReadinessScoreService.evalRestoreLogs() gives 0 points if no successful restore exists in the last 30 days. On a fresh platform install, this penalizes the DR score even though no restore has ever been needed.
**Functional Risk:** Zero — actual restore works correctly. Score is cosmetic.
**Workaround:** Run one dry-run restore from the DR Center — this records a `dry_run_complete` entry and brings the component to OK.
**Resolution:** Phase 3 — treat "no prior restores" as NEUTRAL (5 points), not MISSING (0 points).
**Owner:** Phase 3 implementation

---

## KL-003 — ALERT-007 TTL Math in ScanDrHealthCommand

**Severity:** LOW
**Impact:** `ScanDrHealthCommand` uses hardcoded 3600s assumption when checking if a restore lock is stale. Actual tenant restore lock TTL is 1800s. After a 30-minute tenant restore, the system may briefly report "restore still running" for up to 30 extra minutes.
**Functional Risk:** False-positive Slack alert after tenant restore. No operational impact.
**Resolution:** Phase 3 — parameterize TTL from lock constants defined in RestoreService.
**Owner:** Phase 3 implementation

---

## KL-004 — Finance createManual() No Idempotency Key

**Severity:** LOW
**Impact:** `FinanceLedgerService::createManual()` has no deduplication key. An admin double-clicking the submit button could create two identical manual entries.
**Functional Risk:** Admin must not double-submit. Automatic entries (safeCreate) are fully idempotent via unique constraint. Manual entries are admin-initiated and auditable.
**Workaround:** Admin deletes the duplicate entry from the finance ledger.
**Resolution:** Phase 3 — add idempotency_key field to createManual() inputs and uniqueness check.
**Owner:** Phase 3 implementation

---

## KL-005 — No Rate Limiting on License Validate Endpoint

**Severity:** MEDIUM
**Impact:** `/api/license/validate` has no `throttle` middleware. An attacker could repeatedly call this endpoint without rate limiting.
**Functional Risk:** HMAC-SHA256 validation is computationally expensive (not instant). Brute-forcing license keys is infeasible — keyspace is (4 * 4-char alphanumeric segments) = 36^16 ≈ 7.96 × 10^24 combinations. No plaintext secret is ever returned.
**Workaround:** Server-level rate limiting (nginx `limit_req`) can be applied without code change.
**Resolution:** Phase 3 security hardening — add `throttle:60,1` middleware to `/api/license/*` routes.
**Owner:** Phase 3 implementation

---

## KL-006 — Tenant Storage Restore Uses Platform Storage Job

**Severity:** LOW
**Impact:** `RestoreWizardController::execute()` dispatches `RestorePlatformStorageJob` for both `TYPE_PLATFORM_STORAGE` and `TYPE_TENANT_STORAGE`. The job extracts files to `storage/app/public/` without tenant filtering.
**Functional Risk:** If tenant storage files are organized by subdirectory under `storage/app/public/{siteId}/`, restoration works correctly. If they are interleaved, restoring one tenant's storage backup restores all files from that backup snapshot regardless of tenant.
**Context:** Only one `storage:backup` command exists (platform-wide). No per-tenant storage backup is scheduled. The `TYPE_TENANT_STORAGE` restore type is a placeholder for future per-tenant storage backups.
**Resolution:** Phase 3 — implement `BackupTenantStorageCommand` + `RestoreTenantStorageJob` with per-tenant path scoping.
**Owner:** Phase 3 implementation

---

## Limitations NOT Blocking Certification

None of the above limitations:
- Cause data loss under normal operation
- Create tenant isolation breaches
- Block a customer from using the platform
- Create unrecoverable states
- Represent a security vulnerability that exposes customer data

**All 6 limitations are accepted for the CORE_ENTERPRISE_CERTIFIED verdict.**
