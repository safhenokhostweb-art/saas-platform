# PRODUCTION STORAGE AUDIT
> Generated: 2026-06-16
> Scope: saas-platform + customer-site — all file system operations
> Auditor: Claude Code (PROD-003 / PROD-004 / PROD-005 hardening phase)

---

## Methodology

Scanned for:
- `mkdir`, `is_dir`, `rmdir`, `unlink`, `rename`, `copy`, `move_uploaded_file`
- `fopen`, `fclose`, `fwrite`, `file_put_contents`, `file_get_contents`
- `storage_path`, `public_path`, `$file->move()`

All raw filesystem calls were classified by risk and assigned a replacement strategy.

---

## SAAS-PLATFORM FINDINGS

### [SP-01] BackupDatabaseCommand — mkdir + unlink
| Field | Value |
|-------|-------|
| File | `app/Console/Commands/BackupDatabaseCommand.php:37-38,90` |
| Operations | `is_dir()`, `mkdir($backupDir, 0750, true)`, `@unlink($file)` |
| Risk | **LOW** |
| Reason | Database backup is a system-level operation that cannot go through a Storage disk (mysqldump writes to a pipe then gzip to a local path). The directory is within `storage_path()`. Backup file pruning is safe because paths come from `glob()` on a controlled directory. |
| Replacement | No change required. Acceptable exception documented. |

---

### [SP-02] InstallController — rename (atomic .env write)
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/InstallController.php:44` |
| Operations | `rename($tmpPath, $envPath)` |
| Risk | **LOW** |
| Reason | Hardened in INFRA-3. Atomic write: file_put_contents to temp → rename. Only runs during platform installation, protected by install-lock check. Acceptable bootstrapping exception. |
| Replacement | Already hardened — no change required. |

---

### [SP-03] StorageManager — fopen/fclose/fwrite/unlink (internal)
| Field | Value |
|-------|-------|
| File | `app/Services/Infrastructure/StorageManager.php:51,56,82,84,90,93,98,100` |
| Operations | `fopen`, `fclose`, `fwrite`, `@unlink` |
| Risk | **NONE** |
| Reason | These are the legitimate internals of the storage abstraction layer itself. All calls are to PHP system temp (`sys_get_temp_dir()`), never to user-controlled paths. The temp paths use `Str::random(16)` prefixes. |
| Replacement | No change required. Add `sanitizeFilename()` and `validateUpload()` methods for callers. |

---

### [SP-04] AppReleaseController — getClientOriginalName() in filename
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Admin/AppReleaseController.php:55` |
| Operations | `$filename = "v{$version}-build{$build}-" . $file->getClientOriginalName()` |
| Risk | **HIGH** |
| Reason | `getClientOriginalName()` returns user-supplied data that can contain `../`, null bytes, path separators, or encoding tricks. Although the StorageManager handles the actual write, a crafted filename can cause: (1) unexpected overwrite if disk implementation resolves the path, (2) directory traversal on some Flysystem adapters. |
| Replacement | **PROD-004**: Sanitize via `StorageManager::sanitizeFilename()` before constructing the final filename. |

---

### [SP-05] SecurityValidationService — storage_path() for existence check
| Field | Value |
|-------|-------|
| File | `app/Services/SecurityValidationService.php:60` |
| Operations | `$publicPath = storage_path('app/public/ticket-attachments')` |
| Risk | **LOW** |
| Reason | Read-only check used to detect if attachments are in the wrong disk. Not a write operation. However, it bypasses Storage abstraction and is coupled to disk layout. |
| Replacement | **PROD-004**: Replace with `Storage::disk('public')->path('ticket-attachments')`. |

---

