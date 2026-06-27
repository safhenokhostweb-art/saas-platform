# Customer Remote Deployment — Architecture

**Document ID:** CIE-REMOTE-001  
**Phase:** PHASE-002  
**Status:** ARCHITECTURE_COMPLETE  
**Date:** 2026-06-27

---

## 1. Remote Connection Architecture

```
CustomerInstallationOrchestrator
    │
    ▼
RemoteConnectionService
    │
    ├── SshRemoteDriver          (Phase 1 — primary)
    ├── DockerDriver             (Future)
    ├── CpanelDriver             (Future)
    └── DirectAdminDriver        (Future)
```

All drivers implement `RemoteConnectionInterface`. The orchestrator never depends on a concrete driver class.

---

## 2. SshRemoteDriver — Primary Driver

**Library:** `phpseclib/phpseclib` (v3.x) — pure PHP, no native OpenSSH dependency.

### 2.1 Connection Sequence

```
1. Load CustomerRemoteServer record
2. Decrypt credentials using INSTALLER_MASTER_KEY
3. Determine auth method: KEY or PASSWORD
4. For KEY auth:
   a. Decrypt private key from DB
   b. Load into phpseclib RSA object
   c. Verify server host key fingerprint (stored in customer_remote_servers.key_fingerprint)
5. For PASSWORD auth:
   a. Decrypt password
   b. Authenticate
6. SSH::connect(host, port) with timeout 10s
7. On success: connection object passed to InstallationContext
8. On failure: ConnectionTestResult with error details
```

### 2.2 Host Key Verification

On first connection to a new server, the host key is recorded as `SHA256:{base64}` fingerprint in `customer_remote_servers.key_fingerprint`.

On subsequent connections, the fingerprint is verified before authentication proceeds. A mismatch causes a hard abort — logged as a security event.

**First-connection flow:**
```
TestConnection API called → SshRemoteDriver connects → captures fingerprint →
displays to admin → admin confirms → fingerprint stored → server record finalized
```

### 2.3 Command Execution

```php
$result = $connection->execute('php artisan migrate --force');
// Returns CommandResult {
//   exitCode: 0,
//   stdout: "Migration ran",
//   stderr: "",
//   durationMs: 1450
// }
```

All commands are executed with:
- `DEBIAN_FRONTEND=noninteractive` (prevents interactive prompts)
- `MYSQL_PWD={password}` injected via environment for any MySQL commands (never via -p flag)
- Hard timeout per stage (configured in stage definition)

### 2.4 SFTP Upload

```
1. Open SFTP subsystem on existing SSH connection
2. Split large files: chunked upload (4MB chunks)
3. Report progress: Redis RPUSH every 5% increment
4. Verify remote file size matches local after upload
5. On mismatch: retry once, then fail
```

Bandwidth throttle is configurable: `config('customer_installation.upload_kbps_limit')` — 0 = unlimited.

### 2.5 Connection Pooling

A single SSH connection is opened at the start of `CustomerInstallationPipelineJob` and reused across all stages in that job. Connection is closed in the job's `finally` block.

If a stage triggers a sub-job (e.g., `CustomerPackageUploadJob`), that job opens its own connection using the stored server credentials.

---

## 3. Credential Encryption

### 3.1 Encryption Algorithm

**Algorithm:** AES-256-GCM with random nonce per encryption  
**Key:** `INSTALLER_MASTER_KEY` env (32-byte base64-encoded key)  
**Format stored in DB:** `base64(nonce + ciphertext + tag)` — single concatenated string

### 3.2 CredentialVault Service

```php
namespace App\Services\CustomerInstallation;

class CredentialVault
{
    // Encrypts on write to customer_remote_servers
    public function encrypt(string $plaintext): string;

    // Decrypts on read (only called inside RemoteConnectionService)
    public function decrypt(string $ciphertext): string;

    // Rotates all stored credentials to a new master key
    public function rotate(string $newMasterKey): void;
}
```

**Rule:** `decrypt()` is only called inside `RemoteConnectionService`. It is never called in controllers or views. Decrypted values are never logged.

### 3.3 Key Generation

```bash
# Generate a new INSTALLER_MASTER_KEY
php artisan installer:generate-master-key
```

Stored in platform `.env`. Must be backed up securely. Loss of master key = loss of ability to connect to all registered servers.

---

## 4. SSH Key Management

### 4.1 Per-Installation Keypair Strategy

For each new customer installation on our own VPS, the platform generates a dedicated SSH keypair:

```
Platform generates:
  private_key (RSA-4096) → encrypted with INSTALLER_MASTER_KEY → stored in customer_remote_servers
  public_key → pushed to remote ~/.ssh/authorized_keys during server registration
```

**Benefits:**
- Compromise of one customer's private key does not affect others
- Keys can be revoked per-customer without affecting platform access
- Full audit trail: which key was used for which installation

### 4.2 Customer-Owned VPS (Remote VPS Mode)

Customer provides:
- SSH host, port, username
- Either: SSH private key (PEM format) OR password

Admin pastes the private key in the server registration form. It is immediately encrypted and stored. The original form value is never logged.

### 4.3 Key Revocation

```
admin triggers: POST /admin/infrastructure/customer-deployment/servers/{id}/revoke-key

Flow:
1. Connect to remote server using current key
2. Remove public key from ~/.ssh/authorized_keys
3. Delete encrypted private key from customer_remote_servers
4. Mark server as auth_method = revoked
5. Log revocation event with admin user_id
```

---

## 5. Connection Test Protocol

**Endpoint:** `POST /admin/infrastructure/customer-deployment/servers/{id}/test`

**Test sequence:**

