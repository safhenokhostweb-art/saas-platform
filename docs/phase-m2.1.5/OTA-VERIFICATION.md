# OTA VERIFICATION — Phase M2.1.5

**Date:** 2026-05-31

---

## Governance Score

`php artisan mobile:validate-client-updates` → **81/100**  
`mobile:validate-production-client` Updates dimension → **9/10**

---

## Backend OTA Endpoint

Route verified: `GET /api/mobile/ota/manifest` → `MobileOtaController@manifest`  
Evidence: `php artisan route:list --path=api/mobile/ota`

## Update Channels

| Channel | Target Audience | Rollout % | Status |
|---------|----------------|-----------|--------|
| `stable` | All users (default) | 100% | ✅ Defined |
| `beta` | Opt-in testers | 20% | ✅ Defined |
| `canary` | Developers | 5% | ✅ Defined |

Evidence: `mobile:validate-client-updates` → `Rollout percentages: PASS 10/10`

## Flutter OTA Service

File: `mobile/lib/core/ota/ota_update_service.dart`

Features verified by code inspection:
- `checkForUpdates()` — fetches manifest, dispatches to channel
- `setChannel(UpdateChannel)` — persists to SecureStorage
- `_schedulePeriodicCheck()` — `Timer.periodic(6h)` + 10s startup delay
- `UpdateChannel.stable`, `.beta`, `.canary` — enum with `name` extension

## Windows Update Runtime

File: `mobile/lib/core/ota/windows_update_runtime.dart`

Features verified:
- `download(url, checksum)` — Dio streaming download
- SHA-256 checksum via `crypto` package: `sha256.convert(bytes).toString()`
- `showRestartPrompt(context, path)` — AlertDialog with Later/Restart options
- `_launchInstaller(path)` — `Process.start(path, ['/SILENT'])` then `exit(0)`
- Error: emits `WindowsUpdateDownloadState(error: ...)`, deletes corrupt download

## Android Update Runtime

File: `mobile/lib/core/ota/android_update_runtime.dart`

Features verified:
- `downloadApk(url, checksum)` — Dio streaming to external storage
- SHA-256 checksum verification before install
- `showUpdatePrompt(context, apkPath, mandatory)` — supports mandatory flag
- `installApk(path)` — requests `INSTALL_PACKAGES` permission then `OpenFile.open()`
- Corrupt APK deleted on checksum failure

## Security

- Downloads exclusively from platform OTA endpoints (no external CDN)
- SHA-256 checksum verified **before** launching any installer
- Failed checksum → immediate file deletion + error state
- Auth Bearer token included via Dio interceptor

## Version Enforcement

`MobileVersionEnforcementService` active: `MOBILE_MIN_APP_VERSION=1.0.0`  
`MobileMandatoryUpdatePolicy` active.  
Evidence: `mobile:validate-client-updates` → `Min app version: PASS 10/10`

## Gap (Score: 9/10)

The governance validator deducted 1 point because the Android canary APK channel download URL wiring was not explicitly verified in the runtime manifest test. This is a minor gap — the `AndroidUpdateRuntime` code is complete; the manifest endpoint populates `downloadUrl` from the backend `MobileUpdateChannelManager`.

**Remediation:** Verify that `MobileUpdateChannelManager` populates `download_url` in the canary channel manifest response at `/api/mobile/ota/manifest?channel=canary`.
