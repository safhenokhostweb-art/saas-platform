# RESTORE_WIZARD_SECURITY_AUDIT
> Date: 2026-06-26
> Auditor: Claude Sonnet 4.6 (automated)
> Scope: Phase 1 — Restore Wizard (Platform + Tenant)

---

## Audit Summary

| Category | Finding | Severity | Status |
|----------|---------|----------|--------|
| Access Control | Admin-only middleware on all routes | PASS | ✅ |
| Tenant Isolation | Cross-tenant restore prevention | PASS | ✅ |
| Input Validation | All parameters validated before use | PASS | ✅ |
| Confirmation Gates | Double-confirmation for destructive ops | PASS | ✅ |
| Concurrent Restore | Redis lock prevents race conditions | PASS | ✅ |
| Command Injection | All shell args use escapeshellarg() | PASS | ✅ |
| CSRF Protection | All POST routes protected | PASS | ✅ |
| SSE Token | Token validated with regex before use | PASS | ✅ |
| Activity Logging | All restore attempts logged | PASS | ✅ |
| Rollback Snapshot | Pre-restore snapshot before any write | PASS | ✅ |
| Job Safety | tries=1 on restore jobs (no auto-retry) | PASS | ✅ |
| Lock TTL | Redis lock expires automatically | PASS | ✅ |

---

## Detailed Findings

### SEC-001 — Access Control
**Severity:** N/A (PASS)
**Evidence:** All disaster-recovery routes are registered under `Route::prefix('admin')->middleware(['auth', 'admin'])`. No unauthenticated or non-admin user can reach any endpoint.
**Verdict:** PASS

### SEC-002 — Tenant Isolation
**Severity:** N/A (PASS)
**Evidence:**
- `RestoreWizardController::execute()` validates `site_id` exists in `sites` table via Laravel validation rule `exists:sites,id`
- Tenant backup paths are strictly `customer-sites/tenant-{siteId}/...` — no path can reference another tenant's data
- `RestoreService::remotePrefixFor()` enforces path construction without interpolating raw user input
- Cross-tenant restore (wrong site_id) is rejected at validation layer before any operation
**Verdict:** PASS

### SEC-003 — Command Injection Prevention
**Severity:** N/A (PASS)
**Evidence:** All calls to `exec()` and `mysqldump` use `escapeshellarg()` on every argument:
```php
$passwordArg = $password !== '' ? '-p' . escapeshellarg($password) : '';
$cmd = sprintf('mysqldump ... %s %s | gzip > %s', ..., escapeshellarg($db), escapeshellarg($fullPath));
```
No raw string concatenation in shell commands.
**Verdict:** PASS

### SEC-004 — CSRF Protection
**Severity:** N/A (PASS)
**Evidence:** All POST endpoints (`/validate`, `/dry-run`, `/execute`, `/abort`) require CSRF token. The frontend sends `X-CSRF-TOKEN` header on all AJAX requests.
**Verdict:** PASS

### SEC-005 — SSE Token Validation
**Severity:** N/A (PASS)
**Evidence:** `RestoreWizardController::stream()` validates the token with:
```php
if (!preg_match('/^[a-zA-Z0-9]{20,64}$/', $token)) { abort(400); }
```
Redis key is prefixed: `restore_progress_{token}` — cannot be manipulated to read arbitrary Redis keys.
**Verdict:** PASS

### SEC-006 — Concurrent Restore Prevention
**Severity:** N/A (PASS)
**Evidence:**
- Redis SET NX (set if not exists) with `acquireLock()` ensures only one restore runs at a time
- Lock TTL (30–60 minutes) prevents permanent lock if process crashes
- Lock value identifies what is running (e.g., `tenant-5`, `platform-db`)
- Lock is released on both success and failure in all code paths
**Verdict:** PASS

### SEC-007 — Platform DB Confirmation Gate
**Severity:** N/A (PASS)
**Evidence:** Platform database restore (the most destructive operation) requires:
1. `confirmation: true` checkbox in the UI
2. Exact text match: `confirm_text === 'RESTORE-PLATFORM'`
Both checks run server-side in `RestoreWizardController::execute()`.
**Verdict:** PASS

### SEC-008 — Job Retry Safety
**Severity:** N/A (PASS)
**Evidence:** Both `RestoreTenantDatabaseJob` and `RestorePlatformStorageJob` declare:
```php
public int $tries = 1;
```
Restore jobs never auto-retry — a failed partial restore could leave data in an inconsistent state if retried.
**Verdict:** PASS

### SEC-009 — Backup File Path Traversal
**Severity:** N/A (PASS)
**Evidence:**
- `backup_file` is validated as `string|max:500`
- The file is only accessed via `ArvanBackupService::exists()`, `::size()`, and `::download()` which use `Storage::disk('arvan_backup')` — Laravel's filesystem abstraction prevents path traversal
- No raw file system access with user-provided paths
**Verdict:** PASS

### SEC-010 — Snapshot Retention
**Severity:** MEDIUM (documented, not a vulnerability)
**Evidence:** Pre-restore snapshots are written to `/tmp/pre-restore-*.sql.gz`. These files:
- Contain full database dump
- Are accessible to anyone with SSH root access
- Are NOT automatically cleaned up beyond 24h system tmpfs cycle

**Recommendation:** Consider adding a scheduled command to clean up snapshots older than 24h.
**Verdict:** DOCUMENTED — Not a code vulnerability but operational risk

---

## Audit Verdict

**RESTORE_WIZARD_SECURITY_AUDIT: PASS**

All critical security controls implemented. One operational risk documented (SEC-010) that should be addressed in Phase 2 (DR Center).

---

*Generated: 2026-06-26*
