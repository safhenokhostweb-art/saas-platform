# Platform Installer Security
> Phase: PLATFORM_INSTALLER_PHASE_1
> Date: 2026-06-26

---

## Security Model

The installer is a high-privilege component that can write .env, run migrations, and create admin users.
Every design decision is made to minimize attack surface.

---

## 1. install.lock — The Primary Guard

**Location:** `storage/install.lock`

**Rule:** If this file exists, the installer refuses to serve any page except:
- `GET /platform-installer/locked` — shows lock status
- `POST /platform-installer/unlock` — Super Admin only

**Enforcement:** `InstallerLockMiddleware` runs on every installer route.

**Creation:** Written atomically on `POST /platform-installer/fresh/finish` after all steps complete.

**Removal:** Only via:
1. Authenticated Super Admin (`is_admin = 1`)
2. Typing exact confirmation string `UNLOCK-INSTALLER`

**Danger:** If the lock file is manually deleted without authentication, the installer becomes accessible again. This is an intentional trade-off (VPS admin always has filesystem access).

---

## 2. Confirmation Strings

All destructive operations require typing a specific string:

| Operation | Required String |
|-----------|-----------------|
| Restore platform | `RESTORE-PLATFORM` |
| Upgrade platform | `UPGRADE-PLATFORM` |
| Unlock installer | `UNLOCK-INSTALLER` |

These are validated server-side with `in:` validation rules.

---

## 3. Environment Writer Safety

`EnvironmentWriter::write()` enforces:

1. **Path traversal guard:** `realpath()` check — must be within `base_path()`
2. **Atomic write:** writes to `.env.tmp.{pid}` then `rename()` — no partial-write corruption
3. **Input quoting:** Values containing spaces/special chars are double-quoted with `addslashes()`
4. **No shell injection:** No shell commands used; pure PHP file I/O

---

## 4. Database Validation

`InstallerValidator` validates DB credentials via regex before writing to .env:

```
db_host: /^[a-zA-Z0-9.\-_]+$/
db_name: /^[a-zA-Z0-9_\-]+$/
db_user: /^[a-zA-Z0-9_\-]+$/
```

---

## 5. Shell Command Safety (UpgradeAdapter / RestoreAdapter)

Where shell commands are needed (mysqldump, gunzip):
- All values passed through `escapeshellarg()`
- Never interpolate user input directly into shell strings
- Output captured with `exec()` (not `shell_exec`)
- Return codes checked — failure stops the process

---

## 6. CSRF Protection

All POST routes protected by Laravel's CSRF middleware (default `web` middleware group applies).
The installer routes use the `InstallerLockMiddleware` which runs inside the `web` group.

---

## 7. Admin-Only Unlock

The unlock endpoint does a hard gate check:

```php
if (!auth()->check() || !auth()->user()->is_admin) {
    abort(403);
}
```

No special middleware required — the controller validates directly.

---

## 8. No Public API Exposure

The installer has no JSON API except `GET /platform-installer/check` which only returns read-only check results (no writes).

---

## 9. Session Security

Installer state stored in PHP session (`platform_installer_state`).
Laravel's session is CSRF-protected by default.
State is cleared on `finish` step completion.

---

## 10. Upgrade Rollback Safety

Before any migration runs:
1. `UpgradeAdapter::createSnapshot()` saves DB dump + .env
2. If migration fails → `rollback()` automatically restores
3. Maintenance mode disabled on both success and failure

---

## Threat Model Summary

| Threat | Mitigation |
|--------|------------|
| Re-run installer after setup | install.lock blocks all pages |
| CSRF attacks | Laravel CSRF on all POSTs |
| Shell injection via DB credentials | escapeshellarg() + regex validation |
| Partial .env write corruption | Atomic rename from tmp file |
| Unauthorized unlock | is_admin gate + confirmation string |
| Brute-force unlock | Laravel rate limiting (default session) |
| Upgrade data loss | Pre-upgrade snapshot + auto rollback |
| Restore without confirmation | RESTORE-PLATFORM string required |

---

## Audit Verdict

**PASS** — All destructive operations gated, all inputs validated, no shell injection vectors, lock mechanism prevents re-installation.
