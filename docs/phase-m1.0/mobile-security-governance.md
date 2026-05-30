# Mobile Security Governance — Phase M1.0

## Security Layers

### 1. Device Security Validation

Every device registration passes through `DeviceSecurityValidator`:

- **OS version** enforcement: iOS >= 15.0, Android >= 10
- **App version** enforcement: >= 1.0.0
- **Jailbreak/root detection**: platform inspects `security_flags` in `device_info`
- **Score** (0–100): deducted for each policy violation
- Returns `{valid, score, issues[]}` — caller decides whether to block

### 2. Device Trust Lifecycle

Managed by `DeviceTrustManager`:

| Level | Condition |
|---|---|
| `provisional` | Newly registered device |
| `trusted` | Successful login recorded |
| `suspicious` | ≥5 failed logins in 600s window |
| `revoked` | Manually revoked by admin or automatic policy |

Failed login attempts are tracked in Cache with 600s TTL. On success, provisional devices are auto-promoted to trusted.

### 3. Session Governance

`MobileSessionCoordinator` issues 64-character hex random tokens (bin2hex(random_bytes(32))):

- Sessions live in Cache, TTL 24h
- Max 10 sessions per user — oldest is automatically revoked when limit exceeded
- Session key: `mobile:sessions:{sessionId}`

### 4. WebSocket Authorization

`RuntimeSocketAuthorizationService` issues HMAC-SHA256 signed tokens for WebSocket channels:
- Token = base64(HMAC-SHA256(payload, app.key))
- TTL: 300 seconds
- Conversation channel access requires caller to be in participant list

### 5. OTA Security Patches

`MobileSecurityPatchCoordinator` + `MobileSecurityPatchRuntime`:
- Critical patches can be marked requiring immediate installation
- `isSecurityCompliant()` checks all critical patches are applied per tenant
- OTA manifest includes `security_patches` array for the mobile app

### 6. API Rate Limiting

Mobile API has its own rate limiter (`mobile-api`): 120 req/min per user or IP.

## Artisan Commands

```bash
php artisan mobile:validate-security                   # Full security audit
php artisan mobile:validate-security --tenant=123      # Tenant-specific audit
php artisan mobile:validate-security --strict          # Warnings become failures
php artisan mobile:validate-security --json            # JSON output for CI
```

## Environment Variables

```env
MOBILE_FCM_SERVER_KEY=         # FCM transport key (not an authority key)
MOBILE_MIN_IOS_VERSION=15.0
MOBILE_MIN_ANDROID_VERSION=10
MOBILE_DEVICE_TRUST_WINDOW=600
MOBILE_MAX_FAIL_ATTEMPTS=5
MOBILE_JAILBREAK_BLOCK=true
MOBILE_SOCKET_AUTH_TTL=300
```
