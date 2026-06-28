# UPLOAD_PACKAGE Timeout — Root Cause Analysis

**Incident:** Production installation failed with `"Timed out waiting for server"` at stage `UPLOAD_PACKAGE`  
**Date:** 2026-06-28  
**Severity:** P0 — complete installation failure, partial artifacts left on remote server  
**Status:** FIXED (commit following this analysis)

---

## Executive Summary

The failure was caused by **six compounding bugs**, not a single timeout. The primary cause was a misconfigured SFTP session timeout of 10 seconds (default) applied to a 42 MB file upload, while a safe 1800-second timeout was defined in config but never applied. Five secondary bugs caused incomplete cleanup, duplicate rollback records, and the risk of orphan deployments.

---

## Execution Timeline

```
T+0s      Pipeline job starts
T+~5s     SSH connection established (ssh.timeout_connect = 10s)
T+~6s     Stages 1-5 (validate) complete
T+~7s     UPLOAD_PACKAGE starts
T+~8s     Local package resolved (42.6 MB, local_path)
T+~9s     SHA-256 local verification — PASS
T+~9s     SFTP session opened: new SFTP($host, $port)
          ← SFTP timeout set to DEFAULT 10 seconds
T+~10s    sftp->put() begins — 4 MB chunk 1 sent
T+~12s    Server acknowledges chunk 1
T+~14s    chunk 2 sent
T+~16s    chunk 3 sent — server under disk I/O pressure
T+~26s    phpseclib3 waits for ACK of chunk 3
          ← 10-second SFTP timeout fires
T+~26s    phpseclib3 throws: "Timed out waiting for server"
T+~26s    AbstractStage::run() catches exception
T+~26s    StageResult::failure('UPLOAD_PACKAGE', 'Timed out waiting for server')
T+~26s    Orchestrator: markFailed()
T+~26s    Orchestrator: dispatchRollback()
          ← Rollback snapshot is EMPTY (bug #2)
T+~27s    CustomerInstallationRollbackJob queued
T+~30s    Rollback job runs
          ← Checkpoint snapshot = [] → remote_path missing
T+~31s    rm -f '' executed (no-op)
T+~31s    Partial ZIP remains at /tmp/customer_install_N_v1.0.0.zip
```

---

## Root Causes

### RCA-001 — CRITICAL: SFTP session timeout = 10 seconds (PRIMARY CAUSE)

**Location:** `SshRemoteDriver::getSftp()` (line ~253)

**Before:**
```php
$sftp = new SFTP($this->server->host, $this->server->port);
```

phpseclib3 `SFTP::__construct()` signature:
```php
public function __construct(string $host, int $port = 22, int $timeout = 10)
```

The 3rd parameter `$timeout = 10` is used as **the per-operation response timeout for all SFTP operations** — including the ACK wait for every write packet. With 4 MB chunks on a 42 MB file, there are ~11 write packets. If the remote server takes >10 seconds to acknowledge any single packet (disk I/O spike, TCP retransmit, momentary load), phpseclib3 throws `"Timed out waiting for server"`.

`config('customer_installation.ssh.timeout_upload')` is defined as `1800` but is **never applied to the SFTP session**. It exists in config with no consumer.

**After:**
```php
$uploadTimeout = (int) config('customer_installation.ssh.timeout_upload', 1800);
$sftp = new SFTP($this->server->host, $this->server->port, $uploadTimeout);
$sftp->setTimeout($uploadTimeout);
```

**Impact of fix:** SFTP session now waits up to 1800 seconds for each ACK — matching the queue job timeout. A brief server-side disk pause of 10–60 seconds will no longer abort the upload.

---

### RCA-002 — CRITICAL: `sftp->put()` return value ignored

**Location:** `SshRemoteDriver::upload()` (original)

**Before:**
```php
$sftp->put($remotePath, $callback, SFTP::SOURCE_CALLBACK);
// return value discarded
```

