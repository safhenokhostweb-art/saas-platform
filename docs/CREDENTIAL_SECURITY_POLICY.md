# Credential Security Policy

**Phase:** PHASE-003D6  
**Status:** Enterprise Certified  
**Date:** 2026-06-28

---

## Cryptographic Standards

### Encryption at Rest
- **Algorithm:** AES-256-GCM (authenticated encryption)
- **Key:** `INSTALLER_MASTER_KEY` (base64-encoded, minimum 16 bytes raw)
- **Nonce:** 12-byte random per encryption operation
- **Tag:** 16-byte authentication tag
- **Storage format:** `base64(nonce[12] + ciphertext + tag[16])`

### Key Generation
- **Ed25519:** phpseclib3 `EC::createKey('Ed25519')` — 256-bit security level
- **RSA-4096:** phpseclib3 `RSA::createKey(4096)` — 4096-bit key, equivalent security

### Fingerprints
- **Format:** `SHA256:base64url-of-sha256-of-key-blob`
- **Host fingerprints:** Stored after first successful connection, verified on all subsequent connections
- **Key fingerprints:** Computed from public key blob on generation/import

---

## Credential Fields

| Model | Field | Type | Encrypted | Notes |
|-------|-------|------|-----------|-------|
| CustomerRemoteServer | private_key_encrypted | text | ✅ AES-256-GCM | SSH private key |
| CustomerRemoteServer | password_encrypted | string | ✅ AES-256-GCM | SSH or SFTP password |
| CustomerRemoteServer | api_token_encrypted | text | ✅ AES-256-GCM | API token |
| SshKey | private_key_encrypted | text | ✅ AES-256-GCM | Managed SSH private key |
| SshKey | passphrase_encrypted | text | ✅ AES-256-GCM | Optional key passphrase |
| CustomerRemoteServer | public_key (via SshKey) | text | ❌ plaintext | Safe to store unencrypted |
| CustomerRemoteServer | key_fingerprint | string | ❌ plaintext | Hash, not secret |

---

## Forbidden Operations

The following MUST NEVER occur:

1. **Plaintext private key in logs** — Never log, dump, or print decrypted keys
2. **Plaintext password in logs** — Never include passwords in exception messages or logs
3. **Plaintext API token in logs** — Never include token values in logs
4. **Plaintext secrets in reports** — Installation reports must not contain any credential values
5. **Plaintext secrets in responses** — JSON responses never contain decrypted credentials
6. **Model serialization of secrets** — `private_key_encrypted`, `password_encrypted`, `api_token_encrypted` are in `$hidden`

---

## Master Key Rotation

Call `CredentialVault::rotate(string $newMasterKeyBase64)` to re-encrypt all credentials.
This method re-encrypts:
- `CustomerRemoteServer`: `private_key_encrypted`, `password_encrypted`, `api_token_encrypted`
- `SshKey`: `private_key_encrypted`, `passphrase_encrypted`

Rotation does NOT interrupt active sessions. Update `.env` AFTER rotation completes.

Rotation procedure:
1. Generate new key: `base64_encode(random_bytes(32))`
2. Call `CredentialVault::rotate($newKey)` — returns count of rotated records
3. Update `INSTALLER_MASTER_KEY` in `.env`
4. Run `php artisan config:clear`
5. Verify at least one connection test succeeds

---

## Host Fingerprint Pinning

For SSH/Docker deployment targets:
- **First connection:** fingerprint stored in `key_fingerprint` (no verification — trust on first use)
- **Subsequent connections:** fingerprint compared; mismatch throws `RuntimeException` with "MITM" warning
- **Fingerprint cleared:** only by admin via the Authentication Center UI

This protects against:
- DNS hijacking
- ARP spoofing
- BGP hijacking to a different server

If a legitimate server key change occurs (OS reinstall, key rotation), admin must manually clear the stored fingerprint in the deployment target settings.

---

## Authentication Method Hierarchy (by security level)

| Level | Method | Notes |
|-------|--------|-------|
| 1 (Best) | SSH Ed25519 Key | Recommended for all SSH/Docker targets |
| 2 | SSH RSA-4096 Key | For legacy server compatibility |
| 3 | API Token (cPanel/DA) | Required for panel-type targets |
| 4 (Allowed) | SSH Password | Backward compatible — security audit flags HIGH |
| 5 (Allowed) | SFTP Password | Only option for pure SFTP targets |
| — (Forbidden) | Plaintext storage | Violates security policy |

---

## Security Audit Findings Mapping

| Finding | Severity | Remediation |
|---------|----------|-------------|
| Password auth on SSH/Docker target | HIGH | Use Migration Wizard |
| Unverified host fingerprint | MEDIUM | Test connection (auto-stores fingerprint) |
| Last test result: failure | HIGH | Investigate credentials and network |
| Not tested in >30 days | LOW | Run connection test |
| SSH key not rotated in >90 days | LOW | Use Key Rotation in Auth Center |
| API token stale (>30 days unused) | MEDIUM | Verify token still valid or replace |

---

## Incident Response

If a credential compromise is suspected:

1. **Immediate:** Revoke the compromised key via Auth Center → SSH Keys → Revoke
2. **Immediate:** Generate new key, install, activate (the rotation flow does this atomically)
3. **Investigate:** Check `last_used_at` on SshKey for unauthorized access times
4. **If API token:** Replace token immediately via Auth Center → API Tokens → Rotate
5. **Master key:** If `INSTALLER_MASTER_KEY` is suspected compromised, rotate immediately
6. **Alert:** The `SshDeploymentDriver::verifyFingerprint()` will abort + throw if fingerprint changes — treat this as a CRITICAL incident
