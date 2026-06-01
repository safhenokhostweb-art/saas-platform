# Security Validation — Phase M3.0

**Date:** 2026-05-31

---

## Governance Results (Actual Execution)

### `mobile:validate-client-security` → 89/100

```
✓ Token refresh interceptor   PASS 10/10  Automatic 401 → refresh → retry in _AuthInterceptor
✓ Certificate validation       PASS 10/10  No SSL bypass — system cert chain enforced
✓ Remote logout API            PASS 10/10  POST /api/mobile/auth/logout-all
~ Security patch registry      WARN  7/10  Registry check skipped (non-HTTP context)
✓ Sensitive data telemetry     PASS 10/10  Telemetry quotas enforced per tenant
```

### `mobile:validate-security` → 88/100

```
✓ DeviceTrustManager active — max 5 failed attempts before suspension
✓ OS version policy: iOS >= 15.0, Android >= 10
✓ MobileSecurityPatchRuntime active — OTA patch delivery configured
```

### `mobile:validate-production-client` — Security dimension → 10/10

```
✓ SecureTokenService present
✓ DeviceTrustManager present
✓ Token refresh logic present
✓ Conversation routes behind auth:sanctum
```

---

## Verified Security Features

### Token Storage

| Platform | Mechanism | Status |
|----------|-----------|--------|
| Android | Android Keystore (AES encryption) | ✅ `flutter_secure_storage 9.2.4` |
| iOS | iOS Keychain | ✅ |
| Windows | DPAPI (Data Protection API) | ✅ |

Tokens stored: `accessToken`, `sessionId`, `tenantId`, `deviceId`, `updateChannel`

### Authentication

- All `/api/mobile/*` routes (except auth/token, auth/refresh) behind `auth:sanctum`
- Token refresh: `_AuthInterceptor` catches 401 → calls `refreshToken()` → retries original request
- Session revocation: `POST /api/mobile/auth/logout-all` (revokes all sessions for user)
- Verified via `route:list` — 52 routes, 48 behind Sanctum

### Tenant Isolation

| Layer | Mechanism | Verified |
|-------|-----------|---------|
| DB queries | `forTenant($tenantId)` scope | ✅ |
| Redis | `mobile:presence:t:{tenantId}:*` namespace | ✅ Live tested |
| API | `tenantId` from authenticated `site_id` | ✅ |
| Inbox | TenantInboxService scoped to `tenant_id` | ✅ |
| Attachments | `conversation_id` → verified tenant via conversation | ✅ |

### Device Trust

- `DeviceTrustManager`: max 5 failed attempts before suspension
- `DeviceCapabilityResolver`: per-device capability resolution
- `DeviceSecurityValidator`: security posture check before access

### Attachment Security

MIME whitelist (enforced at upload):
```
image/jpeg, image/png, image/gif, image/webp, image/heic
application/pdf
application/msword, application/vnd.openxmlformats-officedocument.*
application/zip, application/x-tar, application/gzip
```
Max file size: **20 MB** — enforced client-side + server-side

---

## Known Gaps (Non-Blocking)

| Gap | Severity | Reason Not Fixed |
|-----|----------|-----------------|
| Certificate pinning | Medium | Not required for M3.0 scope |
| Firebase FCM token | Low | Firebase is optional by design |
| Security patch registry in CLI | Low | Expected non-HTTP behavior |
