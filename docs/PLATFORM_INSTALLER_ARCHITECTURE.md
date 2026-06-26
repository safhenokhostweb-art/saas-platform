# Platform Installer Architecture
> Phase: PLATFORM_INSTALLER_PHASE_1
> Date: 2026-06-26
> Status: SCAFFOLDING_COMPLETE

---

## Overview

The Platform Installer is the permanent installation engine for the SaaS Platform.
It supports three modes and reuses all existing infrastructure services.

---

## Modes

| Mode | Route | Description |
|------|-------|-------------|
| MODE 1 — Fresh Install | `/platform-installer/` → select `fresh` | New VPS from scratch |
| MODE 2 — Restore | `/platform-installer/` → select `restore` | Restore from backup |
| MODE 3 — Upgrade | `/platform-installer/` → select `upgrade` | Upgrade running platform |

---

## Service Layer

```
app/Services/Installer/
├── InstallerService.php        — Orchestrator: state, lock, step navigation
├── InstallerValidator.php      — Pre-flight checks: PHP, extensions, DB, Redis, etc.
├── EnvironmentWriter.php       — Atomic .env writer
├── InfrastructureInstaller.php — Migrations, seeding, cache, Supervisor/Cron config
├── RestoreAdapter.php          — Wraps RestoreService for installer context
├── UpgradeAdapter.php          — Snapshot → migrate → validate → rollback
└── HealthCheckRunner.php       — Wraps DrReadinessScoreService + custom installer checks
```

---

## Reused Services (No duplication)

| Installer Service | Reuses |
|-------------------|--------|
| `RestoreAdapter` | `RestoreService`, `ArvanBackupService` |
| `UpgradeAdapter` | `InfrastructureInstaller`, `HealthCheckRunner`, `SlackAlertService` |
| `HealthCheckRunner` | `DrReadinessScoreService`, `ArvanBackupService` |

---

## Controller

```
app/Http/Controllers/Installer/PlatformInstallerController.php
```

- `index()` — Mode selection page
- `selectMode()` — POST: init state, redirect to first step
- `step($mode, $step)` — GET: render step view
- `processStep($mode, $step)` — POST: process step, advance
- `locked()` — Show install.lock page
- `unlock()` — Super Admin only: remove install.lock
- `complete()` — Post-install completion page
- `apiCheck()` — AJAX: re-run a single env check

---

## Middleware

```
app/Http/Middleware/InstallerLockMiddleware.php
```

Redirects to `installer.locked` if `storage/install.lock` exists.
Exceptions: `installer.locked` and `installer.unlock`.

---

## Routes

```
GET  /platform-installer/              → installer.index
POST /platform-installer/select-mode  → installer.select-mode
GET  /platform-installer/complete      → installer.complete
GET  /platform-installer/locked        → installer.locked
POST /platform-installer/unlock        → installer.unlock
GET  /platform-installer/{mode}/{step} → installer.step
POST /platform-installer/{mode}/{step} → installer.process
```

---

## Fresh Install — 7 Steps

| # | Step | File | Description |
|---|------|------|-------------|
| 1 | `env_check` | `InstallerValidator::runAll()` | PHP version, extensions, disk, Redis, MySQL, SSL, mail |
| 2 | `database` | `EnvironmentWriter::writeDatabaseConfig()` | Write DB config to .env |
| 3 | `environment` | `EnvironmentWriter::write()` | APP_KEY, APP_URL, timezone, queue, cache |
| 4 | `platform` | `InfrastructureInstaller` | migrate + super admin + platform settings |
| 5 | `infrastructure` | Config generation | Supervisor, Cron, Reverb (display only) |
| 6 | `validation` | `HealthCheckRunner::runFull()` | DR score + backup + mail + Arvan |
| 7 | `finish` | `InstallerService::lock()` | Write install.lock, clear state |

---

## Restore — 4 Steps

| # | Step | Description |
|---|------|-------------|
| 1 | `source_select` | Choose: local disk / Arvan / release package |
| 2 | `verify` | Checksum + size verification |
| 3 | `restore` | Confirm with `RESTORE-PLATFORM`, create rollback point, dispatch |
| 4 | `finish` | Show completion, link to DR center |

---

## Upgrade — 4 Steps

| # | Step | Description |
|---|------|-------------|
| 1 | `snapshot` | `UpgradeAdapter::createSnapshot()` — DB + .env backup |
| 2 | `upgrade` | Confirm with `UPGRADE-PLATFORM` → maintenance → migrate → optimize |
| 3 | `validate` | `HealthCheckRunner::runFull()` |
| 4 | `finish` | Success or rollback report |

---

## Views

```
resources/views/installer/
├── layout.blade.php                — Standalone RTL dark layout (Vazirmatn)
├── mode-select.blade.php           — 3-mode selection cards
├── locked.blade.php                — install.lock active
├── complete.blade.php              — Post-install success
├── partials/
│   └── progress.blade.php          — Step progress bar
├── fresh/
│   ├── env-check.blade.php
│   ├── database.blade.php
│   ├── environment.blade.php
│   ├── platform.blade.php
│   ├── infrastructure.blade.php
│   ├── validation.blade.php
│   └── finish.blade.php
├── restore/
│   ├── source-select.blade.php
│   ├── verify.blade.php
│   ├── restore.blade.php
│   └── finish.blade.php
└── upgrade/
    ├── snapshot.blade.php
    ├── upgrade.blade.php
    ├── validate.blade.php
    └── finish.blade.php
```

---

## State Management

State is stored in PHP session under key `platform_installer_state`:

```json
{
  "mode": "fresh",
  "current_step": "database",
  "completed_steps": ["env_check"],
  "data": {
    "env_check": {},
    "database": { "db_host": "127.0.0.1", "db_name": "saas" }
  },
  "started_at": "2026-06-26T10:00:00Z"
}
```

---

## Lock File

Location: `storage/install.lock`

```json
{
  "locked_at": "2026-06-26T10:30:00Z",
  "mode": "fresh",
  "completed_at": "2026-06-26T10:30:00Z",
  "version": "1.0.0"
}
```

- Written on `finish` step completion
- Read by `InstallerLockMiddleware` on every installer route
- Can only be removed by authenticated Super Admin via `POST /platform-installer/unlock`

---

## Admin Menu

- Key: `platform_installer`
- Group: `زیرساخت / DevOps`
- Visible to: Super Admin only (WIZARD_MENU_KEYS)
- Icon: Server/grid SVG
