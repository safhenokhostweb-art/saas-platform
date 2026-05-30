# Phase M2.0 — OTA Update Guide

## Architecture

The OTA update system is entirely **server-authoritative**. The client polls the server for an update manifest on:

1. App launch
2. App foreground (from background, desktop wake)
3. Periodic background check (future M2.1)

No third-party OTA service (CodePush, Shorebird, etc.) is used. Updates are distributed as platform-native binaries.

## Update Channels

| Channel | Rollout | Audience | Config key |
|---------|---------|---------|------------|
| `stable` | 100% | All users | `mobile.ota.stable_rollout_pct` |
| `beta` | 20% | Opted-in users | `mobile.ota.beta_rollout_pct` |
| `canary` | 5% | Internal / QA | `mobile.ota.canary_rollout_pct` |

Users choose their channel in **Settings → Update Channel**.

## Server-Side: MobileUpdateManifestBuilder

The server's `MobileUpdateManifestBuilder::build($channel, $currentVersion, $platform)` returns:

```json
{
  "latest_version": "1.2.0",
  "current_version": "1.0.0",
  "update_available": true,
  "mandatory": false,
  "channel": "stable",
  "download_url": "https://platform.example.com/downloads/client-v1.2.0.apk",
  "changelog": "Bug fixes and performance improvements",
  "min_os_version": "10"
}
```

## Client-Side: OtaUpdateService

```dart
final manifest = await otaUpdateService.checkForUpdates();
if (manifest?.updateAvailable == true) {
  if (manifest!.mandatory) {
    // Show blocking mandatory update dialog
  } else {
    // Show dismissable update banner
  }
}
```

## Mandatory Updates

When `mandatory: true`, the client:
1. Shows a non-dismissable dialog
2. Blocks all navigation except the update action
3. Opens `download_url` via `url_launcher`

Mandatory updates are triggered by `MobileMandatoryUpdatePolicy` on the server.

## Version Enforcement

The `MobileVersionEnforcementService` rejects API calls from clients older than `MOBILE_MIN_APP_VERSION`. The 401 response body includes `requires_update: true` so the client can surface the update prompt.

## Signing

When `MOBILE_OTA_SIGNING=true`, the manifest is HMAC-signed by the server. The client verifies the signature before trusting the manifest. (Signature verification: M2.1)

## Adding a New Release

1. Build the binary for each platform (see `build-guide.md`)
2. Upload to a file server or CDN accessible at a stable URL
3. Update `MobileUpdateManifestBuilder` to return the new version and URL for the appropriate channel
4. Set `mandatory: true` in the manifest if this is a security release
5. Run: `php artisan mobile:validate-client-updates --json`
