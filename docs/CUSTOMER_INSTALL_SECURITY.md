# Customer Installation Security Model

**Document ID:** CIE-SEC-001  
**Phase:** PHASE-002  
**Status:** ARCHITECTURE_COMPLETE  
**Date:** 2026-06-27

---

## 1. Threat Model

| Threat | Target | Severity | Mitigation |
|--------|--------|----------|------------|
| Private key exfiltration | customer_remote_servers | CRITICAL | AES-256-GCM encryption at rest |
| Package tampering | Release ZIP in transit | CRITICAL | SHA-256 double-verification (local + remote) |
| Package substitution | Arvan storage | HIGH | Signed manifest + BaselineManifestService |
| Rollback exploitation | Remote server state | HIGH | Rollback checkpoints + audit log |
| Path traversal | Server file ops | HIGH | Path sanitization on all remote paths |
| Privilege escalation | SSH execution | HIGH | Minimal-privilege deployment user |
| Replay attack | Installation job | MEDIUM | Installation UUID per deployment, one-time use |
| Log injection | Command output | MEDIUM | All remote output sanitized before DB write |
| Credential brute-force | SSH | LOW | Key-based auth preferred; password locked after test |

---

## 2. Credential Security

### 2.1 Storage

**Rule: No plaintext credentials are ever stored in the database.**

All sensitive fields in `customer_remote_servers`:
- `private_key_encrypted` — RSA private key, AES-256-GCM encrypted
- `password_encrypted` — SSH password (if auth_method = password), AES-256-GCM encrypted

Encryption uses `CredentialVault` service with `INSTALLER_MASTER_KEY` env as the master key.

### 2.2 In-Memory Handling

```
Encrypted DB value
    │
    ▼ CredentialVault::decrypt()  ← only inside RemoteConnectionService
Plaintext string (in PHP memory)
    │
    ▼ Passed to phpseclib SSH authenticator
    │
    ▼ PHP GC collects after connection established
```

Decrypted credentials are **never**:
- Written to any log file
- Returned in any API response
- Stored in session or cache
- Passed to `CommandResult` output

### 2.3 Environment Variables — MySQL Password

All MySQL commands on the remote server use `MYSQL_PWD` environment variable injection, not the `-p` flag. The `-p` flag exposes passwords in process listings (`ps aux`).

```bash
# CORRECT (password hidden from process list)
MYSQL_PWD={password} mysql -u {user} -e "CREATE DATABASE ..."

# FORBIDDEN
mysql -u {user} -p{password} -e "CREATE DATABASE ..."
```

### 2.4 INSTALLER_MASTER_KEY

- 32-byte random key, base64-encoded
- Generated via `php artisan installer:generate-master-key`
- Stored only in platform `.env` — never in DB, never committed to git
- Loss = inability to decrypt stored SSH credentials
- Rotation supported via `CredentialVault::rotate(newKey)` — re-encrypts all records

---

## 3. Package Integrity

### 3.1 Two-Stage SHA-256 Verification

```
Stage 1 (UPLOAD_PACKAGE):
  Platform: sha256sum(local_zip) == customer_installation_packages.sha256
  → If mismatch: abort before upload

Stage 2 (VERIFY_PACKAGE):
  Remote: sha256sum /tmp/customer_install_{uuid}.zip
  → Compare with stored sha256
  → If mismatch: abort, delete remote zip, hard fail
```

A corrupted or tampered package will never be extracted. Both checks must pass.

### 3.2 Manifest Verification

Release packages include a `manifest.json` signed with the platform's manifest checksum. `BaselineManifestService::verify()` confirms the manifest itself is untampered before trusting its contents (version, extensions, SHA-256).

### 3.3 Arvan Storage Access

The release package bucket in Arvan uses a dedicated read-only IAM credential for download. The write credential (used by deploy.ps1) is separate and not accessible from the installation engine.

---

## 4. SSH Security

### 4.1 Host Key Pinning

On first connection, the remote server's SSH host key fingerprint is recorded. On every subsequent connection it is verified. A fingerprint mismatch:
- Aborts the connection immediately
- Creates a `SECURITY_EVENT` log entry with severity CRITICAL
- Fires a Slack alert to platform ops

**This prevents man-in-the-middle attacks where a malicious server is substituted.**

### 4.2 Minimal Privilege Deployment User

The SSH user configured on remote servers should **not** be `root`. Recommended setup:
- User: `deployer` with sudo access to specific commands only
- Passwordless sudo for: `supervisorctl`, `systemctl reload php*-fpm`, `chown`, `chmod`
- No sudo for: `rm -rf /`, `useradd`, `visudo`

Platform documentation (in Installation Center) provides the recommended `sudoers` snippet.

### 4.3 Key Rotation

```
1. Admin triggers key rotation in Installation Center
2. Platform generates new RSA-4096 keypair
3. New public key pushed to remote ~/.ssh/authorized_keys (using old key)
4. Old public key removed from authorized_keys
5. New private key encrypted and stored in customer_remote_servers
6. Connection test runs to verify new key works
7. Rotation logged in customer_installation_logs
```

