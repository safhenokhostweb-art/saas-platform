# INSTALLER BASELINE SECURITY SPECIFICATION

> Status: PLATFORM_INSTALLER_FOUNDATION_FINALIZED  
> Version: 2.0  
> Last Updated: 2026-06-26

---

## Security Principles

The Installation Baseline system is designed with the following security principles:

1. **Manifest cannot be edited** — manifest.json is made read-only immediately after finalization
2. **Baseline cannot be modified** — all baseline files become 0444 (read-only) after Phase 3
3. **Verification is read-only** — integrity checks only read files, never modify them
4. **Secrets never exposed** — `.env.backup` is stored but never transmitted, displayed, or included in reports
5. **No passwords in manifests** — `manifest.json` contains only checksums and metadata
6. **No path traversal** — all download/verify endpoints validate paths against baselines root

---

## Secret Isolation

The `.env.backup` file contains database credentials, API keys, and application secrets. The following measures protect it:

### What is checksummed but NOT exposed:
- `APP_KEY` — never shown
- `DB_PASSWORD` — never shown
- `ARVAN_ACCESS_KEY` — never shown
- All `_SECRET`, `_KEY`, `_TOKEN`, `_PASSWORD` values

### What IS stored in `manifest.json` (safe metadata only):
- SHA-256 checksum of `.env.backup` (allows integrity verification without exposing content)
- `installation_uuid`, timestamps, platform version, git commit

### What IS in `baseline_meta.json` (safe metadata only):
- baseline label, version, mode, platform name, component list
- No credentials, no secrets, no API keys

### Download endpoints:
- `GET /admin/installer/manifest` → `manifest.json` (safe — checksums only)
- `GET /admin/installer/baseline-meta` → `baseline_meta.json` (safe — metadata only)
- `.env.backup` is **never downloadable** through any endpoint
- `database.sql.gz` is **never downloadable** through any endpoint (contains all user data)

---

## Path Traversal Prevention

The admin controller's `verify()`, `downloadManifest()`, and `downloadBaselineMeta()` methods all enforce the following guard:

```php
$root = realpath($this->upgrade->getBaselinesRootPath()); // e.g. /var/www/storage/upgrade_snapshots
$real = realpath($userInputPath);

if (!$real || !$root || !str_starts_with($real, $root)) {
    abort(403, 'Invalid baseline path');
}
```

This ensures:
- `../../../etc/passwd` → rejected (resolves outside root)
- `/tmp/evil` → rejected (resolves outside root)
- Null/empty inputs → rejected
- Symlinks resolved before comparison

---

## Access Control

The installer admin panel is protected by:

1. **`auth` middleware** — user must be logged in
2. **`admin` middleware** — user must have `is_admin = 1`
3. **Read-only contract** — no write, delete, or overwrite operations in `InstallerAdminController`

The installer wizard routes use a separate `InstallerLockMiddleware` that blocks access when `install.lock` exists.

---

## File Permissions (Linux/Production)

After baseline finalization:

| File | Permissions | Meaning |
|------|-------------|---------|
| `database.sql.gz` | `0444` | Read-only for owner, group, others |
| `.env.backup` | `0444` | Read-only for owner, group, others |
| `baseline_meta.json` | `0444` | Read-only |
| `manifest.json` | `0444` | Read-only |
| `*.html`, `*.pdf` | `0444` | Read-only |
| Baseline directory | `0555` | Read + execute (traverse) only, no write |

**Note:** On Windows (development), `chmod()` is a no-op for directory write protection. The protection is enforced at the PHP layer by never calling write operations on an existing baseline directory.

**Important:** Making a directory `0555` means `php artisan storage:link` or other commands that need to write inside it will fail. The baselines directory is under `storage/upgrade_snapshots/` which is not inside the webroot and is not affected by normal application operations.

---

## Overwrite Prevention

`BaselineManifestService::resolveBaselineName()` implements the immutability guarantee:

```php
public function resolveBaselineName(string $basePath): string
{
    $initialPath = $basePath . '/INITIAL_INSTALLATION_BASELINE';

    // If INITIAL does not exist → create it (first and only time)
    if (!is_dir($initialPath)) {
        return $initialPath;
    }

    // INITIAL already exists → use timestamped name (never INITIAL again)
    $timestamp = now()->format('Ymd_His');
    $candidate = $basePath . '/INSTALLATION_BASELINE_' . $timestamp;
    // ... counter suffix for collision
    return $candidate;
}
```

And `createInstallationBaseline()` calls `resolveBaselineName()` every time — the INITIAL directory is **never passed to mkdir if it already exists**.

---

## Verification Security

The `BaselineManifestService::verify()` method:
- Opens files with `hash_file('sha256', $path)` — streaming reads only
- Never loads entire database dump into memory
- Compares computed hashes against manifest — any mismatch = FAILED
- Reports exact failure reason (which file, stored vs actual hash)
- Never writes to or modifies the baseline directory

The admin `POST /admin/installer/verify` endpoint:
- Requires CSRF token
- Requires `auth` + `admin` middleware
- Path validated against baselines root before any file access
- Returns JSON only — no file modification

---

## Installation Report Security

The Installation Report (HTML/PDF) intentionally omits:

- `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`, `MAIL_PASSWORD`
- Any value containing `_SECRET`, `_TOKEN`, `_KEY`, `_PASSWORD`
- `.env` content of any kind
- Session tokens, API credentials

What IS included (safe for audit):
- Software versions (PHP, Laravel, MySQL, Node.js)
- Infrastructure status (Redis: connected/refused — no credentials)
- Domain, server IP, app URL
- Administrator name and mobile (no password hash)
- Module list (name, version, status)
- Health scores and DR readiness

---

## Audit Trail

Every baseline creation triggers:
- `SlackAlertService::info()` with baseline label (no secrets)
- `install.lock` updated with `baseline_path`, `manifest_checksum`, `overall_status`, `certification`

Every verification run is:
- Initiated by an authenticated admin
- Returns structured JSON (not persisted — stateless)
- Full detail available in the response for manual audit

---

## Known Limitations

| Limitation | Impact | Mitigation |
|-----------|--------|------------|
| Windows chmod is no-op | Files writable on dev | Enforced in PHP logic; production is Linux |
| No digital signature (private key) | Cannot cryptographically prove authenticity | SHA-256 + manifest_checksum provides integrity; add GPG signing in future |
| `.env.backup` not encrypted | Readable by server root | Restrict OS-level access to `storage/upgrade_snapshots/` |
| Single-machine baseline | No offsite copy | Supplement with ArvanCloud backup pipeline |
