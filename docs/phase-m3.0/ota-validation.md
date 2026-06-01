# OTA Validation — Phase M3.0

**Date:** 2026-05-31

---

## Governance Result

`mobile:validate-production-client` — Updates dimension: **9/10**

```
✓ OTA manifest route exists     GET /api/mobile/ota/manifest
✓ Update channel 'stable' defined
✓ Update channel 'beta' defined
✓ Update channel 'canary' defined
✓ Found: mobile/lib/core/ota/windows_update_runtime.dart
✓ Found: mobile/lib/core/ota/android_update_runtime.dart
✓ SHA-256 checksum verification in Windows runtime
```

`mobile:validate-client-updates` → **81/100**

```
✓ OTA API routes          PASS 10/10  GET /api/mobile/ota/manifest
✓ Flutter OTA service     PASS 10/10  OtaUpdateService with channel support
✓ OTA signing             PASS 10/10  MOBILE_OTA_SIGNING=true
✓ Rollout percentages     PASS 10/10  canary=5%, beta=20%, stable=100%
✓ Min app version         PASS 10/10  MOBILE_MIN_APP_VERSION=1.0.0
```

---

## Channel Configuration (Verified)

| Channel | Target | Rollout | Defined in Code |
|---------|--------|---------|----------------|
| `stable` | All users | 100% | ✅ `UpdateChannel.stable` |
| `beta` | Opt-in testers | 20% | ✅ `UpdateChannel.beta` |
| `canary` | Developers | 5% | ✅ `UpdateChannel.canary` |

Channel stored in `SecureTokenService` under `SecureKey.updateChannel`. Periodic check every 6 hours + startup check (10s delay).

---

## API Endpoint Verified

```
GET /api/mobile/ota/manifest?version={current}&channel={channel}
→ MobileOtaController@manifest
→ Returns: {update_available, latest_version, mandatory, download_url, checksum, changelog}
```

Verified via `php artisan route:list --path=api/mobile/ota` — route exists and is registered.

---

## Windows Update Runtime

File: `mobile/lib/core/ota/windows_update_runtime.dart`

| Feature | Code Verified |
|---------|--------------|
| Download from platform OTA only | `_api.getOtaManifest()` → URL from manifest | ✅ |
| Streaming download | `_dio.download()` with `onReceiveProgress` | ✅ |
| SHA-256 checksum verification | `sha256.convert(bytes).toString()` | ✅ |
| Checksum failure → delete | `await File(path).delete()` | ✅ |
| Restart prompt dialog | `showRestartPrompt()` AlertDialog | ✅ |
| Silent installer launch | `Process.start(path, ['/SILENT'])` + `exit(0)` | ✅ |

## Android Update Runtime

File: `mobile/lib/core/ota/android_update_runtime.dart`

| Feature | Code Verified |
|---------|--------------|
| APK download to external storage | `getExternalStorageDirectory()` + `_dio.download()` | ✅ |
| SHA-256 verification before install | `_verifyChecksum()` | ✅ |
| Mandatory update flag | `showUpdatePrompt(mandatory: true)` non-dismissible | ✅ |
| `INSTALL_PACKAGES` permission | `Permission.requestInstallPackages.request()` | ✅ |
| Open APK for install | `OpenFile.open(apkPath, type: 'application/vnd.android.package-archive')` | ✅ |

---

## Version Enforcement

- `MobileVersionEnforcementService`: min version 1.0.0 enforced
- `MobileMandatoryUpdatePolicy`: mandatory vs optional update logic
- `MobileUpdateChannelManager`: channel rollout percentages managed backend-side

---

## Remaining Gap (1 point deduction)

The governance validator could not confirm that `MobileUpdateChannelManager` populates `download_url` in the canary channel manifest at runtime. This requires a live API call with `?channel=canary`. Implementing this check requires a running Laravel server with Reverb active.
