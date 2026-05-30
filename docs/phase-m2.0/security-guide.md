# Phase M2.0 — Security Guide

## Token Storage

All sensitive values are stored using `flutter_secure_storage`:
- **iOS**: Keychain Services (accessibility: `kSecAttrAccessibleAfterFirstUnlock`)
- **Android**: Android EncryptedSharedPreferences (AES-256)
- **Windows**: Windows Credential Manager
- **Linux**: libsecret (GNOME Keyring / KWallet)
- **macOS**: Keychain Services

**Never stored in plain SharedPreferences or app files.**

### Keys stored securely

| Key | Content |
|-----|---------|
| `access_token` | Sanctum API token |
| `session_id` | Mobile session ID from `MobileSessionCoordinator` |
| `tenant_id` | Resolved tenant (site) ID |
| `device_id` | Stable device identifier |
| `base_url` | Server URL (not sensitive, but stored for consistency) |
| `reverb_app_key` | WebSocket app key |
| `update_channel` | stable / beta / canary |

## Token Refresh

The `_AuthInterceptor` in `ClientApiService` automatically:
1. Detects 401 responses
2. Calls `refreshToken()` (POST `/api/mobile/auth/refresh` with `session_id`)
3. Retries the original request with the new token
4. If refresh fails → propagates 401 → UI redirects to Login

## Device Registration

On first login, the device is registered via `MobileDeviceRegistry`. The `device_id` is:
- Generated once at first login
- Stored in secure storage
- Sent with every login request (`device_id`, `platform`, `app_version`)

## Session Revocation

- **Logout** (current device): POST `/api/mobile/auth/logout` with `session_id`
- **Logout All** (all devices): POST `/api/mobile/auth/logout-all` — revokes all sessions for the user across all devices

Admin users can revoke sessions from the platform admin panel via `DeviceSecurityValidator`.

## Certificate Validation

The Dio HTTP client uses the system certificate chain. **No custom `badCertificateCallback` is set.** TLS validation is enforced by default on all platforms.

Validated by: `mobile:validate-client-security` — checks for `badCertificateCallback` in `client_api_service.dart`.

## Sensitive Data in Telemetry

The `TelemetrySanitizer` on the server redacts:
- `authorization`, `token`, `license_key`, `password`, `secret`, `api_key`
- Bearer token pattern (`Bearer xxxx`)
- License key pattern (`XXXX-YYYY-ZZZZ-AAAA`)

The Flutter client never sends raw credentials to telemetry endpoints.

## Security Patch Governance

The `SecurityPatchRegistry` and `PatchGovernanceHealthService` manage server-side security patches. When a mandatory security update is issued:
1. The OTA manifest sets `mandatory: true`
2. The client enforces a blocking update prompt
3. Version enforcement rejects API calls from patched versions

## Jailbreak / Root Detection

Controlled server-side via `config('mobile.security.jailbreak_block')`. When enabled, the `DeviceSecurityValidator` can flag suspicious device states. Client-side detection is deferred to M2.1.

## Remote Logout

Platform admins can remotely revoke all sessions for a user via:
- Admin panel → Users → Revoke Devices
- `php artisan` (future M2.1: `mobile:revoke-user-sessions {user_id}`)

The next API call from the revoked device returns 401, which the `_AuthInterceptor` catches, fails refresh, and redirects to Login.

## Network Security

| Platform | Implementation |
|----------|---------------|
| Android | `network_security_config.xml` (cleartext blocked) |
| iOS | `NSAppTransportSecurity` (ATS enforced in Info.plist) |
| Windows/Linux/macOS | Dart TLS (system cert store) |

All WebSocket connections use `wss://` (TLS over port 8080/443).