---

## 5. Command Injection Prevention

All values interpolated into remote shell commands are sanitized:

```php
// Path sanitization
$safePath = preg_replace('/[^a-zA-Z0-9\/_\-.]/', '', $remotePath);

// Shell argument quoting (all dynamic values)
$safeSlug = escapeshellarg($customerSlug);
$safeDb   = escapeshellarg($dbName);

// No user input ever reaches a remote command directly
// All values come from platform DB records (already validated on input)
```

**Rule:** Remote commands are assembled from platform-controlled values only (slug generated by platform, path configured by platform). No customer-provided string ever appears verbatim in a remote command.

---

## 6. Audit Logging

### 6.1 Immutable Installation Log

Every event in `customer_installation_logs` is:
- Written on event occurrence (no batch writes)
- Never updated (only INSERT operations)
- Never deleted (no delete route, no cascade delete from installations table)

The logs table is the legal audit trail for every installation action.

### 6.2 What Is Logged

| Event | Fields Logged |
|-------|---------------|
| Stage start | installation_id, stage, timestamp, executing_user_id |
| Stage complete | installation_id, stage, duration_ms, summary output |
| Stage failure | installation_id, stage, error message, stack trace hash |
| Rollback checkpoint | installation_id, stage, snapshot (sanitized — no credentials) |
| Rollback execution | installation_id, stage, result |
| Connection test | server_id, result summary, fingerprint |
| Credential access | server_id, accessed_by_user_id, purpose (connect/test/rotate) |
| Key rotation | server_id, rotated_by, old_fingerprint_prefix, new_fingerprint_prefix |

### 6.3 What Is Never Logged

- SSH private keys (encrypted or decrypted)
- SSH passwords
- Database passwords
- Full MySQL command output containing passwords
- Customer SMTP credentials
- License keys (only first 8 chars logged)

---

## 7. Rollback Security

### 7.1 Rollback Checkpoint Snapshots

Before any destructive stage (EXTRACT, CREATE_DATABASE, CONFIGURE_QUEUE), a rollback checkpoint is written to `customer_install_rollbacks.snapshot`. The snapshot contains only the information needed to undo that stage:

```json
// EXTRACT checkpoint
{ "remote_path": "/var/www/cust_acme/" }

// CREATE_DATABASE checkpoint
{ "db_name": "cust_acme_x7f3a2", "db_user": "cust_acme_usr" }

// CONFIGURE_QUEUE checkpoint
{ "config_path": "/etc/supervisor/conf.d/cust_acme_worker.conf" }
```

No passwords in snapshots. Rollback operations use the already-established SSH connection (which uses stored encrypted credentials, not snapshot contents).

### 7.2 Rollback Authorization

Rollback can be triggered only by:
- Platform admin with `manage_installations` permission
- Automated pipeline on stage failure (dispatches `CustomerInstallationRollbackJob`)

Rollback is **not** reversible. Once rolled back, a new installation must be started.

---

## 8. Network Security

### 8.1 Outbound Connections

The platform makes outbound connections to:
- Arvan Object Storage (HTTPS 443) — package download
- Remote SSH servers (port 22 or custom)

Both use TLS/SSH — no plaintext transport.

### 8.2 No Inbound from Remote Servers

Remote servers do not call back to the platform during installation. All communication is initiated by the platform (push model). This eliminates the attack surface of inbound webhook exploitation.

### 8.3 Installation UUID

Each installation gets a UUID used in:
- Remote temp file path: `/tmp/customer_install_{uuid}.zip`
- Redis progress channel: `install_progress_{uuid}`
- Audit correlation

The UUID is single-use. Once the install is complete or rolled back, the temp file is deleted.

---

## 9. Access Control

### 9.1 Required Permission

All CIE admin routes require: `manage_installations` gate (new gate to be added to `AuthServiceProvider`).

Only super admins have this gate by default.

### 9.2 Read vs Write Separation

| Action | Permission |
|--------|-----------|
| View installation history | `view_installations` |
| View installation logs | `view_installations` |
| Create new installation | `manage_installations` |
| Trigger rollback | `manage_installations` |
| Add remote server | `manage_installations` |
| Test connection | `manage_installations` |
| Rotate SSH key | `manage_installations` |
| View package registry | `view_installations` |

### 9.3 Rate Limiting

| Endpoint | Limit |
|----------|-------|
| POST /servers/{id}/test | 10 per minute per admin |
| POST / (new installation) | 3 per minute per admin |
| POST /{id}/rollback | 2 per minute per admin |

---

## 10. Compliance Notes

- **No credentials stored plaintext** — satisfies basic security audit requirement
- **Full audit trail** — every installation action recorded immutably
- **Key pinning** — protects against MITM
- **Package integrity** — SHA-256 double-verification prevents supply chain attack via storage compromise
- **Rollback capability** — failed installs leave no dangling resources
- **Principle of least privilege** — deployment user has minimal sudo access

---

_Generated: 2026-06-27 | Phase: PHASE-002 | CIE-SEC-001_
