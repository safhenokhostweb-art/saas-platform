# Phase M2.0 — Release Guide

## Release Checklist

Before every release:

### 1. Governance validation (must all pass ≥ 85%)
```bash
php artisan mobile:validate-client-runtime --strict
php artisan mobile:validate-client-security --strict
php artisan mobile:validate-client-updates --strict
php artisan mobile:validate-client-governance --strict
```

### 2. Flutter analysis clean
```bash
cd mobile && flutter analyze --fatal-infos
```

### 3. Tests pass
```bash
cd mobile && flutter test
```

### 4. Version bump

In `mobile/pubspec.yaml`:
```yaml
version: 1.2.0+3    # format: semver+build_number
```

### 5. Changelog update

Add entry to release notes (stored in OTA manifest for in-app display).

### 6. Build all platforms
```bash
# Use CI workflow or local:
flutter build apk --release --split-per-abi
flutter build appbundle --release
flutter build windows --release
flutter build linux --release
flutter build macos --release   # macOS runner only
```

### 7. Upload binaries to distribution server

Upload APK/AAB/EXE/tar.gz to file server. Update download URLs in `MobileUpdateManifestBuilder`.

### 8. Set update channel

For staged rollout:
- Push to `canary` first (5% of users)
- Promote to `beta` after 48h if metrics clean
- Promote to `stable` after 7 days

### 9. Verify OTA manifest
```bash
curl -H "Authorization: Bearer {token}" \
  https://platform.yourdomain.com/api/mobile/ota/manifest?version=1.0.0&channel=stable
```

Response should include `update_available: true` and correct `download_url`.

## Versioning Strategy

`MAJOR.MINOR.PATCH+BUILD`:
- **MAJOR**: Breaking API or protocol change
- **MINOR**: New features (new screen, new capability)
- **PATCH**: Bug fixes, security patches
- **BUILD**: Monotonically increasing build number (used by stores)

## Emergency / Security Release

For critical security patches:
1. Set `MOBILE_MIN_APP_VERSION` to the new version (blocks older clients)
2. Set `mandatory: true` in the OTA manifest
3. Deploy immediately to all channels simultaneously
4. Run: `php artisan mobile:validate-client-security --strict`

## Rollback

If a release introduces regressions:
1. Update `MobileUpdateManifestBuilder` to point back to the previous version for the affected channel
2. Reduce rollout percentage to 0% (`canary_rollout_pct = 0`)
3. Do **not** set `mandatory: true` on a rollback — let users stay on the broken version while you prepare a hotfix

## GitHub Actions Artifacts

After CI runs, artifacts are available under:
- **GitHub Actions → Artifacts → android-apks** (APK)
- **GitHub Actions → Artifacts → android-aab** (AAB)
- **GitHub Actions → Artifacts → windows-exe** (ZIP)
- **GitHub Actions → Artifacts → linux-appimage** (tar.gz)
- **GitHub Actions → Artifacts → macos-build** (ZIP)

Retention: 30 days. Download and upload to your distribution server.
