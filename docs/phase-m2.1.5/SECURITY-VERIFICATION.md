# SECURITY VERIFICATION — Phase M2.1.5

**Date:** 2026-05-31

---

## Governance Score

`php artisan mobile:validate-security` → **88/100**  
`php artisan mobile:validate-client-security` → **89/100**

---

## Authentication (Sanctum)

All authenticated routes verified via `route:list`:

```
All /api/mobile/* routes except auth/token and auth/refresh
require middleware: auth:sanctum
```

Evidence: `php artisan route:list --path=api/mobile` — 52 routes, all non-public routes behind Sanctum.

## Token Security (Flutter)

File: `mobile/lib/core/security/secure_token_service.dart`

- Stores `accessToken`, `sessionId`, `tenantId`, `updateChannel` in **flutter_secure_storage**
- On Android: uses Android Keystore via AES encryption
- On iOS: uses iOS Keychain
- On Windows: uses DPAPI (Data Protection API)
- Token refresh: `_AuthInterceptor` catches 401, calls `refreshToken()`, retries original request

Validator evidence: `DeviceTrustManager present` ✅, `Token refresh logic present` ✅

## Device Trust

`DeviceTrustManager` active with:
- Max 5 failed auth attempts before device suspension
- OS version policy enforced: iOS ≥ 15.0, Android ≥ 10

## Tenant Isolation

| Layer | Mechanism |
|-------|-----------|
| Database | All conversation queries use `forTenant($tenantId)` scope |
| Redis | Presence keys: `mobile:presence:t:{tenantId}:*` — namespace isolation |
| API | `tenantId` extracted from authenticated user's `site_id` |
| Inbox | `TenantInboxService` queries only `tenant_id`-scoped conversations |
| Attachments | `conversation_id` used as access control; tenant verified via conversation lookup |

## Attachment Access Protection

`AttachmentUploadService` validates:
- MIME type against whitelist (image, PDF, office, archives)
- File size ≤ 20 MB
- Tenant isolation via `conversation_id` → conversation's `tenant_id`

## Session Revocation

Route available: `POST /api/mobile/auth/logout-all` — revokes all sessions for user.  
Evidence: `mobile:validate-client-security` result: `Remote logout API: PASS 10/10`

## Security Patch Runtime

`MobileSecurityPatchRuntime` active — OTA patch delivery configured.  
Patch registry: 5 patches tracked, 0 critical.  
Evidence: `mobile:validate-production-readiness` → `Security: 100/100`

## Known Gaps

| Gap | Severity | Status |
|-----|----------|--------|
| Firebase FCM token not configured | Low | By design — Firebase is optional |
| Security patch registry check skipped in CLI | Low | Expected in non-HTTP context |
| Certificate pinning not implemented | Medium | Recommended for production but not blocking |

## Remaining Blocker: None for M2.1 scope

All security requirements for M2.1 production client are satisfied. Firebase push transport is intentionally optional and does not affect authentication or data security.