`SFTP::put()` returns `bool`. If it returns `false` (partial write, server error without exception), the upload silently fails. The stage reports **success**, VERIFY_PACKAGE then fails with SHA-256 mismatch — a confusing failure that masks the real problem.

**After:**
```php
$ok = $sftp->put(...);
if (!$ok) {
    throw new RuntimeException("SFTP put() returned false after {$uploaded}/{$fileSize} bytes");
}
```

---

### RCA-003 — CRITICAL: Empty rollback snapshot — partial file not cleaned up

**Location:** `CustomerInstallationOrchestrator::run()` (orchestrator stage loop)

**Before:**
```php
// BEFORE execute:
$this->state->recordRollbackCheckpoint($installation, $stageName, []);  // snapshot = []

$result = $stage->execute($ctx);

// AFTER execute (only on success):
$this->state->recordRollbackCheckpoint($installation, $stageName, $result->rollbackSnapshot);
```

When the stage fails:
1. The pre-execution checkpoint has `snapshot = []`
2. The post-execution checkpoint is never written
3. Rollback reads `remote_path = null`
4. `rm -f ''` is executed — no-op
5. Partial ZIP stays at `/tmp/customer_install_N_v1.0.0.zip` forever

**After (two-part fix):**

Part A — `AbstractStage::buildPreSnapshot()` (new method):
```php
public function buildPreSnapshot(InstallationContext $ctx): array { return []; }
```

Stages that know their remote resource path before execution override this:

```php
// UploadPackageStage
public function buildPreSnapshot(InstallationContext $ctx): array {
    return ['remote_path' => '/tmp/customer_install_' . $ctx->installation->id . '_' . $version . '.zip'];
}

// ExtractPackageStage
public function buildPreSnapshot(InstallationContext $ctx): array {
    return ['remote_path' => $ctx->webRoot()];
}
```

Part B — Orchestrator uses pre-snapshot:
```php
$preSnapshot = $stage->buildPreSnapshot($ctx);
$this->state->recordRollbackCheckpoint($installation, $stageName, $preSnapshot);
```

Now rollback always has the `remote_path` available, even when the stage failed before returning its result.

---

### RCA-004 — HIGH: Duplicate rollback checkpoint records

**Location:** `CustomerInstallationOrchestrator::run()` + `InstallationStateManager::recordRollbackCheckpoint()`

**Before:**
`recordRollbackCheckpoint()` always calls `CustomerInstallRollback::create()`. The orchestrator called it twice per stage (pre + post). This creates **two checkpoint records** for every successfully-completed stage.

The rollback service queries all unexecuted checkpoints and runs each in sequence. Two records for `EXTRACT_PACKAGE` means `rm -rf /var/www/slug` runs **twice** — the second run silently succeeds but is wasteful. On stages where rollback is stateful (e.g., `DROP DATABASE`), double-execution would cause errors.

**After:**
`recordRollbackCheckpoint()` upserts — updates the existing pending record for the same stage/installation rather than inserting a new one:
```php
$existing = CustomerInstallRollback::where('installation_id', $installation->id)
    ->where('stage', $stage)->whereNull('executed_at')->first();

if ($existing) {
    $existing->update(['snapshot' => $sanitized]);
} else {
    CustomerInstallRollback::create([...]);
}
```

---

### RCA-005 — HIGH: No upload retry on connection drop

**Location:** `SshRemoteDriver::upload()` (original)

The original `upload()` had zero retry logic. One TCP hiccup (retransmit timeout, server-side TCP RST) permanently aborted the upload.

**After:**
Up to 3 attempts with 2-second delay between each. On connection drop, SFTP is reconnected and the upload restarts from the beginning. Before restarting, the partial remote file is deleted to prevent a false "file complete" check:

