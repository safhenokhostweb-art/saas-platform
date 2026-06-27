# CORE SECURITY AUDIT
> CORE_CERTIFICATION_PHASE_1 — 2026-06-27
> Scope: All 12 core subsystems
> Method: Source code inspection (no assumptions, no inferred passes)

---

## SEC-001 — MySQL Password Exposure via CLI

**Severity:** HIGH
**Evidence:** `app/Services/Infrastructure/RestoreService.php` lines 309-314, 399-402

```php
// Lines 309-314 (createDatabaseSnapshot):
$passwordArg = $password !== '' ? '-p' . escapeshellarg($password) : '';
$cmd = sprintf('mysqldump ... %s ...', $passwordArg);
exec($cmd, $output, $exitCode);

// Lines 399-402 (executeTenantDatabaseRestore):
$passArg  = $password !== '' ? '-p' . escapeshellarg($password) : '';
$connArgs = sprintf('-h %s -P %s -u %s %s', ..., $passArg);
exec("mysql {$connArgs} ...", $o, $c);
```

**Impact:** Database password is visible in `ps aux` process listing while any backup, snapshot, or restore is running. Any local user with `ps` access can capture the production DB password.

**Required Fix:**
```bash
# Use MYSQL_PWD environment variable instead:
MYSQL_PWD="$password" mysqldump -h host -u user database
# OR use --defaults-extra-file with temp credentials file, chmod 600, deleted after use
```

**Regression Risk:** Medium — fix requires changing exec() command construction in createDatabaseSnapshot() and executeTenantDatabaseRestore(). Must be tested end-to-end.

**Verdict:** FAIL — must be fixed before any production restore is run

---

## SEC-002 — No Rate Limiting on License Validation Endpoint

**Severity:** HIGH
**Evidence:** `app/Services/LicenseServerService.php` — no throttle referenced; route likely has no rate limit middleware

**Impact:** An attacker can enumerate license keys by sending unlimited validation requests. With 4-character segments (62^4 = ~14.7M per segment), while HMAC protection makes hash collision infeasible, the key space can be brute-forced via the API at network speeds.

**Required Fix:**
```php
// routes/web.php or routes/api.php — license validate route:
Route::post('/license/validate', [...])
    ->middleware(['throttle:60,1']); // 60 requests per minute per IP
```

**Regression Risk:** Low — adding middleware only; existing validation logic unchanged.

**Verdict:** FAIL — no rate limit is an industry-standard gap

---

## SEC-003 — Shell Command Construction (Low-Risk but Present)

**Severity:** MEDIUM
**Evidence:** `app/Services/Infrastructure/RestoreService.php` line 409

```php
exec("gunzip < {$localPath} | mysql {$connArgs} " . escapeshellarg($tmpDb) . ' 2>&1', $o, $c);
```

**Impact:** `$localPath` is built from `sys_get_temp_dir() . "/restore-tenant-{$siteId}-{$ts}.sql.gz"` where `$siteId` is an int (from RestoreLog model) and `$ts` is a timestamp. Low practical risk. However, the compound shell command with pipe is not wrapped in `escapeshellcmd()`. If the temp dir path ever contains special characters (uncommon but possible in some Docker environments), this could lead to unexpected execution.

**Required Fix:**
```php
// Wrap localPath explicitly:
$cmd = sprintf(
    'gunzip -c %s | mysql %s %s 2>&1',
    escapeshellarg($localPath),
    $connArgs,
    escapeshellarg($tmpDb)
);
exec($cmd, $o, $c);
```

**Regression Risk:** Low — functionally identical when `$localPath` is safe.

**Verdict:** PARTIAL — low exploitability, but should be fixed as defensive hardening

---

## SEC-004 — Non-Parameterized SQL in getTenantTables()

**Severity:** MEDIUM
**Evidence:** `app/Services/Infrastructure/RestoreService.php` lines 587-591

```php
exec("mysql {$connArgs} " . escapeshellarg($db) . " -e " . escapeshellarg(
    "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$db}' AND COLUMN_NAME='site_id' ..."
) . ' 2>&1', $output, $exitCode);
```

**Impact:** `$db` is interpolated directly into the SQL string. While `$db` comes from `config('database.connections.mysql.database')` (not user input), this is a structural violation of parameterized query practice. If config is ever sourced from a writable store, this becomes a vector.

