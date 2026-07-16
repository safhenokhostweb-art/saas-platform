# HOSTING SUPPORT MATRIX

**Version:** 2.0
**Date:** 2026-07-16 (Phase 15-E)

Legend: ✅ Supported | ❌ Not supported | ⚠️ Implemented, needs live-account verification | ⏳ Planned | 🔧 Requires config | SKIP = gracefully skipped via `capabilities()` check

> Version 1.0 (2026-06-27) described cPanel as an architecture stub. As of
> Phase 14-C through Phase 15-E, cPanel is a real, SSH-transport-backed
> driver with working UAPI-based provisioning for database, domain, cron,
> queue, and SSL. DirectAdmin/SFTP/Docker remain unimplemented stubs —
> unchanged from v1.0.

---

## Driver Implementation Status

| Driver | Type | Status | Notes |
|--------|------|--------|-------|
| `SshRemoteDriver` | VPS / Dedicated | **LIVE** | Full implementation, production-certified |
| `FakeRemoteDriver` | Testing | **LIVE** | Full test double |
| `CpanelDeploymentDriver` | cPanel Shared | **LIVE** | SSH transport (password/key only, no API-token live connection — see Phase 14-C). Database/Domain/Cron/Queue/SSL provisioning via UAPI — see below for per-feature verification status |
| `DirectAdminDeploymentDriver` | DirectAdmin | **STUB** | Architecture only — not implemented |
| `SftpDeploymentDriver` | Pure SFTP | **STUB** | Architecture only — not implemented |
| `DockerDeploymentDriver` | Docker | **STUB** | Architecture only — not implemented |

---

## Feature Support Matrix

| Feature | VPS/SSH | cPanel | DirectAdmin | SFTP | Docker |
|---------|---------|--------|-------------|------|--------|
| Database Creation | ✅ | ✅ UAPI (Mysql module) | ❌ | ❌ | ✅ |
| Domain Provisioning (subdomain/addon) | N/A — nginx vhost covers this | ⚠️ UAPI (DomainInfo/SubDomain/AddonDomain) — **needs live-account verification** | ❌ | ❌ | N/A |
| SSL Certificate | ✅ Certbot (Let's Encrypt) | ⚠️ UAPI AutoSSL trigger/check — **needs live-account verification** | ❌ | ❌ | 🔧 handled by reverse proxy |
| Queue Worker | ✅ Supervisor | ✅ cron-based (`queue:work --stop-when-empty`) | ❌ | ❌ | ✅ Supervisor-style |
| Scheduler | ✅ cron | ✅ UAPI Cron module | ✅ cron (untested) | ❌ | ✅ cron |
| Storage Symlink | ✅ | ✅ (SSH-based `artisan storage:link`) | ❌ | ❌ | ✅ |
| Supervisor | ✅ | ❌ (shared hosting cannot run persistent daemons) | ❌ | ❌ | ❌ |
| Redis | ✅ | ❌ | ❌ | ❌ | ✅ |
| Web Server Config (nginx vhost) | ✅ | ❌ (WHM/cPanel manages vhosts automatically — capability gate skips this stage) | ❌ | ❌ | ❌ (reverse proxy owns this) |
| Remote Command | ✅ SSH | ✅ SSH (same transport as VPS since Phase 14-C) | ❌ | ❌ | ⏳ |

---

## Installation Stage Compatibility

| Stage | VPS | cPanel | DirectAdmin | SFTP | Docker |
|-------|-----|--------|-------------|------|--------|
| ValidateServer (+ certbot preflight, Phase 15-D) | ✅ | ✅ | ❌ | ❌ | ✅ |
| ValidatePhp / ValidateExtensions / ValidateDisk / ValidatePermissions | ✅ | ✅ | ❌ | ❌ | ✅ |
| **ProvisionDomain** (Phase 15-D) | ✅ no-op (nginx owns this) | ⚠️ real UAPI — needs verification | ❌ | ❌ | ✅ no-op |
| UploadPackage / VerifyPackage / ExtractPackage | ✅ | ✅ | ❌ | ❌ | ✅ |
| GenerateEnvironment | ✅ | ✅ | ❌ | ❌ | ✅ |
| CreateDatabase | ✅ | ✅ UAPI | ❌ | ❌ | ✅ |
| RunMigrations / RunSeeder | ✅ | ✅ | ❌ | ❌ | ✅ |
| ConfigureStorage | ✅ | ✅ | ❌ | SKIP | ✅ |
| ConfigureQueue | ✅ | ✅ cron-based | ❌ | SKIP | ✅ |
| ConfigureScheduler | ✅ | ✅ UAPI | ❌ | SKIP | ✅ |
| ConfigureWebServer | ✅ | SKIP (capability gate) | SKIP | SKIP | SKIP |
| **IssueSsl** (Phase 15-B1) | ✅ Certbot — hard-fails install if unavailable | ⚠️ AutoSSL — degrades to warning if unavailable, needs verification | SKIP | SKIP | SKIP (no fallback owner) |
| ProvisionLicense / InitializeBackup / ConfigureMonitoring / InstallTheme | ✅ | ✅ | ❌ | ❌ | ✅ |
| CustomerReady (final validation) | ✅ | ✅ | ❌ | ❌ | ✅ |

---

## Authentication Matrix

| Hosting | Preferred Auth | Fallback Auth | Notes |
|---------|---------------|----------------|-------|
| VPS/SSH | SSH Private Key | SSH Password | |
| cPanel | SSH Password or Key | — | API-token auth is accepted at the form level but **rejected for live connections** (Phase 14-C — this driver connects over SSH only, not pure UAPI) |
| DirectAdmin | API Key | Username + Password (Basic Auth) | Driver unimplemented |
| SFTP | Username + Password | — | Driver unimplemented |
| Docker | SSH Key to host | SSH Password to host | Driver unimplemented |

**Security recommendation:** Always use SSH keys over password auth where the target account supports it.

---

## Deployment Constraints Per Hosting Type

### VPS / Dedicated (SSH)
- Full access: all installation stages supported.
- Certbot must be pre-installed on the server for SSL-enabled installs on a custom (non-Arvan-managed) domain — `ValidateServerStage` fails fast with a clear message if missing (Phase 15-D).
- Recommended: Ubuntu 22.04 LTS, PHP 8.1+, MySQL 8, Redis, Supervisor.

### cPanel Shared Hosting
- No root/WHM access used anywhere in this pipeline — only the cPanel account's own credential.
- SSH access on the account is required (not pure UAPI) — most hosts require this to be explicitly enabled per account.
- Database, domain (subdomain/addon), cron, and queue provisioning are implemented via UAPI, but **the exact UAPI module/function names have not been verified against a live cPanel account** — see `docs/project-memory` Phase 15-D/15-E audit notes. All failure paths degrade gracefully (domain: hard-fails the install; SSL: warns and completes without HTTPS) rather than crashing.
- SSL: AutoSSL trigger/check, not Certbot — availability depends on the hosting package having AutoSSL enabled by the provider (a WHM/root-side decision this pipeline cannot make).

### DirectAdmin Shared Hosting
- Architecture stub only — not implemented, not usable for a live installation.

### Pure SFTP
- Architecture stub only — not implemented.

### Docker
- Architecture stub only — not implemented.
