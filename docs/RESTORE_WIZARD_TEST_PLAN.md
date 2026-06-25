# RESTORE_WIZARD_TEST_PLAN
> Date: 2026-06-26
> Scope: Phase 1 — Restore Wizard (Platform + Tenant)

---

## Test Coverage Summary

| Suite | Tests | Status |
|-------|-------|--------|
| Access Control | 3 | ✅ Implemented |
| Browse Endpoint | 5 | ✅ Implemented |
| Validation Endpoint | 3 | ✅ Implemented |
| Dry Run | 4 | ✅ Implemented |
| Execute Endpoint | 5 | ✅ Implemented |
| Abort & Status | 3 | ✅ Implemented |
| Logs View | 2 | ✅ Implemented |
| RestoreService Unit | 5 | ✅ Implemented |
| **Total** | **30** | **✅** |

---

## Test File

`tests/Feature/DisasterRecovery/RestoreWizardTest.php`

---

## Access Control Tests

### TC-AC-001: Unauthenticated access is rejected
- **Arrange:** No session
- **Act:** GET `/admin/disaster-recovery`
- **Assert:** Redirect to login (302)

### TC-AC-002: Non-admin user is rejected
- **Arrange:** Authenticated as regular user (admin=false)
- **Act:** GET `/admin/disaster-recovery`
- **Assert:** 403 Forbidden

### TC-AC-003: Admin user can access wizard
- **Arrange:** Authenticated as admin user
- **Act:** GET `/admin/disaster-recovery`
- **Assert:** 200 OK, view contains wizard markup

---

## Browse Endpoint Tests

### TC-BR-001: Invalid restore type rejected
- **Arrange:** Admin session
- **Act:** GET `/admin/disaster-recovery/browse?type=invalid_type`
- **Assert:** 422 Unprocessable Entity

### TC-BR-002: Tenant type requires site_id
- **Arrange:** Admin session
- **Act:** GET `/admin/disaster-recovery/browse?type=tenant_database` (no site_id)
- **Assert:** 422 validation error

### TC-BR-003: Platform type returns correct Arvan prefix
- **Arrange:** Admin session, mock ArvanBackupService::listFiles()
- **Act:** GET `/admin/disaster-recovery/browse?type=platform_database`
- **Assert:** 200, JSON contains files with correct prefix `saas-platform/database`

### TC-BR-004: Tenant type returns tenant-specific prefix
- **Arrange:** Admin session, mock ArvanBackupService::listFiles(), valid site
- **Act:** GET `/admin/disaster-recovery/browse?type=tenant_database&site_id={id}`
- **Assert:** 200, prefix = `customer-sites/tenant-{id}/database`

### TC-BR-005: Empty backup list returns count=0
- **Arrange:** Admin session, mock returns []
- **Act:** GET `/admin/disaster-recovery/browse?type=platform_database`
- **Assert:** 200, `count=0`, `files=[]`

---

## Validation Endpoint Tests

### TC-VL-001: Valid file passes all checks
- **Arrange:** Admin session, mock ArvanBackupService to return exists=true, size=1MB, correct filename format
- **Act:** POST `/admin/disaster-recovery/validate`
- **Assert:** 200, `valid=true`, all checks pass

### TC-VL-002: Non-existent file fails
- **Arrange:** Admin session, mock ArvanBackupService::exists() = false
- **Act:** POST `/admin/disaster-recovery/validate`
- **Assert:** 200, `valid=false`, file_exists check = fail

### TC-VL-003: Active lock reports lock_active
- **Arrange:** Admin session, manually set Redis lock
- **Act:** POST `/admin/disaster-recovery/validate`
- **Assert:** 200, `no_active_restore` check = warn (not failed)

---

## Dry Run Tests

### TC-DR-001: Dry run creates RestoreLog with dry_run=true
- **Arrange:** Admin session, mock ArvanBackupService
- **Act:** POST `/admin/disaster-recovery/dry-run` with valid params
- **Assert:** DB has RestoreLog where dry_run=true, status=dry_run_complete

### TC-DR-002: Tenant dry run requires site_id
- **Arrange:** Admin session
- **Act:** POST `/admin/disaster-recovery/dry-run` with type=tenant_database, no site_id
- **Assert:** 422 validation error

### TC-DR-003: Dry run steps reference correct tenant ID
- **Arrange:** Admin session, site with id=5
- **Act:** POST `/admin/disaster-recovery/dry-run` with site_id=5
- **Assert:** Response steps contain string `tenant-5`