### [SP-06] FormSubmissionController — fopen('php://output') for CSV
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Admin/FormSubmissionController.php:103` |
| Operations | `fopen('php://output', 'w')`, `fclose($out)` inside `response()->streamDownload()` |
| Risk | **MEDIUM** |
| Reason | Writes directly to the HTTP response body. Works on local disk but prevents S3/MinIO routing because no disk abstraction is used. Also holds result set in memory (limit 10,000 rows). |
| Replacement | **PROD-005**: Inject `StorageManager`, write to `exports` disk via `writeCsv()`, return `download()` response. Cleanup: add `deleteExport()` in StorageManager. |

---

### [SP-07] ThemeBuilderController — file_get_contents(getRealPath())
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Admin/ThemeBuilderController.php:165` |
| Operations | `file_get_contents($request->file('file')->getRealPath())` |
| Risk | **MEDIUM** |
| Reason | Reads theme JSON from PHP temp file path. On PHP 8.4 + Windows, `getRealPath()` may return false for junction-pointed temp dirs. Not a traversal risk (reads only from PHP-managed temp dir) but bypasses Storage abstraction. |
| Replacement | **OUT OF SCOPE** (Theme Builder excluded). Document as future improvement. |

---

### [SP-08] HealthCheckService / SiteHealthChecker — fclose on sockets
| Field | Value |
|-------|-------|
| File | `app/Services/Infrastructure/Monitoring/HealthCheckService.php:102`, `SiteHealthChecker.php:136` |
| Operations | `fclose($connection)` on socket from `fsockopen()` |
| Risk | **NONE** |
| Reason | Network socket close, not a filesystem operation. Standard pattern for TCP port checks. No filesystem risk. |
| Replacement | No change required. |

---

### [SP-09] Kernel.php — storage_path() in appendOutputTo()
| Field | Value |
|-------|-------|
| File | `app/Console/Kernel.php:22,43,49,55` |
| Operations | `storage_path('logs/...')` in `appendOutputTo()` |
| Risk | **NONE** |
| Reason | Standard Laravel scheduler pattern. Log files are in the application's own storage directory. No user input involved. |
| Replacement | No change required. |

---

### [SP-10] ScanSecretsCommand — storage_path() for log scanning
| Field | Value |
|-------|-------|
| File | `app/Console/Commands/ScanSecretsCommand.php:29-30` |
| Operations | `storage_path('logs')`, `storage_path('framework/cache')` |
| Risk | **NONE** |
| Reason | Read-only security scan of own application files. No user input. |
| Replacement | No change required. |

---

### [SP-11] CustomerController — CSV export (already StorageManager)
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Admin/CustomerController.php:161,163` |
| Operations | `$this->storage->writeCsv()`, `$this->storage->download()` |
| Risk | **NONE** |
| Reason | Already routes through StorageManager. Uses `exports` disk. Compliant. |
| Replacement | No change required. |

---

### [SP-12] UserController / UserService — CSV export+import (already StorageManager)
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Admin/UserController.php`, `app/Services/UserService.php` |
| Operations | `$this->storage->writeCsv()`, `$this->storage->readStream()`, `fgetcsv()`, `fclose()` |
| Risk | **NONE** |
| Reason | Export uses StorageManager. Import reads via StorageManager.readStream(). The `fgetcsv()`/`fclose()` operate on the resource returned by StorageManager — acceptable pattern. |
| Replacement | No change required. |

---

### [SP-13] MobileTicketController — path traversal check (insufficient)
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Api/Mobile/MobileTicketController.php:251` |
| Operations | `if (str_contains($safePath, '..')) abort(403)` |
| Risk | **HIGH** |
| Reason | Current traversal check only blocks literal `..`. Does not block: URL-encoded traversal (`%2e%2e`), null bytes, absolute paths, or Windows-style paths (`C:\`). A crafted `path` parameter could escape the disk root on some Flysystem adapters. |
| Replacement | **PROD-004**: Replace with `StorageManager::sanitizeFilename()` + reject absolute paths + normalize separators. |

---

### [SP-14] PlatformSettings — storeLogo() validation gap
| Field | Value |
|-------|-------|
| File | `app/Helpers/PlatformSettings.php:421-435` |
| Operations | `Storage::disk($uploadDisk)->exists()`, `Storage::disk($uploadDisk)->delete()` (bypasses StorageManager for delete) |
| Risk | **MEDIUM** |
| Reason | The upload itself goes through StorageManager, but (1) old logo deletion bypasses StorageManager, (2) no explicit extension allowlist enforced in the method (relies solely on controller's `image` validation rule), (3) client extension and MIME are not validated inside the method itself (defense in depth gap). |
| Replacement | **PROD-003**: Route delete through StorageManager. Add extension allowlist + MIME check inside `storeLogo()`. |

---

### [SP-15] PlatformSettingsController — logo_path input unfiltered
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Admin/PlatformSettingsController.php:57-66` |
| Operations | `$raw = trim($request->platform_logo_path)` → stored directly if starts with `/` |
| Risk | **HIGH** |
| Reason | If a user provides `/etc/passwd` or `/../private/key.pem`, that absolute path is saved as the logo path. If `logoUrl()` ever serves the file directly (or a future feature reads the stored path), this becomes a path disclosure or LFI vector. |
| Replacement | **PROD-003**: Reject any `platform_logo_path` that is not an `https?://` URL or a safe relative path. Strip leading slashes. |

