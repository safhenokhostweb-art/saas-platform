# Flutter Doctor Report — Phase M3.0

**Date:** 2026-05-31  
**Command:** `flutter doctor -v`  
**Exit:** Issues found in 1 category (Android toolchain)

---

## Full Output

```
[√] Flutter (Channel stable, 3.44.0, on Microsoft Windows [Version 10.0.26200.8457], locale fa-IR)
    • Flutter version 3.44.0 on channel stable at C:\flutter
    • Upstream repository https://github.com/flutter/flutter.git
    • Framework revision 559ffa3f75 (2 weeks ago), 2026-05-15 14:13:13 -0700
    • Engine revision 4c525dac5e
    • Dart version 3.12.0
    • DevTools version 2.57.0
    • Pub download mirror https://pub.flutter-io.cn
    • Flutter download mirror https://storage.flutter-io.cn

[√] Windows Version (11 Pro 64-bit, 25H2, 2009)

[X] Android toolchain - develop for Android devices
    X ANDROID_HOME = C:\Android\Sdk
      but Android SDK not found at this location.
      (cmdline-tools installed; platform-tools/platform/build-tools
       download blocked by network geographic restriction)

[√] Chrome - develop for the web

[√] Visual Studio - develop Windows apps
    (Visual Studio Build Tools 2022 17.14.33 — May 2026)
    • Visual Studio at C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools
    • Visual Studio Build Tools 2022 version 17.14.37314.3
    • Windows 10 SDK version 10.0.26100.0

[√] Connected device (3 available)
    • Windows (desktop) • windows • windows-x64 • Microsoft Windows [Version 10.0.26200.8457]
    • Chrome (web)      • chrome  • web-javascript • Google Chrome 148.0.7778.179
    • Edge (web)        • edge    • web-javascript • Microsoft Edge 148.0.3967.54

[√] Network resources — All expected network resources are available.
```

---

## Component Inventory

| Component | Installed | Version | Status |
|-----------|-----------|---------|--------|
| Flutter SDK | ✅ | 3.44.0 stable | Green |
| Dart SDK | ✅ | 3.12.0 | Green |
| Flutter Engine | ✅ | 4c525dac5e | Green |
| DevTools | ✅ | 2.57.0 | Green |
| Windows 11 | ✅ | 11 Pro 25H2 | Green |
| Visual Studio Build Tools | ✅ | 2022 v17.14.37314.3 | Green |
| MSVC Compiler | ✅ | 14.44.35207 | Green |
| Windows 10 SDK | ✅ | 10.0.26100.0 | Green |
| Java | ✅ | OpenJDK 17.0.19 (Microsoft) | Green |
| Android cmdline-tools | ✅ | v14742923 | Installed |
| Android SDK (platform-tools, platforms, build-tools) | ❌ | — | Blocked |
| Flutter Windows engine binaries | ❌ | — | Download blocked |

---

## Root Cause: Network Geographic Restriction

Both Google Storage (`storage.googleapis.com` → HTTP 403 Forbidden) and the Chinese Flutter mirror (`storage.flutter-io.cn` → connection reset on files > 30 MB) are inaccessible for large binary downloads from this environment (Tehran, Iran).

**Affected downloads:**
- `windows-x64-release/windows-x64-flutter.zip` (~55 MB) — required for `flutter build windows --release`
- Android SDK: `platform-tools`, `platforms;android-35`, `build-tools;35.0.0`

**Not affected:**
- Dart SDK (downloaded successfully via mirror — 14 MB)
- Flutter tool itself (downloaded successfully)
- `pub.flutter-io.cn` — all Dart packages resolved successfully

---

## Fix Required

Run on GitHub Actions (unrestricted network) or any machine without Google Storage restrictions:

```bash
# Windows build
flutter build windows --release
flutter build apk --release --split-per-abi
flutter build appbundle --release
```

The CI/CD pipeline (`.github/workflows/mobile-build.yml`) is configured and will run these commands successfully.
