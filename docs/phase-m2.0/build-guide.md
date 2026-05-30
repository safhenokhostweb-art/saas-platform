# Phase M2.0 — Build Guide

## Prerequisites

| Tool | Version | Purpose |
|------|---------|---------|
| Flutter | ≥ 3.22.0 | Cross-platform SDK |
| Dart | ≥ 3.3.0 | Language runtime |
| Java | 17 (Temurin) | Android builds |
| Android SDK | API 33+ | Android targets |
| Xcode | 15+ | iOS + macOS (macOS only) |
| CMake | 3.24+ | Windows/Linux native |
| Ninja | Latest | Linux build |

## Quick Start

```bash
cd mobile
flutter pub get
flutter run                     # Run on connected device / simulator
```

## Android

```bash
# Debug APK
flutter build apk --debug

# Release APK (split by ABI)
flutter build apk --release --split-per-abi

# Release AAB (Play Store)
flutter build appbundle --release
```

Output: `build/app/outputs/flutter-apk/*.apk`

### Signing

Create `mobile/android/key.properties`:
```
storePassword=<password>
keyPassword=<password>
keyAlias=upload
storeFile=../keystore.jks
```

Reference in `android/app/build.gradle` before releasing to Play Store.

## iOS

```bash
flutter build ios --release --no-codesign
# or with code signing:
flutter build ipa
```

Requires macOS + Xcode + valid Apple Developer account.

## Windows

```bash
flutter config --enable-windows-desktop
flutter build windows --release
```

Output: `build/windows/x64/runner/Release/`

Package with MSIX Packaging Tool or zip:
```powershell
Compress-Archive -Path "build\windows\x64\runner\Release\*" -DestinationPath "saas-client-windows.zip"
```

## Linux

```bash
# Install system dependencies (Ubuntu/Debian)
sudo apt-get install -y ninja-build libgtk-3-dev libblkid-dev \
  libsecret-1-dev libayatana-appindicator3-dev

flutter config --enable-linux-desktop
flutter build linux --release
```

Output: `build/linux/x64/release/bundle/`

Package as AppImage or tar.gz:
```bash
tar -czf saas-client-linux.tar.gz -C build/linux/x64/release bundle/
```

## macOS

```bash
flutter config --enable-macos-desktop
flutter build macos --release
```

Output: `build/macos/Build/Products/Release/saas_platform_client.app`

## CI/CD (GitHub Actions)

The workflow `.github/workflows/mobile-build.yml` builds:

- **Android APK + AAB** on every push to `main`/`develop`
- **Windows EXE** on every push to `main`/`develop`
- **Linux AppImage** on every push to `main`/`develop`
- **macOS** on `main` branch only

Artifacts are uploaded to GitHub Actions and retained for 30 days.

## Code Generation

Some packages require code generation:

```bash
cd mobile
dart run build_runner build --delete-conflicting-outputs
```

Run this after changing annotated files (`@riverpod`, Drift database, Retrofit endpoints).

## Asset Setup

Before building, ensure these asset directories exist:
```
mobile/assets/icons/tray_icon.ico     (Windows tray)
mobile/assets/icons/tray_icon.png     (Linux/macOS tray)
mobile/assets/fonts/Vazir.ttf
mobile/assets/fonts/Vazir-Bold.ttf
mobile/assets/fonts/Vazir-Light.ttf
```

Placeholder fonts and icons should be added before first release.
