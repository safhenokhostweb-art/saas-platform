# FUTURE DEPLOYMENT DRIVERS

**Version:** 1.0  
**Date:** 2026-06-27  

---

## Roadmap

| Priority | Driver | Hosting Type | Effort | Blocker |
|----------|--------|-------------|--------|---------|
| P1 | `CpanelDeploymentDriver` | cPanel | ~40h | cPanel UAPI access + test account |
| P2 | `DirectAdminDeploymentDriver` | DirectAdmin | ~30h | DirectAdmin API test account |
| P3 | `SftpDeploymentDriver` | Pure SFTP | ~20h | Post-install manual steps design |
| P4 | `DockerDeploymentDriver` | Docker | ~50h | Docker host access + container spec |
| P5 | `KubernetesDeploymentDriver` | Kubernetes | ~80h | K8s cluster + Helm chart design |
| P6 | `CloudDeploymentDriver` | Managed Cloud | TBD | Cloud provider selection |

---

## cPanel Driver Implementation Guide

**File:** `app/Services/CustomerInstallation/Drivers/CpanelDeploymentDriver.php`

### API Reference
- cPanel UAPI: `https://{host}:2083/execute/{Module}/{Function}`
- WHM API v1: `https://{host}:2087/json-api/{function}`

### Authentication
```
GET https://{host}:2083/execute/Email/list_pops
Authorization: cpanel {username}:{api_token}
```

### Key Operations to Implement

| Operation | cPanel UAPI Endpoint |
|-----------|---------------------|
| testConnection | `GET /execute/Stats/get_bandwidth` |
| execute | SSH fallback (if SSH available) or not supported |
| upload | cPanel File Manager API or FTP |
| createDatabase | `POST /execute/Mysql/create_database` |
| createUser | `POST /execute/Mysql/create_user` |
| addCron | `POST /execute/Cron/add_line` |
| checkDisk | `GET /execute/DiskUsage/fetchdiskusage` |
| installSSL | `POST /execute/SSL/install_ssl` |

### Capability Notes
- `supportsQueue`: ❌ — no persistent process without Supervisor
- `supportsSupervisor`: ❌ — shared hosting limitation
- `supportsRedis`: ❌ — rarely available on shared plans
- `supportsStorageLink`: ❌ — no shell artisan access
- `supportsDatabaseCreation`: ✅ via UAPI
- `supportsCron`: ✅ via UAPI Cron module

---

## DirectAdmin Driver Implementation Guide

**File:** `app/Services/CustomerInstallation/Drivers/DirectAdminDeploymentDriver.php`

### API Reference
- DirectAdmin API: `https://{host}:{port}/CMD_API_{COMMAND}` (form-encoded)
- Default port: 2222 (HTTPS), 2223 (HTTP)

### Authentication
```
GET https://{host}:2222/CMD_API_SHOW_USER_CONFIG
Authorization: Basic base64({username}|{loginkey}:{password})
```

### Key Operations to Implement

| Operation | DirectAdmin API Command |
|-----------|------------------------|
| testConnection | `CMD_API_SHOW_USER_CONFIG` |
| createDatabase | `CMD_API_DATABASES` (action=create) |
| addCron | `CMD_API_CRONJOBS` (action=create) |
| checkDisk | `CMD_API_SHOW_USER_USAGE` |

---

## SFTP Driver Implementation Guide

**File:** `app/Services/CustomerInstallation/Drivers/SftpDeploymentDriver.php`

### Library
Use `phpseclib3\Net\SFTP` — already in composer dependencies.

### Key Operations
```php
// connect
$sftp = new SFTP($this->target->host, $this->target->port);
$sftp->login($this->target->username, $decryptedPassword);

// upload
$sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);

// download
$sftp->get($remotePath, $localPath);

// fileExists
$sftp->file_exists($remotePath);

// diskFree — not available via SFTP, return -1 or call stat
```

### SFTP Installation Flow
Since no command execution is possible:
1. Upload ZIP package via SFTP
2. Generate `.env` locally and upload via SFTP
3. Return `ManualActionRequired` result from stages that need artisan
4. Provide post-install checklist to the admin

---

## Docker Driver Implementation Guide

**File:** `app/Services/CustomerInstallation/Drivers/DockerDeploymentDriver.php`

### Implementation Approach

**Option A — SSH + docker exec (recommended for stubs)**
```php
// Connect via SSH to Docker host
$ssh = new SSH2($this->target->host, $this->target->port);
// Then wrap all commands with docker exec
$result = $ssh->exec("docker exec {$containerName} {$command}");
```

**Option B — Docker Remote API (HTTP)**
```
GET https://{host}:2376/containers/{id}/json
POST https://{host}:2376/containers/{id}/exec
```

### Key Differences from SSH
- `execute()` wraps command with `docker exec {container}`
- `upload()` uses `docker cp {local} {container}:{remote}`
- `download()` uses `docker cp {container}:{remote} {local}`
- `diskFree()` exec into container for df
- Reboot = `docker restart {container}`
- No Supervisor — use Horizon or `php-fpm` + custom entrypoint

---

## Kubernetes Driver (Future)

**Not yet planned. Rough design only.**

```php
class KubernetesDeploymentDriver implements DeploymentDriverInterface
{
    // Authentication: kubeconfig file or service account token
    // Execution: kubectl exec {pod} -- {command}
    // Upload: kubectl cp {local} {pod}:{remote}
    // Database: Kubernetes Job for migration
    // Scaling: kubectl scale deployment ...
}
```

---

## Cloud Driver (Future)

**Not yet planned. Provider-dependent.**

Candidates:
- AWS (EC2 + RDS + ElastiCache + S3)
- Hetzner Cloud
- Arvan Cloud (already used for object storage — natural extension)
- DigitalOcean

Implementation would likely extend `SshDeploymentDriver` with cloud-specific database/storage provisioning.

---

## Implementation Rule

**When implementing any future driver, the rule is absolute:**

> Add only a new Driver class.  
> Never modify the installation pipeline.  
> Never add conditionals to existing stages.  
> Only check `capabilities()` in stages that need host-type awareness.

The pipeline's 20 stages are frozen. New drivers adapt to them — not the other way around.
