# UNIVERSAL DEPLOYMENT ARCHITECTURE

**Version:** 1.0  
**Date:** 2026-06-27  
**Status:** ARCHITECTURE_UPGRADE_001_COMPLETE  
**Pipeline Compatibility:** Hosting-Agnostic  

---

## Overview

The Universal Deployment Engine provides a single installation pipeline that works across every hosting type — VPS, shared hosting (cPanel/DirectAdmin), pure SFTP, Docker — without any conditional logic in the pipeline itself.

This was achieved through a **Driver-Based Deployment Architecture** where:

- The **pipeline** is hosting-agnostic. It queries `capabilities()` on the driver.
- The **driver** knows everything about the hosting type.
- A new hosting type requires only a new driver class. Zero pipeline changes.

---

## Core Design Principle

```
Pipeline never checks:
  if VPS ...
  if cPanel ...
  switch ($hostingType) ...

Pipeline ONLY asks:
  $driver->capabilities()->supportsQueue
  $driver->capabilities()->supportsSupervisor
  $driver->execute($command)
  $driver->upload($local, $remote)
```

All polymorphism, no conditionals.

---

## Architecture Layers

```
┌─────────────────────────────────────────────────┐
│           Installation Pipeline                 │
│  (hosting-agnostic, never checks driver type)   │
├─────────────────────────────────────────────────┤
│           DeploymentDriverInterface             │
│  capabilities() · validate() · rollbackStage()  │
│  connect() · execute() · upload() · download()  │
├──────────┬──────────┬────────────┬──────────────┤
│   SSH    │  cPanel  │DirectAdmin │   SFTP/Docker│
│ Driver   │  Driver  │   Driver   │   Drivers    │
│  (LIVE)  │  (STUB)  │  (STUB)    │   (STUBS)    │
└──────────┴──────────┴────────────┴──────────────┘
         ↑
   DeploymentTarget model
   (name, type, connection_driver, auth_method,
    host, port, username, path, domain, status,
    capabilities JSON)
```

---

## Key Files

| File | Role |
|------|------|
| `Contracts/DeploymentDriverInterface.php` | Universal driver contract |
| `Contracts/RemoteConnectionInterface.php` | Low-level connection contract (extended by driver interface) |
| `Drivers/SshDeploymentDriver.php` | **Full implementation** — VPS/dedicated servers |
| `Drivers/CpanelDeploymentDriver.php` | Architecture stub |
| `Drivers/DirectAdminDeploymentDriver.php` | Architecture stub |
| `Drivers/SftpDeploymentDriver.php` | Architecture stub |
| `Drivers/DockerDeploymentDriver.php` | Architecture stub |
| `Drivers/FakeRemoteDriver.php` | Full test double (implements DeploymentDriverInterface) |
| `Dto/DriverCapabilities.php` | Capability flags DTO |
| `Dto/ValidationResult.php` | Validation outcome DTO |
| `Enums/DriverType.php` | Canonical driver type identifiers |
| `Enums/AuthMethod.php` | Authentication method identifiers |
| `RemoteConnectionService.php` | Driver factory + test runner |
| `app/Models/DeploymentTarget.php` | Universal deployment target model |
| `database/migrations/2026_06_27_300000_create_deployment_targets_table.php` | Target schema |
| `config/customer_installation.php` | Driver registry |

---

## DeploymentTarget Model

Replaces the legacy "Server" concept with a universal "Deployment Target":

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Human label |
| `type` | enum | vps, shared_hosting, cpanel, directadmin, docker, managed_cloud |
| `connection_driver` | enum | ssh, cpanel, directadmin, sftp, docker, fake |
| `auth_method` | enum | ssh_key, ssh_password, cpanel_api_token, etc. |
| `host` | string | Hostname or IP |
| `port` | int | Default: 22 |
| `username` | string | Login username |
| `path` | string | Web root path on remote |
| `domain` | string | Primary domain |
| `status` | enum | active, inactive, pending, error |
| `capabilities` | JSON | Override; null = auto from driver type |
| `private_key_encrypted` | text | AES-256-GCM (CredentialVault) |
| `password_encrypted` | string | AES-256-GCM |
| `api_token_encrypted` | text | AES-256-GCM |
| `key_fingerprint` | string | SSH host key SHA-256 (TOFU pinning) |

---

## Stage-Capability Integration

Pipeline stages must check capabilities before executing driver-specific commands:

```php
// ✅ CORRECT — capability-aware stage
public function execute(InstallationContext $ctx): StageResult
{
    if (!$ctx->driver->capabilities()->supportsSupervisor) {
        return StageResult::skipped($this->name(), 'supervisor not supported on this target');
    }
    $ctx->driver->execute('supervisorctl reread && supervisorctl update');
    // ...
}

// ❌ WRONG — driver-type conditional
if ($ctx->driver->driverType() === DriverType::SSH) {
    $ctx->driver->execute('supervisorctl reread');
}
```

---

## Driver Factory

```php
// New API — DeploymentTarget
$driver = app(RemoteConnectionService::class)->buildDriverForTarget($target);

// Legacy API — CustomerRemoteServer (backward compatible)
$driver = app(RemoteConnectionService::class)->buildDriver($server);
```

---

## Backward Compatibility

| Component | Status |
|-----------|--------|
| `CustomerRemoteServer` model | UNCHANGED |
| `SshRemoteDriver` | UNCHANGED |
| `RemoteConnectionService::buildDriver()` | UNCHANGED |
| Existing 20-stage pipeline | UNCHANGED |
| All 31+ existing tests | UNAFFECTED |

The new architecture is **additive** — nothing was removed or modified.

---

## Adding a New Driver

1. Create `app/Services/CustomerInstallation/Drivers/{Name}DeploymentDriver.php`
2. Implement `DeploymentDriverInterface`
3. Return correct `DriverCapabilities` from `capabilities()`
4. Register in `config/customer_installation.php` under `deployment_drivers`
5. Add `DriverType` enum case and `AuthMethod` enum cases if needed

**Zero pipeline changes required.**
