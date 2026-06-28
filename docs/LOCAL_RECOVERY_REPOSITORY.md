# Local Recovery Repository

**Version:** 1.0  
**Status:** ENTERPRISE_CERTIFIED  
**Date:** 2026-06-28  

---

## Overview

The Local Recovery Repository provides a disk-based, administrator-configured backup destination that operates **in parallel with Arvan Object Storage**. Every platform baseline and every customer installation baseline is copied to both destinations according to the configured policy, giving the platform true dual-location redundancy.

---

## Architecture

### Repository Types

| Repository | Implementation | Status |
|---|---|---|
| Local Repository | `LocalRecoveryRepository` service | ACTIVE |
| Arvan Repository | `ArvanBackupService` | ACTIVE |
| Both | Parallel write (recommended) | DEFAULT |

### Directory Layout

```
{RECOVERY_LOCAL_PATH}/
├── Platform/
│   ├── INITIAL_INSTALLATION_BASELINE/      ← Never overwritten
│   │   ├── database.sql.gz
│   │   ├── .env.backup
│   │   ├── manifest.json
│   │   ├── baseline_meta.json
│   │   ├── INSTALLATION_REPORT.html
│   │   └── INSTALLATION_REPORT.pdf
│   └── INSTALLATION_BASELINE_20260628_143000/
│       └── (same structure)
└── Customers/
    └── Site-{siteId}/
        └── {installation-uuid}/
            └── {Ymd_His}/
                ├── customer_baseline.sql.gz
                └── checksum.txt
```

---

## Configuration

Set in `.env` (or via Admin → Infrastructure → خزانه بازیابی محلی):

| Variable | Default | Description |
|---|---|---|
| `RECOVERY_POLICY` | `both` | `local` / `arvan` / `both` |
| `RECOVERY_PRIORITY` | `local_first` | `local_first` / `arvan_first` |
| `RECOVERY_LOCAL_PATH` | `storage/app/local-recovery-repo` | Absolute path to local repo |
| `RECOVERY_MIN_FREE_GB` | `10` | Minimum free space required (GB) |

---

## Policy Matrix

| Policy | Local Write | Arvan Write |
|---|---|---|
| `local` | Always | Never |
| `arvan` | Never | Always |
| `both` | Always | Always |

---

## Failure Policy

| Scenario | Behaviour |
|---|---|
| Local fails, Arvan succeeds | Continue — warn in logs + Slack |
| Arvan fails, Local succeeds | Continue — warn in logs |
| BOTH fail | Abort — hard stop, no baseline created |

---

## Verification

Every stored file is verified immediately after copy using **SHA-256 streaming hash** (never loads full file into memory). The verification result is stored in `local_recovery_logs.status`:

| Status | Meaning |
|---|---|
| `verified` | SHA-256 matches source — file intact |
| `failed` | SHA-256 mismatch — file corrupted or tampered |
| `pending` | Stored but not yet verified |

### Platform Baseline Verification

Each file in the baseline directory is verified against the SHA-256 checksums in `manifest.json`. The manifest itself is verified via its self-covering `manifest_checksum` field (SHA-256 of the manifest body).

### Customer Baseline Verification

The `customer_baseline.sql.gz` SHA-256 is compared against the hash computed on the platform before upload to Arvan (same hash, two destinations).

---

## Platform Baseline Flow

```
Fresh Install (processFreshFinish)
    ↓
createInstallationBaseline()     → database.sql.gz + .env.backup + manifest.json
    ↓
addReportsToBaseline()           → copies HTML/PDF reports → updates manifest → lock read-only
    ↓
LocalRecoveryRepository
  └── storePlatformBaseline()    → copies entire baseline dir → SHA-256 verifies every file
    ↓
LocalRecoveryLog created         → status: verified | failed
```

---

## Customer Baseline Flow

```
INITIALIZE_BACKUP stage (pipeline step 19)
    ↓
mysqldump on remote server
    ↓
SFTP download to platform /tmp
    ↓
SHA-256 computed
    ↓
┌──────────────────────────────────┐
│  BOTH (default policy)           │
│                                  │
│  LocalRecoveryRepository         │
│    └── storeCustomerBaseline()   │ ← copies before /tmp deleted
│                                  │
│  ArvanBackupService::upload()    │ ← uploads to Arvan
└──────────────────────────────────┘
    ↓
If either succeeds → mark baseline_created = true
If BOTH fail       → warn only (non-fatal for the overall pipeline)
```

---

## Restore Integration

The Platform Installer restore wizard (Step 1: source-select) now lists **four** source types:

| Source | Origin | Listed When |
|---|---|---|
| `local` | `storage/backups/` | Files exist |
| `arvan` | ArvanCloud bucket | Files exist |
| `release` | `storage/releases/` | ZIP files exist |
| `recovery_repository` | Local Recovery Repo | `verified` logs with existing paths |

Only **verified** local repo baselines appear in the restore wizard.

### Priority at Restore

When `RECOVERY_PRIORITY=local_first`:
- The wizard offers Local Recovery Repository backups **before** Arvan backups in the UI ordering.

When `RECOVERY_PRIORITY=arvan_first`:
- Arvan backups appear first.

The priority does not force any automatic selection — the administrator always chooses.

---

## Admin UI

**URL:** `/admin/infrastructure/recovery-repository`

**Menu:** زیرساخت / DevOps → خزانه بازیابی محلی

| Section | Description |
|---|---|
| Status Cards | Overall health, disk usage, verified count, last sync |
| Configuration Form | Path, policy, priority, min free GB |
| Path Validation | AJAX real-time validation (exists, writable, free space) |
| Platform Backups | All platform baseline copies with verify/delete |
| Customer Backups | All customer baseline copies, filterable by site |

---

## Database

**Table:** `local_recovery_logs`

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `type` | enum(platform, customer) | Backup type |
| `site_id` | bigint nullable | Customer site (customer type only) |
| `installation_uuid` | varchar(36) | Installation identifier |
| `baseline_label` | varchar | Human-readable label |
| `local_path` | varchar | Full path to backup directory |
| `size_bytes` | bigint | Total size of all files |
| `sha256` | varchar(64) | SHA-256 of primary file |
| `manifest_checksum` | varchar(64) | SHA-256 of manifest (platform only) |
| `status` | enum(pending, verified, failed) | Verification result |
| `verified_at` | timestamp | When verified |
| `notes` | text | Failure details or success message |

---

## Security

- Path validation prevents directory traversal (must be absolute, outside `base_path()`)
- Local repo directories created with `0750` permissions
- Verification is streaming (hash_file — no large file in memory)
- `.env.backup` files in the local repo contain secrets — the repo path must be protected at the OS level (not web-accessible)
- The local repo path is validated for writability before every write operation