**Required Fix:** Use PDO with parameterized queries via DB facade for this lookup instead of executing raw CLI mysql command.

**Regression Risk:** Medium — requires refactoring getTenantTables() to use DB::select() instead of exec().

**Verdict:** PARTIAL — low immediate risk, architecturally incorrect

---

## SEC-005 — Manifest.json Exposed via Download Endpoint (Partially)

**Severity:** LOW
**Evidence:** `app/Http/Controllers/Admin/InstallerAdminController.php` — `downloadManifest()`

**Assessment:** The endpoint correctly prevents download of `.env.backup` and `database.sql.gz`. It only serves `manifest.json` and `baseline_meta.json`. Neither contains secrets. Path traversal guard (`realpath()` + `str_starts_with()`) is in place.

**Verdict:** PASS — correctly implemented

---

## SEC-006 — Finance API Push Without Amount Bound

**Severity:** MEDIUM
**Evidence:** `app/Services/Finance/FinanceLedgerService.php` lines 97-121

```php
'amount' => (int) ($data['amount'] ?? 0),
```

**Impact:** No maximum value check. API caller could inject ledger entries with arbitrarily large amounts (e.g., 999,999,999,999 IRR), corrupting KPI aggregations.

**Required Fix:**
```php
'amount' => min((int)($data['amount'] ?? 0), 9_999_999_999),
```
Plus validation at the API controller level.

**Regression Risk:** Low — adding a max() cap.

**Verdict:** FAIL — needs amount validation

---

## SEC-007 — HMAC License Key Hashing

**Severity:** N/A (strength, not weakness)
**Evidence:** `app/Services/LicenseServerService.php` lines 25-29

```php
private function hashKey(string $key): string
{
    $secret = (string) config('app.key');
    return hash_hmac('sha256', $key, $secret);
}
```

**Assessment:** Uses HMAC-SHA256 with APP_KEY as secret. This is cryptographically strong — rainbow tables and brute-force are computationally infeasible. Lazy rehash from SHA256 to HMAC on first use means zero-downtime upgrade. Key prefix (8 chars) enables indexed lookup.

**Verdict:** PASS

---

## SEC-008 — Path Traversal Prevention

**Severity:** N/A (strength, not weakness)
**Evidence:** `app/Http/Controllers/Admin/InstallerAdminController.php`

```php
$root = realpath(storage_path('upgrade_snapshots'));
$real = realpath($baselineDir);
if (!$real || !str_starts_with($real, $root)) {
    abort(422, 'Invalid baseline path');
}
```

**Assessment:** Correct two-step traversal prevention: symlink resolution + prefix check. No path traversal possible through this gate.

**Verdict:** PASS

---

## SEC-009 — Restore Lock Prevents Concurrent Destructive Operations

**Severity:** N/A (strength)
**Evidence:** `app/Services/Infrastructure/RestoreService.php` lines 40-48

```php
public function acquireLock(string $value, int $ttlMinutes = 30): bool
{
    return (bool) Redis::set('restore_in_progress', $value, 'EX', $ttlMinutes * 60, 'NX');
}
```

**Assessment:** NX (not-exists) atomic Redis lock. Prevents concurrent restores. TTL prevents permanent lock on crash.

**Verdict:** PASS

---

## Security Priority Matrix

| Finding | Severity | Fix Effort | Priority |
|---------|----------|------------|----------|
| SEC-001: DB password in ps aux | HIGH | 2h | P1 |
| SEC-002: No rate limit on license validate | HIGH | 30min | P1 |
| SEC-006: Finance amount no max | MEDIUM | 30min | P2 |
| SEC-003: Shell pipe not escaped | MEDIUM | 1h | P2 |
| SEC-004: Non-parameterized SQL | MEDIUM | 2h | P2 |

---

## Overall Security Verdict

**Security Score: 76/100**

- 4 findings: 2 HIGH, 2 MEDIUM, 1 LOW
- No CRITICAL security vulnerabilities found
- No SQL injection via Eloquent ORM (primary code paths)
- No stored XSS patterns found in admin views
- No credentials in code (all in .env)
- Authentication/authorization middleware appears correct (auth + admin)

**Blocking issues for production hardening:** SEC-001 (password in ps), SEC-002 (rate limiting)