---

## CUSTOMER-SITE FINDINGS

### [CS-01] Ticket attachment upload — 5 controllers
| Field | Value |
|-------|-------|
| Files | `TicketController.php:159-168`, `CustomerMobileTicketController.php:137-142`, `SupportApiClient.php:78-87`, `UserTicketController.php:191-198`, `UnifiedSupportController.php:468-477` |
| Operations | `public_path()` + `mkdir()` + `$file->move()` |
| Risk | **CRITICAL** |
| Reason | All 5 locations write uploaded files directly to `public/uploads/{subdir}` using raw PHP filesystem calls. Risks: (1) no extension allowlist enforcement at filesystem level, (2) mkdir creates world-readable directories in the public webroot, (3) $file->move() does not validate the destination path at the framework level, (4) uploaded files are served directly from the public webroot without any access control layer. |
| Replacement | Migrate to `StorageManager::storeUploadedFile()` with `private` disk. **Currently OUT OF SCOPE** (Support Tickets module — scheduled separately). |

---

### [CS-02] Admin profile avatar + user avatar upload
| Field | Value |
|-------|-------|
| Files | `AdminProfileController.php:39-45`, `ProfileAvatarController.php:29-33` |
| Operations | `public_path()` + `mkdir()` + `$file->move()` |
| Risk | **HIGH** |
| Reason | Same pattern as CS-01. Writes avatars to public webroot without access control. No extension or MIME check at filesystem level. |
| Replacement | Migrate to `StorageManager::storeUploadedFile()` with `uploads` disk. **OUT OF SCOPE** — schedule separately. |

---

### [CS-03] SmartImageUploadService — file_put_contents temp + @unlink
| Field | Value |
|-------|-------|
| File | `app/Services/SmartImageUploadService.php:73,294,308` |
| Operations | `file_put_contents($tmpFile, $body)`, `@unlink($processedPath)` |
| Risk | **MEDIUM** |
| Reason | Temp file written to system temp dir for AI processing, then unlinked. Path generated by `tempnam()` (safe). Also `file_get_contents($path)` used to send image to AI endpoint. Controlled path (no user input in path). |
| Replacement | **OUT OF SCOPE** (IMG module). Document as future improvement. |

---

### [CS-04] InstallController + Installation.php
| Field | Value |
|-------|-------|
| Files | `InstallController.php:75,86,187`, `Support/Installation.php:30,36,40,41` |
| Operations | `file_get_contents`, `file_put_contents`, `is_dir`, `rename`, `@unlink` |
| Risk | **LOW** |
| Reason | Bootstrapping concern. .env and installed.lock management required at filesystem level. Atomic write pattern used. No user input in paths. |
| Replacement | No change required. Acceptable bootstrapping exception. |

---

### [CS-05] BackupDatabaseCommand — mkdir + unlink
| Field | Value |
|-------|-------|
| File | `app/Console/Commands/BackupDatabaseCommand.php:24,191` |
| Operations | `storage_path('backups')`, `mkdir()`, `@unlink()` |
| Risk | **LOW** |
| Reason | Same pattern as SP-01. System-level backup operation. Acceptable. |
| Replacement | No change required. |

---