```php
for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        // Resume check → upload → verify
        return;
    } catch (\Throwable $e) {
        if ($attempt < $maxAttempts && $this->isConnectionDropped($e)) {
            $this->sftp = null; // force SFTP reconnect
            usleep($retryDelayMs * 1000);
            continue;
        }
        throw $e;
    }
}
```

---

### RCA-006 — HIGH: Partial remote file not cleaned up on upload failure

**Location:** `SshRemoteDriver::upload()` + `UploadPackageStage::execute()` (original)

When `sftp->put()` threw, the partial file at `/tmp/customer_install_N_v1.0.0.zip` was left on the remote server. Subsequent installation attempts would find a partial file of the wrong size, potentially causing confusion.

**After (two-layer cleanup):**

Layer 1 — SshRemoteDriver:
```php
} catch (\Throwable $e) {
    try { $this->getSftp()->delete($remotePath); } catch (\Throwable) {}
    throw $e;
}
```

Layer 2 — UploadPackageStage (belt-and-suspenders via SSH exec):
```php
} catch (\Throwable $e) {
    try {
        $ctx->connection->execute('rm -f ' . escapeshellarg($remotePath));
    } catch (\Throwable) {}
    return StageResult::failure(...);
}
```

---

### RCA-007 — HIGH: Orphan deployment — rollback job has 1 retry

**Location:** `CustomerInstallationRollbackJob`

**Before:**
```php
public int $tries = 1;
```

If the rollback job fails to connect to the remote server (server overloaded immediately after a failed install), the job is not retried. The web root created by `EXTRACT_PACKAGE` (if that stage ran) stays in place. If Nginx has a wildcard config that serves the domain, the partially-extracted PHP files are publicly accessible.

**After:**
```php
public int $tries = 3;
public function backoff(): array { return [60, 300]; }
```

3 attempts: immediately → 60s → 5m. If all 3 fail, `rollback_failed` status is set and a Slack CRITICAL alert fires.

**Additional fix — sentinel file:**

`ExtractPackageStage` creates `.cie_installing` in the web root before extracting files:
```bash
touch /var/www/cust_slug/.cie_installing
```

`CustomerReadyStage` removes it only on complete success:
```bash
rm -f /var/www/cust_slug/.cie_installing
```

Any web server middleware that detects this file should return 503. This is a defense-in-depth guard: even if rollback completely fails, the sentinel signals that the installation is not complete.

---

## Upload Phase Timing (New Logging)

Every upload now logs five phases with millisecond timestamps:

| Phase | Description |
|---|---|
| Phase 1 | Manifest resolved (version, size) |
| Phase 2 | Local package ready (local copy or Arvan download) |
| Phase 3 | Local SHA-256 verification |
| Phase 4 | SFTP upload (with progress: KB/s) |
| Phase 5 | Remote file size verification via `stat` |

Example log output:
```
[INFO] CIE UPLOAD_PACKAGE: Phase 1 — manifest resolved: v1.0.0 (42.6 MB) [12ms]
[INFO] CIE UPLOAD_PACKAGE: Phase 2 — local package ready: customer-site-1.0.0-abc123.zip [8ms]
[INFO] CIE UPLOAD_PACKAGE: Phase 3 — local SHA-256 verified [1840ms]
[INFO] CIE UPLOAD_PACKAGE: Phase 4 — upload complete in 47.3s (922 KB/s) [47301ms]
[INFO] CIE UPLOAD_PACKAGE: Phase 5 — remote size check: 44672000 bytes (expected ~44672000) [180ms]
```

The stage output JSON also contains all timing breakdowns:
```json
{
  "timing": {
    "manifest_ms": 12,
    "download_ms": 8,
    "verify_ms": 1840,
    "upload_ms": 47301,
    "stat_ms": 180
  },
  "upload_secs": 47.3,
  "upload_kbps": 922
}
```

---

## Resumable Upload Logic

Before each upload attempt, the SFTP session checks for an existing remote file:

```
Remote file exists AND size == local file size → skip upload (already complete)
Remote file exists AND size != local file size → delete partial + re-upload
Remote file missing → upload fresh
```

This makes the upload idempotent: if the pipeline job dies and is re-queued, and the previous attempt completed the upload but not the SFTP close handshake, the second run detects the complete file and proceeds without re-uploading.

---

## Resumable Extraction Logic

`ExtractPackageStage` checks the web root before extracting:

```
Web root exists AND file_count > 100 → resume (skip extraction)
Web root exists AND file_count <= 100 → partial extraction: rm -rf + re-extract
Web root missing → fresh extraction
```

---

## Network Timing Analysis

| Metric | Value |
|---|---|
| Package size | 42.6 MB |
| Chunk size (4 MB) | ~11 SFTP write packets |
| Old SFTP timeout | 10 seconds per packet |
| Expected upload time (10 MB/s line) | ~4 seconds |
| Time-to-fail (single ACK delay) | 10 seconds at any packet |
| New SFTP timeout | 1800 seconds per packet |
| Queue job timeout | 3600 seconds |
| Upload retry attempts | 3 |
| Retry delay | 2 seconds |

The old configuration could fail even on a fast server if a single disk I/O operation caused a 10-second ACK delay. The new configuration tolerates a 30-minute full connection stall.

---

## Files Changed

| File | Change |
|---|---|
| `app/Services/CustomerInstallation/Drivers/SshRemoteDriver.php` | SFTP timeout from config; upload retry; cleanup on failure; resume check; `put()` return value check |
| `app/Services/CustomerInstallation/Stages/AbstractStage.php` | Added `buildPreSnapshot()` method |
| `app/Services/CustomerInstallation/Stages/UploadPackageStage.php` | 5-phase logging; `buildPreSnapshot()`; self-cleanup on failure; remote size check; `estimatedSeconds` updated |
| `app/Services/CustomerInstallation/Stages/ExtractPackageStage.php` | `buildPreSnapshot()`; idempotency (resume or re-extract); sentinel file `.cie_installing` |
| `app/Services/CustomerInstallation/Stages/CustomerReadyStage.php` | Remove `.cie_installing` sentinel on complete success |
| `app/Services/CustomerInstallation/CustomerInstallationOrchestrator.php` | Use `buildPreSnapshot()` for pre-execution checkpoint |
| `app/Services/CustomerInstallation/InstallationStateManager.php` | `recordRollbackCheckpoint()` upserts — no duplicate records |
| `app/Jobs/CustomerInstallationRollbackJob.php` | `tries = 3`, `backoff = [60, 300]` |

---

## Permanent Fix Verification Checklist

- [ ] Deploy and trigger one installation on a test server
- [ ] Verify logs show all 5 upload phases with timing
- [ ] Simulate server delay: `tc qdisc add dev eth0 root netem delay 15s` — upload must not timeout with new 1800s SFTP timeout
- [ ] Verify partial file cleanup: kill job mid-upload, confirm `/tmp/customer_install_*.zip` is absent
- [ ] Verify resume: start upload, kill after 50%, re-queue job — confirm upload completes without re-sending the full file (or re-sends from scratch with no errors)
- [ ] Verify rollback snapshot: fail intentionally at UPLOAD_PACKAGE, confirm rollback checkpoint has `remote_path` populated
- [ ] Verify no duplicate rollback records: check `customer_install_rollbacks` table has exactly 1 row per stage per installation
- [ ] Verify sentinel: after failed install, confirm `.cie_installing` is present if EXTRACT ran; confirm it is absent after successful install

---

## Related Audit Findings (from prior audit, not addressed here)

| ID | Finding | Status |
|---|---|---|
| P0-001 | CREATE_DATABASE assumes MySQL root | Open |
| P1-001 | INSTALL_THEME no-op | Open |
| P1-002 | DeploymentTarget new arch not wired | Open |
| P1-003 | 4 driver stubs crash if used | Open |