### TC-DR-004: Dry run rejects non-existent site
- **Arrange:** Admin session, site_id=99999 (not in DB)
- **Act:** POST `/admin/disaster-recovery/dry-run`
- **Assert:** 422, site_id validation fails

---

## Execute Endpoint Tests

### TC-EX-001: Execute requires confirmation flag
- **Arrange:** Admin session
- **Act:** POST `/admin/disaster-recovery/execute` without `confirmation=true`
- **Assert:** 422 validation error

### TC-EX-002: Platform DB execute requires confirm_text
- **Arrange:** Admin session, type=platform_database
- **Act:** POST without `confirm_text=RESTORE-PLATFORM`
- **Assert:** 422 validation error

### TC-EX-003: Execute blocked when lock is active
- **Arrange:** Admin session, Redis lock already set
- **Act:** POST `/admin/disaster-recovery/execute` with valid params
- **Assert:** 409 response, `locked=true`

### TC-EX-004: Valid execute creates RestoreLog and dispatches job
- **Arrange:** Admin session, Queue::fake(), mock ArvanBackupService
- **Act:** POST `/admin/disaster-recovery/execute` with all valid params
- **Assert:** DB has RestoreLog with status=pending, Queue::assertPushed(RestoreTenantDatabaseJob::class)

### TC-EX-005: Execute returns SSE stream URL
- **Arrange:** Admin session, Queue::fake()
- **Act:** POST `/admin/disaster-recovery/execute`
- **Assert:** JSON response has `stream_url` matching pattern `/stream?token=...`

---

## Abort & Status Tests

### TC-AB-001: Abort clears Redis lock
- **Arrange:** Admin session, Redis lock active
- **Act:** POST `/admin/disaster-recovery/abort`
- **Assert:** 200, Redis key `restore_in_progress` no longer exists

### TC-AB-002: Abort when no lock is graceful
- **Arrange:** Admin session, no lock
- **Act:** POST `/admin/disaster-recovery/abort`
- **Assert:** 200, no error

### TC-ST-001: Status reports locked state
- **Arrange:** Admin session, Redis lock active with value `platform-db`
- **Act:** GET `/admin/disaster-recovery/status`
- **Assert:** JSON `locked=true`, `lock_value=platform-db`

---

## Logs View Tests

### TC-LG-001: Logs page returns paginated results
- **Arrange:** Admin session, 3 RestoreLogs in DB
- **Act:** GET `/admin/disaster-recovery/logs`
- **Assert:** 200, all 3 logs visible

### TC-LG-002: Log detail page shows execution log
- **Arrange:** Admin session, RestoreLog with execution_log set
- **Act:** GET `/admin/disaster-recovery/logs/{id}`
- **Assert:** 200, execution log rendered

---

## RestoreService Unit Tests

### TC-SV-001: acquireLock() / isLocked() / releaseLock()
- **Arrange:** Empty Redis
- **Assert:** isLocked() = false → acquireLock() = true → isLocked() = true → releaseLock() → isLocked() = false

### TC-SV-002: pushProgress() / getProgressEvents()
- **Arrange:** RestoreService instance
- **Act:** pushProgress with 3 events
- **Assert:** getProgressEvents() returns 3 events in order

### TC-SV-003: dryRun() returns valid structure
- **Arrange:** Mock ArvanBackupService
- **Act:** dryRun('platform_database', 'path/to/file', null)
- **Assert:** Result has keys: type, remote_path, validation, steps, warnings, impact, safe_to_run

### TC-SV-004: humanSize() formats correctly
- **Assert:** humanSize(0)='0 B', humanSize(1023)='1023 B', humanSize(1048576)='1.00 MB'

### TC-SV-005: generateToken() returns 40-char alphanumeric
- **Assert:** strlen = 40, ctype_alnum = true

---

## Manual Testing Checklist (Pre-Launch)

- [ ] Admin can open wizard at `/admin/disaster-recovery`
- [ ] Step 1: All 4 restore types selectable
- [ ] Step 2: Site list appears for tenant types; info shown for platform types
- [ ] Step 3: Backup list loads from Arvan (with real test bucket)
- [ ] Step 4: Validation results display with pass/warn indicators
- [ ] Step 5: Dry-run simulates steps without modifying data
- [ ] Step 6: Platform DB requires "RESTORE-PLATFORM" text match
- [ ] Step 7: Progress bar advances via SSE; terminal log shows live events
- [ ] Lock banner appears while restore is running
- [ ] Concurrent restore attempt returns 409
- [ ] Abort button releases lock
- [ ] Restore log persists with execution detail
- [ ] Rollback snapshot created in /tmp/ before restore

---

*Generated: 2026-06-26*
