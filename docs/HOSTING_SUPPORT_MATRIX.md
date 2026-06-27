# HOSTING SUPPORT MATRIX

**Version:** 1.0  
**Date:** 2026-06-27  

Legend: ✅ Supported | ❌ Not supported | ⏳ Planned | 🔧 Requires config

---

## Driver Implementation Status

| Driver | Type | Status | Notes |
|--------|------|--------|-------|
| `SshDeploymentDriver` | VPS / Dedicated | **LIVE** | Full implementation, production-certified |
| `FakeRemoteDriver` | Testing | **LIVE** | Full test double |
| `CpanelDeploymentDriver` | cPanel Shared | **STUB** | Architecture only — not implemented |
| `DirectAdminDeploymentDriver` | DirectAdmin | **STUB** | Architecture only — not implemented |
| `SftpDeploymentDriver` | Pure SFTP | **STUB** | Architecture only — not implemented |
| `DockerDeploymentDriver` | Docker | **STUB** | Architecture only — not implemented |

---

## Feature Support Matrix

| Feature | VPS/SSH | cPanel | DirectAdmin | SFTP | Docker | Kubernetes* | Cloud* |
|---------|---------|--------|-------------|------|--------|-------------|--------|
| Queue Worker | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Scheduler | ✅ | ✅ cron | ✅ cron | ❌ | ✅ | ✅ | ✅ |
| Cron | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ |
| Supervisor | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Database Creation | ✅ | ✅ API | ✅ API | ❌ | ✅ | ✅ | ✅ |
| SSL Certificate | ✅ | ✅ | ✅ | ❌ | 🔧 proxy | ✅ | ✅ |
| Storage Symlink | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Redis | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Docker | ✅ | ❌ | ❌ | ❌ | ✅ | N/A | ❌ |
| Server Reboot | ✅ | ❌ | ❌ | ❌ | ✅ restart | ✅ | ✅ |
| File Upload | ✅ SFTP | ✅ FTP/API | ✅ FTP/API | ✅ | ✅ cp | ✅ | ✅ |
| Remote Command | ✅ SSH | ⏳ UAPI | ⏳ API | ❌ | ⏳ exec | ⏳ | ⏳ |

*Kubernetes and Cloud: future drivers, not yet planned.

---

## Installation Stage Compatibility

| Stage | VPS | cPanel | DirectAdmin | SFTP | Docker |
|-------|-----|--------|-------------|------|--------|
| ValidateServer | ✅ | ⏳ | ⏳ | ⏳ | ⏳ |
| ValidatePhp | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| ValidateExtensions | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| ValidateDisk | ✅ | ⏳ | ⏳ | ⏳ | ⏳ |
| ValidatePermissions | ✅ | ⏳ | ⏳ | ⏳ | ⏳ |
| UploadPackage | ✅ SFTP | ⏳ FTP | ⏳ FTP | ✅ | ⏳ |
| VerifyPackage | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| ExtractPackage | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| GenerateEnvironment | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| CreateDatabase | ✅ | ⏳ API | ⏳ API | ❌ | ⏳ |
| RunMigrations | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| RunSeeder | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| ConfigureStorage | ✅ | SKIP | SKIP | SKIP | ✅ |
| ConfigureQueue | ✅ | SKIP | SKIP | SKIP | ✅ |
| ConfigureScheduler | ✅ supervisor | ⏳ cron | ⏳ cron | SKIP | ✅ cron |
| ProvisionLicense | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| InitializeBackup | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| ConfigureMonitoring | ✅ | ⏳ | ⏳ | SKIP | ⏳ |
| InstallTheme | ✅ | ⏳ | ⏳ | ❌ | ⏳ |
| CustomerReady | ✅ | ⏳ | ⏳ | ⏳ | ⏳ |

SKIP = stage gracefully skipped via `capabilities()` check.

---

## Authentication Matrix

| Hosting | Preferred Auth | Fallback Auth |
|---------|---------------|---------------|
| VPS/SSH | SSH Private Key | SSH Password |
| cPanel | cPanel API Token | Username + Password |
| DirectAdmin | API Key | Username + Password (Basic Auth) |
| SFTP | Username + Password | — |
| Docker | SSH Key to host | SSH Password to host |

**Security recommendation:** Always use API tokens or SSH keys. Avoid password auth in production.

---

## Deployment Constraints Per Hosting Type

### VPS / Dedicated (SSH)
- Full access: all 20 installation stages supported
- Recommended: Ubuntu 22.04 LTS, PHP 8.1+, MySQL 8, Redis, Supervisor
- Root or sudo-capable user required

### cPanel Shared Hosting
- Limited access: no supervisor, no redis, no shell symlinks
- Queue workers: not possible (use file-based queuing or external cron job)
- Database: created via cPanel MySQL Databases API
- Laravel setup requires PHP selector and SSH access (varies by host)

### DirectAdmin Shared Hosting
- Similar to cPanel limitations
- Database: created via DirectAdmin MySQL API
- Cron available via DirectAdmin Cron Jobs panel

### Pure SFTP
- File upload/download only
- No command execution — manual artisan commands required post-install
- Suitable for static sites or hosts with zero shell access
- `.env` must be pre-generated and uploaded manually

### Docker
- Near-VPS capabilities
- No Supervisor — use container process manager or Horizon
- SSL handled by reverse proxy (nginx/traefik) — not by the driver
- Reboot = container restart
