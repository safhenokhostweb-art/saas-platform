# Authentication Center

**Phase:** PHASE-003D6  
**Status:** Complete  
**Date:** 2026-06-28

---

## Overview

The Authentication Center is the unified admin interface for managing all
authentication credentials used by the Deployment Engine.

**Route prefix:** `/admin/infrastructure/customer-deployment/auth-center`  
**Route name prefix:** `admin.infrastructure.customer-deployment.auth-center.*`  
**Controller:** `AuthenticationCenterController`

---

## Sections

### 1. Overview Dashboard (`/auth-center`)
- Security score (0-100) based on: key auth ratio, fingerprint coverage, test success rate
- Stats: active SSH keys, stored API tokens, password-only targets
- Warning banner if password-only targets exist
- Recent SSH keys table

### 2. SSH Key Management (`/auth-center/ssh-keys`)
- List all managed SSH keys with status, fingerprint, target, dates
- Generate new key (Ed25519 or RSA-4096)
- Import existing private key
- Per-key: view public key, copy, install on target, test, activate, rotate, revoke, delete

### 3. API Token Management (`/auth-center/api-tokens`)
- List all cPanel/DirectAdmin targets with token status
- Inline token store/rotate (masked display only)
- Delete token

### 4. Security Audit (`/auth-center/security-audit`)
- Automated audit findings: HIGH/MEDIUM/LOW severity
- Finding types: password-only auth, unverified fingerprint, failed test, stale test, aging key
- Full auth method breakdown table for all targets
- Direct links to remediation actions

### 5. Migration Wizard (`/auth-center/migration-wizard/{server}`)
- Step-by-step guide to migrate a server from password to SSH key auth
- Step 1: Select existing key or link to key generator
- Step 2: Install public key using current credentials
- Step 3: Test authentication with new key
- Step 4: Activate key (switches auth_method, copies private key to server slot)

---

## Route Table

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | /auth-center | .index | Overview |
| GET | /auth-center/ssh-keys | .ssh-keys.index | Key list |
| GET | /auth-center/ssh-keys/generate | .ssh-keys.generate | Generate form |
| POST | /auth-center/ssh-keys/generate | .ssh-keys.generate.store | Generate action |
| GET | /auth-center/ssh-keys/import | .ssh-keys.import | Import form |
| POST | /auth-center/ssh-keys/import | .ssh-keys.import.store | Import action |
| GET | /auth-center/ssh-keys/{key} | .ssh-keys.show | Key detail |
| POST | /auth-center/ssh-keys/{key}/install | .ssh-keys.install | Install on target (AJAX) |
| POST | /auth-center/ssh-keys/{key}/test | .ssh-keys.test | Test auth (AJAX) |
| POST | /auth-center/ssh-keys/{key}/activate | .ssh-keys.activate | Activate on server |
| POST | /auth-center/ssh-keys/{key}/rotate | .ssh-keys.rotate | Rotate key |
| POST | /auth-center/ssh-keys/{key}/revoke | .ssh-keys.revoke | Revoke key |
| DELETE | /auth-center/ssh-keys/{key} | .ssh-keys.delete | Delete key |
| GET | /auth-center/api-tokens | .api-tokens | Token list |
| POST | /auth-center/api-tokens/{server} | .api-tokens.store | Store token (AJAX) |
| POST | /auth-center/api-tokens/{server}/rotate | .api-tokens.rotate | Rotate token (AJAX) |
| DELETE | /auth-center/api-tokens/{server} | .api-tokens.delete | Delete token |
| GET | /auth-center/security-audit | .security-audit | Audit |
| GET | /auth-center/migration-wizard/{server} | .migration-wizard | Migration |

---

## Security Score Calculation

```
score = (keyAuthCount/total * 40) + (fingerprintCount/total * 30) + (testedOkCount/total * 30)
```

| Range | Interpretation |
|-------|---------------|
| 80-100 | Excellent — enterprise grade |
| 50-79 | Acceptable — improvements recommended |
| 0-49 | Poor — immediate action required |

---

## Backward Compatibility

Existing password-based deployments continue working without any changes.
The Authentication Center provides a migration path but never forces migration.
Password auth is still a supported method — it is flagged in the security audit
as HIGH severity but does not break deployment functionality.