### [CS-06] NotificationController — fopen on uploaded file
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Admin/NotificationController.php:60,63` |
| Operations | `fopen($uploadedFile->getPathname(), 'r')` + `fclose()` |
| Risk | **MEDIUM** |
| Reason | Reads PHP temp file to stream notification attachment. Similar to ThemeBuilderController pattern (SP-07). On Windows + PHP 8.4, getPathname() may return a junction-resolved path. |
| Replacement | Use `$uploadedFile->getContent()` or store via StorageManager first. **OUT OF SCOPE** — schedule separately. |

---

### [CS-07] FormSubmissionController — fopen('php://output')
| Field | Value |
|-------|-------|
| File | `app/Http/Controllers/Admin/FormSubmissionController.php:99` |
| Operations | `fopen('php://output', 'w')` inside `streamDownload()` |
| Risk | **MEDIUM** |
| Reason | Same as SP-06. Does not use Storage abstraction. |
| Replacement | **OUT OF SCOPE** (Forms UX excluded). Future improvement. |

---

## SUMMARY TABLE

| ID | File | Risk | PROD Item | Status |
|----|------|------|-----------|--------|
| SP-01 | BackupDatabaseCommand | LOW | — | Acceptable |
| SP-02 | InstallController | LOW | — | Already hardened |
| SP-03 | StorageManager internals | NONE | — | By design |
| SP-04 | AppReleaseController filename | HIGH | PROD-004 | **FIX REQUIRED** |
| SP-05 | SecurityValidationService storage_path | LOW | PROD-004 | **FIX REQUIRED** |
| SP-06 | FormSubmissionController fopen | MEDIUM | PROD-005 | **FIX REQUIRED** |
| SP-07 | ThemeBuilderController getRealPath | MEDIUM | — | Out of scope |
| SP-08 | HealthCheckService socket fclose | NONE | — | Acceptable |
| SP-09 | Kernel.php appendOutputTo | NONE | — | Acceptable |
| SP-10 | ScanSecretsCommand | NONE | — | Acceptable |
| SP-11 | CustomerController CSV | NONE | — | Already compliant |
| SP-12 | UserController/Service CSV | NONE | — | Already compliant |
| SP-13 | MobileTicketController path traversal | HIGH | PROD-004 | **FIX REQUIRED** |
| SP-14 | PlatformSettings storeLogo | MEDIUM | PROD-003 | **FIX REQUIRED** |
| SP-15 | PlatformSettingsController logo_path | HIGH | PROD-003 | **FIX REQUIRED** |
| CS-01 | Ticket attachment (5 controllers) | CRITICAL | — | Out of scope |
| CS-02 | Avatar upload (2 controllers) | HIGH | — | Out of scope |
| CS-03 | SmartImageUploadService | MEDIUM | — | Out of scope |
| CS-04 | InstallController + Installation | LOW | — | Acceptable |
| CS-05 | BackupDatabaseCommand (CS) | LOW | — | Acceptable |
| CS-06 | NotificationController | MEDIUM | — | Out of scope |
| CS-07 | FormSubmissionController (CS) | MEDIUM | — | Out of scope |

---

## REQUIRED FIXES (In-scope)

| PROD | Target | Action |
|------|--------|--------|
| PROD-003 | `PlatformSettings::storeLogo()` | Add extension allowlist + MIME validation + route delete through StorageManager |
| PROD-003 | `PlatformSettingsController::update()` | Reject absolute/traversal paths in `platform_logo_path` input |
| PROD-003 | `StorageManager` | Add `sanitizeFilename()` + `validateUpload()` methods |
| PROD-004 | `AppReleaseController::store()` | Sanitize `getClientOriginalName()` via `StorageManager::sanitizeFilename()` |
| PROD-004 | `MobileTicketController::downloadAttachment()` | Full path traversal prevention (null bytes, absolute paths, encoded sequences) |
| PROD-004 | `SecurityValidationService::checkPublicStorageLeaks()` | Replace `storage_path()` with `Storage::disk('public')->path()` |
| PROD-005 | `FormSubmissionController::export()` (saas-platform) | Inject StorageManager, use `writeCsv()` + `download()` instead of `fopen('php://output')` |

---

## OUT-OF-SCOPE BACKLOG (For future phases)

| ID | File | Priority |
|----|------|----------|
| CS-01 | 5 ticket attachment controllers (customer-site) | CRITICAL — schedule next |
| CS-02 | Avatar upload controllers (customer-site) | HIGH |
| SP-07 | ThemeBuilderController JSON import | MEDIUM |
| CS-06 | NotificationController fopen | MEDIUM |
| CS-07 | FormSubmissionController (customer-site) | MEDIUM |
| CS-03 | SmartImageUploadService | MEDIUM |