```
1. CONNECT          → TCP + SSH handshake
2. FINGERPRINT      → verify stored fingerprint matches live server
3. PHP_VERSION      → `php -v`
4. EXTENSIONS       → `php -m`
5. DISK_FREE        → `df -B1 /var/www`
6. PERMISSIONS      → write + delete test file in /var/www
7. MYSQL_ACCESS     → `mysql -u root -e "SELECT 1"` (or configured admin user)
8. SUPERVISOR       → `which supervisorctl`
9. DISCONNECT
```

Returns `ConnectionTestResult`:
```json
{
  "connected": true,
  "os": "Ubuntu 22.04.4 LTS",
  "php_version": "8.2.20",
  "php_extensions": ["bcmath", "curl", "pdo_mysql", ...],
  "disk_free_bytes": 42949672960,
  "ping_ms": 23,
  "mysql_accessible": true,
  "supervisor_present": true,
  "warnings": [],
  "error": null
}
```

---

## 6. Rollback on Connection Failure

If the SSH connection drops mid-pipeline:

1. Stage marked as `status = failed` with error `CONNECTION_LOST`
2. `CustomerInstallationRollbackJob` dispatched (uses independent connection)
3. Rollback attempts to connect, executes rollback commands in reverse order
4. If rollback connection also fails → manual intervention required
5. Admin sees status `rollback_failed` in Installation Center
6. Slack alert fired immediately: `[CRITICAL] Customer install rollback failed: {company}`

---

## 7. Network Architecture

### 7.1 Our VPS (87.107.12.81) — Mode: Direct SSH

```
Platform Admin → SSH → 87.107.12.81:9011
                       /var/www/{customer_slug}/
```

The platform itself initiates outbound SSH to the same VPS. The SSH connection is loopback (same datacenter). This is the lowest-latency deployment path.

### 7.2 Customer Remote VPS — Mode: Outbound SSH

```
Platform Admin → SSH → {customer_vps_host}:{port}
```

Platform must be able to reach the remote VPS outbound on port 22 (or custom). If the customer VPS is behind a firewall, they must whitelist the platform's outbound IP.

### 7.3 Independent Linux Server

Same as Remote VPS. Customer provides SSH credentials for a server they own or rent.

---

## 8. Package Transfer Strategy

### 8.1 Package Registry

Release packages are stored in Arvan object storage:
```
saas-platform/
  releases/
    customer-site/
      v1.0.0/
        customer-site-v1.0.0.zip
        customer-site-v1.0.0.sha256
        manifest.json
      v1.0.1/
        ...
```

`manifest.json` contains:
```json
{
  "version": "1.0.0",
  "sha256": "abc123...",
  "size_bytes": 18432000,
  "released_at": "2026-06-27T10:00:00Z",
  "min_php": "8.1",
  "min_mysql": "8.0",
  "required_extensions": ["bcmath", "curl", ...]
}
```

### 8.2 Download → Verify → Upload Flow

```
Arvan Object Storage
    │
    ▼ (1) ArvanBackupService::download()
Platform temp storage (/tmp/cust_install_{uuid}.zip)
    │
    ▼ (2) BaselineManifestService::verify() — SHA-256 check
    │
    ▼ (3) CustomerPackageUploadJob — SFTP chunked upload
Remote /tmp/customer_install_{uuid}.zip
    │
    ▼ (4) sha256sum on remote — verify again
    │
    ▼ (5) unzip to /var/www/{slug}/
    │
    ▼ (6) rm /tmp/customer_install_{uuid}.zip
```

**Two-stage verification:** verify locally (Step 2) and verify remotely (Step 4). A package that passes both checks is guaranteed to be intact.

### 8.3 Never Git Clone

Git is not required on remote servers. No `git clone`. No `composer install` on remote servers. The release ZIP is self-contained: all dependencies pre-bundled in `vendor/`.

This is enforced at pipeline level: no stage issues a `git` or `composer` command on the remote server.

---

## 9. Bandwidth and Timeout Configuration

```php
// config/customer_installation.php

return [
    'ssh' => [
        'timeout_connect' => 10,        // seconds
        'timeout_execute'  => 600,       // seconds per command
        'timeout_upload'   => 1800,      // seconds for package upload
    ],
    'upload' => [
        'chunk_size_bytes' => 4 * 1024 * 1024,  // 4 MB
        'kbps_limit'       => 0,                 // 0 = unlimited
        'retry_attempts'   => 3,
        'retry_delay_ms'   => 2000,
    ],
    'package' => [
        'arvan_prefix'     => 'saas-platform/releases/customer-site',
        'local_temp_path'  => storage_path('app/install-packages'),
    ],
    'encryption' => [
        'algorithm'        => 'aes-256-gcm',
        'master_key_env'   => 'INSTALLER_MASTER_KEY',
    ],
];
```

---

## 10. Future Driver Architecture

Adding a new driver (e.g., cPanel):

```php
namespace App\Services\CustomerInstallation\Drivers;

class CpanelDriver implements RemoteConnectionInterface
{
    // Uses cPanel WHM API v1 instead of SSH
    // Maps execute() to WHM API calls
    // Maps upload() to WHM File Manager API
    // Maps fileExists() to WHM stat API
}
```

Register in `config/customer_installation.php`:
```php
'drivers' => [
    'ssh'          => SshRemoteDriver::class,
    'cpanel'       => CpanelDriver::class,        // future
    'directadmin'  => DirectAdminDriver::class,   // future
    'docker'       => DockerDriver::class,         // future
],
```

The `customer_remote_servers.type` column determines which driver is instantiated by `RemoteConnectionService::buildDriver()`. No pipeline changes required.

---

_Generated: 2026-06-27 | Phase: PHASE-002 | CIE-REMOTE-001_
