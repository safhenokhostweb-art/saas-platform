# Mobile Device Governance — Phase M1.0

## Device Registry

`MobileDeviceRegistry` uses `DB::table('mobile_devices')` with upsert on `id` (UUID). Cache layer at `mobile:devices:{deviceId}` with 3600s TTL.

On registration:
1. UUID assigned if `device_id` not provided by client
2. Device info, OS version, app version, push token stored
3. Security validation score computed by `DeviceSecurityValidator`
4. Trust level starts at `provisional`

## Device Trust

`DeviceTrustManager` governs trust progression:

```
provisional → trusted    (on successful login)
provisional → suspicious (5+ failures in 600s)
trusted     → suspicious (5+ failures in 600s)
*           → revoked    (admin action or policy)
```

Suspicious devices are not blocked automatically — callers use `isTrusted()` to make enforcement decisions.

## Session Coordination

`MobileSessionCoordinator`:
- Token: `bin2hex(random_bytes(32))` — 64 hex chars
- Cache key: `mobile:sessions:{sessionId}`
- Per-user index: `mobile:sessions:user:{userId}` — ordered list
- Max 10 sessions: oldest removed on overflow
- Revoking a session also invalidates the Sanctum token

## Push Token Registry

`MobilePushTokenRegistry` uses `mobile_push_tokens` table with upsert on `device_id`. One push token per device. Failed delivery marks token invalid for rotation.

## Capability Resolution

`DeviceCapabilityResolver` evaluates per-device capabilities based on:
- Platform (iOS/Android)
- OS version
- App version

| Capability | iOS min | Android min | App min |
|---|---|---|---|
| push_notifications | 15.0 | 10 | 1.0.0 |
| biometric_auth | 15.0 | 10 | 1.0.0 |
| background_sync | 15.0 | 10 | 1.0.0 |
| camera | 15.0 | 10 | 1.0.0 |
| file_upload | 15.0 | 10 | 1.0.0 |
| voice_messages | 16.0 | 11 | 1.5.0 |
| video_calls | 16.0 | 11 | 2.0.0 |
| offline_mode | 16.0 | 12 | 1.5.0 |

## API Endpoints

```
POST   /api/mobile/devices                Register device
DELETE /api/mobile/devices/{deviceId}     Unregister device
GET    /api/mobile/devices/{deviceId}     Get device info
PUT    /api/mobile/devices/{deviceId}     Update device info
```
