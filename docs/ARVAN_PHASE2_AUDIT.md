# ARVAN OFFSITE BACKUP — PHASE 2 AUDIT
> Date: 2026-06-25
> Status: ARVAN_PHASE2_AUDIT_COMPLETE
> Purpose: Map all backup workflows, identify missing Arvan integrations

---

## Summary

| Workflow | Current Destination | Arvan Integration | Coverage |
|----------|-------------------|-------------------|----------|
| Database Backup | `storage/app/backups/db_backup_*.sql.gz` | ✅ `platformDbPath()` → `saas-platform/database/` | OPERATIONAL |
| Archive Backup | `storage/app/archives/site_{id}_{date}.zip` | ❌ MISSING | NOT INTEGRATED |
| Storage Backup | `storage/app/public/` (live files, no snapshot) | ❌ MISSING | NO WORKFLOW |
| Tenant Backup | Inside site archive ZIP (tenant data) | ❌ MISSING | NOT INTEGRATED |

**Coverage before Phase 2:** 1 / 4 workflows = 54.5% (db path + upload/exists/size reused)
**Target after Phase 2:** 4 / 4 workflows = 100%

---

## Workflow 1 — Database Backup ✅ OPERATIONAL

**Command:** `php artisan db:backup --retention=7`
**Schedule:** Daily at 02:00 (Kernel.php)
**Local path:** `storage/app/backups/db_backup_{Y-m-d_His}.sql.gz`
**Arvan path:** `saas-platform/database/{filename}`
**Path builder:** `ArvanBackupService::platformDbPath($filename)`
**Upload method:** `ArvanBackupService::upload()` — retry×3, size verification
**On failure:** Slack alert via `SlackAlertService::critical()`
**Source file:** `app/Console/Commands/BackupDatabaseCommand.php`
**Status:** FULLY OPERATIONAL — no change required

---

## Workflow 2 — Archive Backup ❌ NOT INTEGRATED

**Trigger:** `SiteLifecycleService::archive()` → calls `SiteArchiveService::createArchivePackage()`
**Schedule trigger:** `lifecycle:check-suspended` (daily 03:05) → archives sites suspended > 90 days
**Manual trigger:** Admin lifecycle panel
**What is archived:** Site configuration JSON, theme builder pages/blocks, modules config, README
**Local path:** `storage/app/archives/site_{siteId}_{Ymd_His}.zip`
**DB column:** `sites.archive_path = 'archives/{archiveName}'`
**Arvan path:** **NONE** — `platformArchivePath()` exists but never called
**Service:** `app/Services/Lifecycle/SiteArchiveService.php`
**Method:** `createArchivePackage(Site $site): string`

### Required Change

After `$site->save()` (line 47 in SiteArchiveService), call:
```
ArvanBackupService::upload($archivePath, platformArchivePath($archiveName))
```
→ uploads to `saas-platform/archives/{archiveName}`

This covers **Archive Backup** workflow and activates `platformArchivePath()`.

---

## Workflow 3 — Storage Backup ❌ NO EXISTING WORKFLOW

**What needs backing up:** `storage/app/public/` — all customer-uploaded media (product images,
blog covers, profile photos, etc.)
**Current state:** Files exist on VPS disk only — no backup process exists
**Risk documented:** HIGH (OFFSITE_BACKUP_AUDIT.md Section 3)
**Arvan path:** `platformStoragePath()` → `saas-platform/storage/{filename}` — exists but unused

### Required Change

Create new `BackupStorageCommand` (`php artisan storage:backup`):
1. Create tar.gz of `storage/app/public/`
2. Upload using `ArvanBackupService::upload($localPath, platformStoragePath($filename))`
3. Delete local tar.gz after upload attempt (original files remain on disk)
4. Alert via Slack on failure
5. Schedule: weekly Sunday 03:30 (after DB backup and resource cleanup)

This covers **Storage Backup** workflow and activates `platformStoragePath()`.

---

## Workflow 4 — Tenant Backup ❌ NOT INTEGRATED

**What is a tenant backup:** A per-tenant isolation copy of the site archive ZIP, stored under
the tenant's own prefix in the Arvan bucket. Allows DR targeting a single tenant without
scanning the platform-wide archives prefix.
**Source:** Same `SiteArchiveService::createArchivePackage()` that creates the local ZIP
**Arvan path:** `tenantPath($siteId, 'archives', $filename)` → `customer-sites/tenant-{siteId}/archives/{filename}`
**Path builder:** `ArvanBackupService::tenantPath(int $siteId, string $type, string $filename)` — exists but never called

### Required Change

Same `createArchivePackage()` call site — upload to BOTH paths:
1. `platformArchivePath($archiveName)` → platform-wide archives view (Workflow 2 above)
2. `tenantPath($site->id, 'archives', $archiveName)` → per-tenant isolated view

Both calls reuse the same `ArvanBackupService::upload()` retry + verification logic.

This covers **Tenant Backup** workflow and activates `tenantPath()`.

---

## Archive Cleanup Workflow

**Command:** `lifecycle:check-archived` (daily 04:05)
**Flow:** `SiteLifecycleService::delete()` → `SiteArchiveService::deleteArchiveZip()`
**Current behavior:** Deletes local ZIP from disk, sets `sites.archive_path = null`
**Arvan behavior:** Does NOT delete from Arvan — intentional

**Decision:** Arvan copies are kept permanently for extended disaster recovery.
After a site is permanently deleted from the platform, its Arvan backup remains as an
audit trail. This is the correct behavior for a backup system.
No change required here.

---

## Files to Modify

| File | Change Type | Scope |
|------|------------|-------|
| `app/Services/Lifecycle/SiteArchiveService.php` | Modify | Add constructor + `uploadToArvan()` method + 1 call site |
| `app/Console/Commands/BackupStorageCommand.php` | Create | New command (storage backup) |
| `app/Console/Kernel.php` | Modify | Add 1 schedule line |

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Console/Commands/BackupStorageCommand.php` | Storage backup command |
| `docs/ARVAN_PHASE2_AUDIT.md` | This file |
| `docs/ARVAN_PHASE2_CERTIFICATION.md` | Post-implementation certification |

---

## No-Change Confirmation

| Component | Decision |
|-----------|----------|
| `ArvanBackupService.php` | DO NOT MODIFY — reuse as-is |
| `BackupDatabaseCommand.php` | DO NOT MODIFY — already operational |
| `config/filesystems.php` | DO NOT MODIFY — `arvan_backup` disk already configured |
| Retry logic | REUSED from `ArvanBackupService::upload()` |
| Verification logic | REUSED from `ArvanBackupService::upload()` |
| Retention policy | UNCHANGED — local 7-day retention stays |

---

*ARVAN_PHASE2_AUDIT — generated 2026-06-25*
