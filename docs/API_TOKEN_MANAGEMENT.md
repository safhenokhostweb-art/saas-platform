# API Token Management

**Phase:** PHASE-003D6  
**Status:** Complete  
**Date:** 2026-06-28

---

## Overview

API tokens are used for panel-based deployment targets: cPanel and DirectAdmin.
They are stored encrypted via CredentialVault and never exposed in plaintext in
logs, reports, or API responses.

---

## Service: `ApiTokenManager`

Located at: `app/Services/CustomerInstallation/ApiTokenManager.php`

### Methods

```php
store(CustomerRemoteServer $server, string $token): void
```
Encrypts and stores a new API token. Sets `api_token_created_at`. 
Switches `auth_method` to `'api_token'`.

```php
replace(CustomerRemoteServer $server, string $newToken): void
rotate(CustomerRemoteServer $server, string $newToken): void
```
Both replace the encrypted token and reset `api_token_created_at`.
`rotate()` is semantically identical to `replace()` — use either.

```php
delete(CustomerRemoteServer $server): void
```
Clears `api_token_encrypted`, `api_token_created_at`, `api_token_last_used_at`.

```php
hasToken(CustomerRemoteServer $server): bool
```
Returns true if `api_token_encrypted` is set.

```php
recordUsage(CustomerRemoteServer $server): void
```
Updates `api_token_last_used_at`. Called by driver implementations.

```php
maskedPreview(CustomerRemoteServer $server): string
```
Returns `••••••••••••xxxx` (last 4 chars visible). Never returns plaintext.

```php
tokenHealth(CustomerRemoteServer $server): string
```
Returns: `'ok'` | `'stale'` (>30 days) | `'unused'` | `'missing'`

---

## Storage Schema

Added to `customer_remote_servers` via migration `2026_06_27_310000`:
- `api_token_encrypted` — encrypted token (text)

Added via migration `2026_06_28_100001`:
- `api_token_created_at` — timestamp
- `api_token_last_used_at` — timestamp

---

## Driver Integration

When a cPanel or DirectAdmin driver calls the API:
1. Call `$this->vault->decrypt($server->api_token_encrypted)` to get plaintext
2. Use token in HTTP Authorization header
3. Call `$this->apiTokenManager->recordUsage($server)` after successful call

Token format by driver:
- **cPanel:** `WHM_USERNAME:TOKEN` (UAPI Authorization header)
- **DirectAdmin:** `login:token` (Authorization header)

---

## Security Policy

1. Token is encrypted at rest (AES-256-GCM, `INSTALLER_MASTER_KEY`)
2. `maskedPreview()` is the only display-safe method — never use `decrypt()` in views
3. Token is excluded from `$hidden` property — access via `api_token_encrypted` column
4. Rotation is manual — no automatic expiry (token TTL is panel-controlled)
5. `tokenHealth()` provides admin visibility into stale/unused tokens
