# SSH Key Management

**Phase:** PHASE-003D6  
**Status:** Complete  
**Date:** 2026-06-28

---

## Overview

The SSH Key Manager provides full lifecycle management of SSH key pairs used for
deployment target authentication. Keys are generated server-side, stored encrypted,
and can be installed, tested, rotated, and revoked through the Authentication Center UI.

**Core invariant:** Private keys are never exposed in plaintext outside the method
that generates or processes them. All storage goes through CredentialVault (AES-256-GCM).

---

## Key Types

| Type | Algorithm | Bits | Use Case |
|------|-----------|------|----------|
| Ed25519 | Edwards-curve | 256-bit security | Default — fast, secure, small key |
| RSA-4096 | RSA | 4096 bits | Legacy server compatibility |

**Recommendation:** Use Ed25519 unless the target server requires RSA.

---

## Service: `SshKeyManager`

Located at: `app/Services/CustomerInstallation/SshKeyManager.php`

### Methods

#### Generation
```php
generateEd25519(string $label, ?string $comment, ?int $targetId): SshKey
generateRsa4096(string $label, ?string $comment, ?int $targetId): SshKey
```
Generates a key pair using phpseclib3. Stores private key via CredentialVault.
Returns `SshKey` model with `status = 'pending'`.

#### Import
```php
import(string $label, string $privateKeyPem, ?string $comment, ?int $targetId): SshKey
```
Validates and imports an existing private key. Supports OpenSSH and PEM formats.

#### Installation
```php
installOnTarget(SshKey $key, CustomerRemoteServer $server): bool
```
Connects to the server using CURRENT credentials (password or existing key),
appends the public key to `~/.ssh/authorized_keys`. Idempotent.

#### Testing
```php
testKey(SshKey $key, CustomerRemoteServer $server): bool
```
Authenticates with the managed private key to verify installation.
Updates `last_used_at` on success.

#### Activation
```php
activateOnServer(SshKey $key, CustomerRemoteServer $server): void
```
Copies the private key to the server's `private_key_encrypted` field.
Switches `auth_method` to `'key'`. Updates `key_fingerprint`.

#### Rotation
```php
rotateKey(SshKey $oldKey, CustomerRemoteServer $server): SshKey
```
Generates new key → installs → activates → marks old key as `'rotated'`.
Optionally removes old public key from `authorized_keys`.

#### Revocation
```php
revokeKey(SshKey $key, ?CustomerRemoteServer $server): void
```
Marks key as `'revoked'`. Optionally removes from `authorized_keys`.

---

## Model: `SshKey`

Table: `ssh_keys`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| label | string(100) | User-defined name |
| key_type | enum | ed25519 \| rsa4096 |
| public_key | text | OpenSSH public key (safe to store) |
| private_key_encrypted | text | AES-256-GCM via CredentialVault |
| passphrase_encrypted | text? | Optional passphrase encryption |
| fingerprint | string(120) | SHA256:base64 format |
| deployment_target_id | bigint? | FK to customer_remote_servers |
| status | enum | pending \| active \| rotated \| revoked |
| installed_at | timestamp? | When installed on target |
| last_used_at | timestamp? | Last successful auth |
| rotated_at | timestamp? | When rotated |
| notes | text? | Admin notes |

---

## Fingerprint Format

Fingerprints are stored in OpenSSH SHA256 format:
```
SHA256:base64-encoded-sha256-of-key-blob
```

Example: `SHA256:uNiVztksCsDhcc0u9e8BujQXVUpKZIDTMczCvj3tD2s`

---

## Key Lifecycle

```
generate / import
      ↓
  status: pending
      ↓
  installOnTarget() — public key → authorized_keys
      ↓
  testKey() — verify auth
      ↓
  activateOnServer() — private key → server credential slot
      ↓
  status: active
      ↓
  rotateKey() — generates new → installs → activates
      ↓ (old key)
  status: rotated
      ↓
  revokeKey()
      ↓
  status: revoked
      ↓
  deleteKey() — permanent removal
```

---

## Fingerprint Pinning

Host fingerprints are managed by `SshDeploymentDriver::verifyFingerprint()`.
On first connection: fingerprint is not stored — connection allowed.
On subsequent connections: stored fingerprint must match. Mismatch = MITM alert + abort.
