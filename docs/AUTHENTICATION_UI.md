# Authentication UI Architecture

**Status:** HOTFIX-003_COMPLETE  
**Date:** 2026-06-28  
**Scope:** Deployment Target create/edit forms — driver-aware, method-aware authentication UI

---

## Overview

Authentication is now **Driver-aware** and **Method-aware**. Every driver declares its supported authentication methods, and the UI dynamically adapts when the driver changes.

---

## Driver → Authentication Method Matrix

| Driver | API Token | SSH Key | Username+Password | Notes |
|--------|-----------|---------|-------------------|-------|
| SSH / VPS | — | ✔ (Ed25519 / RSA) | ✔ | Key recommended |
| cPanel | ✔ (Recommended) | — | ✔ | Token preferred |
| DirectAdmin | ✔ (Recommended) | — | ✔ | Token preferred |
| SFTP | — | — | ✔ (only) | Fixed method |
| Docker | — | ✔ (Ed25519 / RSA) | ✔ | Key recommended |

---

## Create Form — Authentication Sections

### SSH / Docker: 3-option selector

```
○ کلید SSH موجود          → private_key textarea
● تولید کلید جدید Ed25519  → AJAX wizard (6 steps)
○ نام کاربری + رمز عبور   → password field
```

**auth_method submitted:**
- `existing_key` or `generate_key` → `key`
- `password` → `password`

### cPanel / DirectAdmin: 2-option selector

```
● API Token (پیشنهادی)     → api_token field
○ نام کاربری + رمز عبور   → password field
```

**auth_method submitted:**
- API Token → `api_token`
- Password → `password`

### SFTP: Fixed

No selector — always `password` / `sftp_password`.

---

## SSH Key Generation Wizard (6 Steps)

| Step | Description | Implementation |
|------|-------------|----------------|
| 1 | Click "ایجاد کلید Ed25519" | AJAX POST to `/servers/generate-ssh-key` |
| 2 | Display Public Key | Monospace terminal box, green text |
| 3 | Copy button | `navigator.clipboard.writeText()` with fallback |
| 4 | Guide where to paste | `~/.ssh/authorized_keys` instructions |
| 5 | Test Connection | Available after form save via edit page |
| 6 | Activate | Form submission saves encrypted private key |

**Backend:** `CustomerRemoteServerAdminController::generateSshKeyPair()`  
Uses `phpseclib3\Crypt\EC::createKey('Ed25519')`.

**Security:** Private key never leaves the browser until form submission where it is AES-256-GCM encrypted by `CredentialVault`.

---

## SSH Education Panel (Part 4)

Expandable `<details>`-style collapsible section shown when SSH or Docker driver is selected.

**Content:**
- Private Key: what it is, where it lives, that it's encrypted
- Public Key: what it is, that it's safe to share, where to put it
- ASCII connection diagram: Platform (private key) → signs → Server verifies via public key

---

## Edit Form

### Auth Method Change
- SSH/Docker: can switch between `key` and `password` — shows appropriate input
- SSH Key input: sub-mode toggle between "paste existing" and "generate new"
- cPanel/DA: can switch between `api_token` and `password`

### Current Auth Badge
Shows current `auth_method` with color-coded badge + key fingerprint if available.

---

## Validation (Part 7)

Controller `credentialRules()` enforces:

| Driver | Rule |
|--------|------|
| SSH + `auth_method=key` | `private_key` required |
| SSH + `auth_method=password` | `password` required |
| cPanel + `auth_method=api_token` | `api_token` required |
| cPanel + `auth_method=password` | `password` required |
| DirectAdmin + `auth_method=api_token` | `api_token` required |
| DirectAdmin + `auth_method=password` | `password` required |
| SFTP | `password` required |

Invalid combinations prevented by form UI (fields hide/show based on selection) and backend `required_if` rules.

---

## Security (Part 8)

- `$hidden` on `CustomerRemoteServer` model for all encrypted fields
- Passwords/tokens: rendered as `type="password"` on input, never displayed after save
- Private keys: only fingerprint shown after save
- `api_token_encrypted`, `password_encrypted`, `private_key_encrypted` — never in Blade output
- `CredentialVault::encrypt()` with AES-256-GCM, 12-byte random nonce per encryption

---

## Routes Added

```
POST /admin/infrastructure/customer-deployment/servers/generate-ssh-key
→ CustomerRemoteServerAdminController::generateSshKeyPair()
→ name: admin.infrastructure.customer-deployment.servers.generate-ssh-key
```

---

## Files Modified

| File | Change |
|------|--------|
| `CustomerRemoteServerAdminController.php` | Added `generateSshKeyPair()`, updated `credentialRules()` for cPanel/DA password |
| `routes/web.php` | Added `generate-ssh-key` route |
| `servers/create.blade.php` | Full rewrite — driver-aware auth UI, SSH wizard, education panel |
| `servers/edit.blade.php` | Added auth method selector, migration wizard, inline key generation |
