# INSTALLER BASELINE MANIFEST SPECIFICATION

> Status: PLATFORM_INSTALLER_FOUNDATION_FINALIZED  
> Version: 2.0  
> Last Updated: 2026-06-26

---

## Overview

Every Installation Baseline contains a `manifest.json` file that is the authoritative integrity record for that baseline. The manifest records SHA-256 checksums for all baseline files and includes platform identity metadata, making it possible to verify that no file has been altered since the baseline was created.

---

## Manifest Structure

```json
{
  "manifest_version": "1.0",
  "installation_uuid": "uuid-v4",
  "platform_version": "1.0.0",
  "laravel_version": "10.x",
  "php_version": "8.4.x",
  "installer_version": "2.0",
  "git_commit": "abc1234",
  "created_at_jalali": "1403/04/05 14:22",
  "created_at_utc": "2026-06-26T14:22:00.000000Z",
  "generated_by": "Platform Installer v2.0 / BaselineManifestService",
  "baseline_label": "INITIAL_INSTALLATION_BASELINE",
  "checksums": {
    "database.sql.gz":       "sha256-hex-64-chars",
    ".env.backup":            "sha256-hex-64-chars",
    "baseline_meta.json":    "sha256-hex-64-chars",
    "INSTALLATION_REPORT_*.html": "sha256-hex-64-chars",
    "INSTALLATION_REPORT_*.pdf":  "sha256-hex-64-chars"
  },
  "manifest_checksum": "sha256-of-manifest-body-without-this-field"
}
```

---

## Field Descriptions

| Field | Description |
|-------|-------------|
| `manifest_version` | Version of the manifest schema (currently `1.0`) |
| `installation_uuid` | UUID v4 matching the `installation_id` in the Installation Report |
| `platform_version` | Platform version from `config('app.version')` or `package.json` |
| `laravel_version` | Laravel framework version |
| `php_version` | PHP runtime version |
| `installer_version` | Installer service version (currently `2.0`) |
| `git_commit` | Short git commit hash at time of installation (`N/A` if not a git repo) |
| `created_at_jalali` | Baseline creation time in Jalali calendar (`Y/m/d H:i`) |
| `created_at_utc` | Baseline creation time in ISO 8601 UTC |
| `generated_by` | Identifier of the service that generated the manifest |
| `baseline_label` | Human-readable label matching the directory name |
| `checksums` | SHA-256 hash for every file in the baseline directory |
| `manifest_checksum` | SHA-256 of the manifest body (without this field) — self-covering |

---

## Self-Covering Checksum

The `manifest_checksum` covers the entire manifest body **excluding the `manifest_checksum` field itself**. This means:

1. Compute the manifest JSON without `manifest_checksum`
2. `sha256(json_encode($manifestBody))` → `manifest_checksum`
3. Write the full manifest including `manifest_checksum`

**Verification** recomputes the same way:
1. Read manifest → remove `manifest_checksum`
2. Recompute SHA-256
3. Compare with stored value → must match exactly

If they don't match, the manifest was tampered with.

---

## File Checksums (Streaming)

All file checksums are computed using PHP's `hash_file('sha256', $path)`, which reads files in chunks and never loads the entire file into memory. This safely handles large database dumps.

**Checksum algorithm:** SHA-256 (hex, 64 characters)

**Files checksummed in Phase 1 (baseline core):**
- `database.sql.gz` — mysqldump compressed with gzip
- `.env.backup` — copy of `.env` at time of installation
- `baseline_meta.json` — baseline identity metadata

**Files added in Phase 2 (finalization):**
- `INSTALLATION_REPORT_YYYYMMDD_HHMMSS.html` — full HTML report
- `INSTALLATION_REPORT_YYYYMMDD_YYYYMMDD_HHMMSS.pdf` — PDF report

The manifest is **recomputed** after Phase 2 to include HTML/PDF checksums.

---

## Baseline Naming Convention

| Scenario | Directory Name |
|----------|---------------|
| First installation ever | `INITIAL_INSTALLATION_BASELINE` |
| Subsequent installations | `INSTALLATION_BASELINE_YYYYMMDD_HHMMSS` |
| Timestamp collision (rare) | `INSTALLATION_BASELINE_YYYYMMDD_HHMMSS_2` |

The `INITIAL_INSTALLATION_BASELINE` directory is **never overwritten**. Once it exists, all subsequent baselines use timestamped names.

---

## Required Files

The following files must be present for a baseline to be considered **COMPLETE**:

1. `database.sql.gz` — Database snapshot
2. `.env.backup` — Environment configuration backup
3. `baseline_meta.json` — Baseline identity metadata
4. `manifest.json` — Integrity manifest (this document's subject)

HTML/PDF reports are optional but strongly recommended.

---

## Two-Phase Creation Protocol

```
Phase 1: createInstallationBaseline($installationUuid)
    → Creates baseline directory
    → mysqldump → database.sql.gz
    → copy .env → .env.backup
    → write baseline_meta.json
    → createManifest() → manifest.json (checksums for 3 files)
    → Returns: baseline_path, manifest_checksum

Phase 2: enrichWithBaseline($reportData, $baselineResult)
    → Runs manifest verification
    → Computes overall_status (PASS / WARNING / FAILED)
    → Sets installation_certification text
    → Generates HTML/PDF reports (with baseline info embedded)

Phase 3: addReportsToBaseline($baselineDir, $htmlPath, $pdfPath)
    → Copies HTML/PDF into baseline directory
    → updateManifest() → recomputes manifest with 5 files
    → makeReadOnly() → locks directory (0555 / 0444)
```

---

## Storage Location

```
storage/upgrade_snapshots/
├── INITIAL_INSTALLATION_BASELINE/
│   ├── database.sql.gz
│   ├── .env.backup
│   ├── baseline_meta.json
│   ├── INSTALLATION_REPORT_20260626_142200.html
│   ├── INSTALLATION_REPORT_20260626_142200.pdf
│   └── manifest.json              ← authoritative integrity record
└── INSTALLATION_BASELINE_20260927_103000/
    ├── ...
    └── manifest.json
```

---

## Service Reference

**`BaselineManifestService`** at `app/Services/Installer/BaselineManifestService.php`

| Method | Description |
|--------|-------------|
| `resolveBaselineName($basePath)` | Returns next available baseline directory path |
| `createManifest($baselineDir, $meta)` | Computes checksums + writes manifest.json |
| `updateManifest($baselineDir)` | Recomputes manifest preserving original UUID/timestamps |
| `verify($baselineDir)` | Full integrity check — returns PASS/FAILED + detail |
| `makeReadOnly($baselineDir)` | Sets 0444/0555 permissions |
| `listBaselines($basePath)` | Returns all baseline directories with metadata |
| `getManifestChecksum($baselineDir)` | Reads manifest_checksum from existing manifest |
