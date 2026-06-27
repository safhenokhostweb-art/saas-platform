# DEPLOYMENT DRIVER INTERFACE

**Version:** 1.0  
**Date:** 2026-06-27  

---

## Interface Definition

```php
interface DeploymentDriverInterface extends RemoteConnectionInterface
{
    public function capabilities(): DriverCapabilities;
    public function validate(): ValidationResult;
    public function rollbackStage(string $stageName, array $snapshot): void;
    public function driverType(): DriverType;
}
```

Extends `RemoteConnectionInterface`:

```php
interface RemoteConnectionInterface
{
    public function connect(): void;
    public function testConnection(): ConnectionTestResult;
    public function execute(string $command, array $env = []): CommandResult;
    public function upload(string $localPath, string $remotePath, ?callable $progress = null): void;
    public function download(string $remotePath, string $localPath): void;
    public function fileExists(string $remotePath): bool;
    public function diskFree(string $path): int;
    public function disconnect(): void;
}
```

---

## Method Contracts

### `capabilities(): DriverCapabilities`

Returns a `DriverCapabilities` DTO declaring what this hosting type supports.
The pipeline **only** reads capabilities — it never reads `driverType()` to make decisions.

```php
public function capabilities(): DriverCapabilities
{
    return DriverCapabilities::forVps();
    // or: forCpanel(), forDirectAdmin(), forSftp(), forDocker()
    // or: new DriverCapabilities(supportsQueue: true, supportsCron: true, ...)
}
```

### `validate(): ValidationResult`

Pre-flight validation **without** opening a connection.
Called by `RemoteConnectionService::buildDriverForTarget()` before returning the driver.

```php
public function validate(): ValidationResult
{
    $errors = [];
    if (empty($this->target->host)) $errors[] = 'Host is required';
    // ...
    return empty($errors) ? ValidationResult::pass() : ValidationResult::fail($errors);
}
```

### `rollbackStage(string $stageName, array $snapshot): void`

Driver-level cleanup hook. Stage-specific rollback logic lives in each `Stage::rollback()` method.
This method is for driver-level session cleanup (close connections, release locks, etc.).

### `driverType(): DriverType`

Returns the canonical `DriverType` enum case. Used for logging and metrics only.
Pipeline stages must **not** switch on this value.

---

## DriverCapabilities Flags

| Flag | SSH | cPanel | DirectAdmin | SFTP | Docker |
|------|-----|--------|-------------|------|--------|
| `supportsQueue` | ✅ | ❌ | ❌ | ❌ | ✅ |
| `supportsScheduler` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `supportsCron` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `supportsSupervisor` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `supportsDatabaseCreation` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `supportsSSL` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `supportsStorageLink` | ✅ | ❌ | ❌ | ❌ | ✅ |
| `supportsRedis` | ✅ | ❌ | ❌ | ❌ | ✅ |
| `supportsDocker` | ✅ | ❌ | ❌ | ❌ | ✅ |
| `supportsReboot` | ✅ | ❌ | ❌ | ❌ | ✅ |

---

## Authentication Per Driver

| Driver | Auth Methods |
|--------|-------------|
| SSH | `ssh_key` (recommended), `ssh_password` |
| cPanel | `cpanel_api_token` (recommended), `cpanel_password` |
| DirectAdmin | `directadmin_api_token` (recommended), `directadmin_password` |
| SFTP | `sftp_password` |
| Docker | `ssh_key`, `ssh_password` (SSH to Docker host) |

---

## Driver Responsibilities

Each driver is responsible for:

| Responsibility | Examples |
|----------------|---------|
| Connection management | connect(), disconnect(), reconnect on drop |
| Command execution | execute("php artisan migrate") |
| File upload | upload($localZip, $remotePath) |
| File download | download($remoteBackup, $localPath) |
| Filesystem queries | fileExists(), diskFree() |
| Health check | testConnection() |
| Rollback hook | rollbackStage() |

Each driver is **NOT** responsible for:

- Installation stage logic
- Progress tracking
- Audit logging
- Pipeline state management
- Job dispatching

---

## Stage-Capability Pattern

Every stage that uses a hosting-specific feature MUST guard with a capability check:

```php
// ConfigureQueueStage.php
public function execute(InstallationContext $ctx): StageResult
{
    if (!$ctx->driver->capabilities()->supportsQueue) {
        return StageResult::skipped(
            $this->name(),
            'Queue not supported on this hosting type. Manual queue setup required.'
        );
    }

    // ... supervisor or horizon configuration
}

// ConfigureSchedulerStage.php
public function execute(InstallationContext $ctx): StageResult
{
    if ($ctx->driver->capabilities()->supportsSupervisor) {
        $ctx->driver->execute('supervisorctl reread && supervisorctl update');
    } elseif ($ctx->driver->capabilities()->supportsCron) {
        // add crontab entry
        $ctx->driver->execute('(crontab -l; echo "* * * * * php ' . $path . ' schedule:run") | crontab -');
    } else {
        return StageResult::skipped($this->name(), 'No scheduler mechanism available');
    }
}
```

---

## Implementation Checklist

When implementing a new driver:

- [ ] Implement `DeploymentDriverInterface`
- [ ] Return accurate `DriverCapabilities` from `capabilities()`
- [ ] Implement `validate()` with all required field checks
- [ ] Implement `connect()` — throw `RuntimeException` on failure
- [ ] Implement `execute()` — return `CommandResult` always
- [ ] Implement `upload()` with optional progress callback
- [ ] Implement `download()`
- [ ] Implement `fileExists()`
- [ ] Implement `diskFree()`
- [ ] Implement `disconnect()` — must be idempotent
- [ ] Implement `rollbackStage()` for any driver-level cleanup
- [ ] Register in `config/customer_installation.php` under `deployment_drivers`
- [ ] Add `DriverType` enum case
- [ ] Add `AuthMethod` enum cases
- [ ] Write unit tests using `FakeRemoteDriver` as reference
