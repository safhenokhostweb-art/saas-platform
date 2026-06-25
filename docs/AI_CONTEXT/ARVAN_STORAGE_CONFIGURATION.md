# ARVAN STORAGE CONFIGURATION
> S3-compatible filesystem disk configuration for ArvanCloud object storage.
> Generated: 2026-06-24

---

## DISK CONFIGURATION

**Disk name:** `arvan_backup`
**Driver:** `s3` (S3-compatible API)
**Bucket:** `safhenokhost-backups`
**Region:** `ir-thr-at1`
**Endpoint:** ArvanCloud S3 endpoint
**Path-style:** `true` (required for ArvanCloud)

**Config location (both repos):**
- `config/filesystems.php` → `disks.arvan_backup`

**Environment variables (both repos):**
```
ARVAN_ACCESS_KEY=
ARVAN_SECRET_KEY=
ARVAN_BUCKET=safhenokhost-backups
ARVAN_ENDPOINT=https://safhenokhost-backups.s3.ir-thr-at1.arvanstorage.ir
ARVAN_REGION=ir-thr-at1
```

## CREDENTIAL SOURCE

- All credentials read from `.env` via `env()` — zero hardcoded values
- saas-platform `.env`: ✅ All 4 ARVAN_* variables present
- customer-site `.env`: ❌ Missing — must be added manually before customer-site deploy

## VERIFICATION

- Disk configured with `throw: false` (non-fatal on failure)
- Visibility set to `private` (backups must never be public)
- Path-style endpoint enabled (ArvanCloud requirement)

## USAGE

```php
// Upload a file
Storage::disk('arvan_backup')->put('saas-platform/database/file.sql.gz', $stream);

// Check existence
Storage::disk('arvan_backup')->exists('saas-platform/database/file.sql.gz');

// Get file size
Storage::disk('arvan_backup')->size('saas-platform/database/file.sql.gz');
```

## STORAGE STRUCTURE

```
safhenokhost-backups/
  saas-platform/
    database/       ← DB backups
    archives/       ← Lifecycle archives
    storage/        ← Storage snapshots
  customer-sites/
    tenant-{id}/
      database/     ← Tenant DB backups
      archives/     ← Tenant archives
      storage/      ← Tenant storage
