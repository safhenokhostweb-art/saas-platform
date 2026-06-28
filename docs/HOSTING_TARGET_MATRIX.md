# Hosting Target Capability Matrix

**Phase:** PHASE-003D.5  
**Date:** 2026-06-28

---

## Summary

This matrix documents which deployment capabilities are available per hosting type,
and the current implementation status of each driver.

---

## Capability Matrix

| Capability | SSH/VPS | DirectAdmin | cPanel | SFTP | Docker |
|-----------|---------|-------------|--------|------|--------|
| **Queue Workers** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **Supervisor** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Redis** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **SSL / HTTPS** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Database Creation** | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Storage Link** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **Cron Scheduler** | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Docker** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **Reboot** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **Driver Status** | ✅ Active | 🔜 Stub | 🔜 Stub | 🔜 Stub | 🔜 Stub |

---

## Driver Implementation Status

| Driver | Class | Status | Notes |
|--------|-------|--------|-------|
| SSH | `SshDeploymentDriver` | **Active** | Full phpseclib3 SSH2/SFTP, key pinning, chunked upload |
| DirectAdmin | `DirectAdminDeploymentDriver` | **Stub** | Architecture only — all methods throw BadMethodCallException |
| cPanel | `CpanelDeploymentDriver` | **Stub** | Architecture only |
| SFTP | `SftpDeploymentDriver` | **Stub** | execute() intentionally throws (no shell access) |
| Docker | `DockerDeploymentDriver` | **Stub** | Architecture only |
| Fake | `FakeRemoteDriver` | **Active** | Used in testing only |

---

## Hosting Type Use Cases

### SSH / VPS / Dedicated Server
**Best for:** Laravel deployments requiring full stack (queue, Redis, Supervisor).  
**Limitations:** Requires SSH access with sufficient permissions.  
**Auth:** RSA/ED25519 private key (recommended) or password.

### DirectAdmin
**Best for:** Shared hosting environments with DirectAdmin panel.  
**Limitations:** No queue workers, Redis, or Supervisor. Cron via DA's cron manager.  
**Auth:** API Key in `login:token` format via DirectAdmin API.

### cPanel
**Best for:** Shared hosting environments with cPanel/WHM.  
**Limitations:** Same as DirectAdmin. Laravel compatibility depends on PHP version available.  
**Auth:** cPanel API Token via UAPI.

### SFTP (Pure)
**Best for:** Static sites or environments where only file deployment is needed.  
**Limitations:** No server-side execution — no migrations, no artisan, no composer.  
**Auth:** Username + password only.

### Docker Host
**Best for:** Containerized deployments. Docker managed per tenant.  
**Limitations:** Requires Docker daemon access. No Supervisor (systemd inside container).  
**Auth:** SSH to the Docker host (key or password).

---

## Pipeline Stage Compatibility

Per ADR-014: pipeline stages NEVER check driver type — only `capabilities()`.

| Pipeline Stage | Requires Capability |
|----------------|-------------------|
| QueueSetup | `supportsQueue` |
| SupervisorSetup | `supportsSupervisor` |
| RedisSetup | `supportsRedis` |
| SslSetup | `supportsSSL` |
| DatabaseCreate | `supportsDatabaseCreation` |
| StorageLink | `supportsStorageLink` |
| CronSetup | `supportsCron` |
| DockerDeploy | `supportsDocker` |
| ServerReboot | `supportsReboot` |

Stages whose required capability returns `false` skip silently via
`if (!$driver->capabilities()->supportsXxx) { return; }`.

---

## Future Drivers (Roadmap)

| Driver | Priority | Notes |
|--------|----------|-------|
| Kubernetes | Low | Namespace-per-tenant isolation model needed |
| Plesk | Medium | Similar to cPanel/DA — API token auth |
| RunCloud | Medium | Cloud server management panel |
| Forge | Low | Laravel Forge API integration |
