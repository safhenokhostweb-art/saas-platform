# Platform Installer Flow
> Phase: PLATFORM_INSTALLER_PHASE_1
> Date: 2026-06-26

---

## Entry Point

```
GET /platform-installer/
```

→ `InstallerLockMiddleware` checks `storage/install.lock`
  - If locked → redirect to `/platform-installer/locked`
  - If not locked → show mode selection

---

## MODE 1 — Fresh Installation Flow

```
[Mode Select] → fresh
      │
      ▼
STEP 1: env_check
  InstallerValidator::runAll()
  ├─ PHP version ≥ 8.1
  ├─ Required extensions (pdo, mbstring, openssl, curl, zip, gd, xml, intl, bcmath, fileinfo)
  ├─ Disk space ≥ 2GB
  ├─ Redis::ping()
  ├─ DB::select('SELECT 1')
  ├─ Queue driver check
  ├─ Storage writable
  ├─ SSL/HTTPS check
  ├─ Mail config check
  └─ Scheduler heuristic
  [CRITICAL FAIL] → cannot proceed
  [WARN/PASS] → continue
      │
      ▼
STEP 2: database
  EnvironmentWriter::writeDatabaseConfig()
  ├─ Validate: host, port, name, user (regex)
  └─ Write .env atomically
      │
      ▼
STEP 3: environment
  EnvironmentWriter::write()
  ├─ APP_NAME, APP_URL, APP_TIMEZONE
  ├─ QUEUE_CONNECTION, CACHE_DRIVER
  └─ APP_KEY (auto-generated if empty)
      │
      ▼
STEP 4: platform
  InfrastructureInstaller::runMigrations()
  InfrastructureInstaller::createSuperAdmin()
  InfrastructureInstaller::createPlatformSettings()
      │
      ▼
STEP 5: infrastructure
  InfrastructureInstaller::generateSupervisorConfig()   → display
  InfrastructureInstaller::generateReverbConfig()       → display
  InfrastructureInstaller::generateCronLine()           → display
  [No actual server config written — manual step]
      │
      ▼
STEP 6: validation
  HealthCheckRunner::runFull()
  ├─ DrReadinessScoreService::calculate() → DR score 0-100
  ├─ checkBackupDir()
  ├─ checkMailConfig()
  └─ checkArvanConnect()
      │
      ▼
STEP 7: finish
  InstallerService::lock({ mode: fresh, ... })
  InstallerService::clearState()
  → Redirect to /platform-installer/complete
```

---

## MODE 2 — Restore Flow

```
[Mode Select] → restore
      │
      ▼
STEP 1: source_select
  RestoreAdapter::listAllSources()
  ├─ local:   storage/backups/*.sql.gz
  ├─ arvan:   RestoreService::listBackups('platform_database')
  └─ release: storage/releases/*.zip
  User picks: source + file_path
      │
      ▼
STEP 2: verify
  RestoreAdapter::verifyBackup(source, path)
  ├─ local/release: file_exists + filesize > 100 + md5_file
  └─ arvan: ArvanBackupService::exists()
      │
      ▼
STEP 3: restore
  [User types: RESTORE-PLATFORM]
  RestoreAdapter::createRollbackPoint('pre_restore')
  RestoreService::acquireLock(token)   ← Redis NX lock
  → dispatch to background (RestoreService)
      │
      ▼
STEP 4: finish
  Show completion, link to DR Center logs
```

---

## MODE 3 — Upgrade Flow

```
[Mode Select] → upgrade
      │
      ▼
STEP 1: snapshot
  UpgradeAdapter::createSnapshot()
  ├─ mysqldump + gzip → storage/upgrade_snapshots/{timestamp}/database.sql.gz
  └─ copy .env → storage/upgrade_snapshots/{timestamp}/.env.backup
  [FAIL] → abort upgrade (cannot safely proceed without rollback point)
      │
      ▼
STEP 2: upgrade
  [User types: UPGRADE-PLATFORM]
  UpgradeAdapter::runUpgrade(snapshotPath)
  ├─ Artisan::call('down')              ← maintenance on
  ├─ runMigrations(force: true)
  │   └─ [FAIL] → disableMaintenanceMode + return needs_rollback
  ├─ clearCacheAndOptimize()
  ├─ HealthCheckRunner::runQuick()     ← DB + Redis ping
  └─ Artisan::call('up')              ← maintenance off
  [needs_rollback] → UpgradeAdapter::rollback(snapshotPath) automatically
      │
      ▼
STEP 3: validate
  HealthCheckRunner::runFull()
  [Shows DR score + custom checks]
      │
      ▼
STEP 4: finish
  Show success or rollback report
```

---

## Lock / Unlock Flow

```
Installer complete → install.lock written
                         │
Request to /platform-installer/*
                         │
                InstallerLockMiddleware
                         │
              ┌──────────┴──────────┐
          locked?                not locked
              │                      │
      → /platform-installer/locked   → normal flow
              │
    Is it /locked or /unlock?
              │
         Yes → serve page
              │
         No  → show locked page

/platform-installer/unlock (POST):
  auth()->check() && is_admin → proceed
  confirm === 'UNLOCK-INSTALLER' → unlink install.lock → redirect to index
```

---

## Error Handling

| Error Type | Behavior |
|-----------|----------|
| Critical env check fail | Stay on env_check, show fail, button disabled |
| DB write fail | Back with error message |
| Migration fail during platform step | Back with error, .env already written |
| Snapshot fail during upgrade | Abort — do not upgrade without snapshot |
| Migration fail during upgrade | Auto rollback + show error |
| Restore lock busy | Back with "another restore in progress" message |
| Arvan unavailable on verify | Allow continue with warning |
