# Windows Runtime Report — Phase M3.0

**Date:** 2026-05-31

---

## Visual Studio Build Tools — Verified

```
flutter doctor -v (Windows section):
[√] Visual Studio - develop Windows apps
    • Visual Studio Build Tools 2022 version 17.14.37314.3
    • MSVC at: C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Tools\MSVC\14.44.35207
    • Windows 10 SDK version 10.0.26100.0
```

MSVC compiler confirmed present: `cl.exe` in MSVC 14.44.35207.

---

## Windows Production Runtime Implementation

File: `mobile/lib/core/desktop/desktop_production_runtime.dart`

| Feature | Status | Implementation |
|---------|--------|----------------|
| System tray icon | ✅ Code complete | `system_tray 2.0.3` — `_tray.initSystemTray()` |
| Tray context menu | ✅ Code complete | Open / Launch at startup / Quit via `MenuSeparator()` |
| Unread badge (tray title) | ✅ Code complete | `_tray.setTitle('Support Client ($count)')` |
| Unread badge (window title) | ✅ Code complete | `windowManager.setTitle(title)` |
| Launch at startup | ✅ Code complete | `launch_at_startup 0.3.1` — `launchAtStartup.enable()` |
| Minimize to tray on close | ✅ Code complete | `windowManager.setPreventClose(true)` → `windowManager.hide()` |
| Native notifications | ✅ Code complete | Tray title update on Windows (flutter_local_notifications 17.x has no Windows toast) |
| Reconnect on window focus | ✅ Code complete | `onWindowFocus()` → `_triggerReconnect()` |
| Reconnect on network recovery | ✅ Code complete | `OfflineSyncService.stateStream` → `reconnect()` |

---

## Windows Update Runtime

File: `mobile/lib/core/ota/windows_update_runtime.dart`

| Feature | Status |
|---------|--------|
| Version check via OTA API | ✅ `checkAndDownload()` → `api.getOtaManifest()` |
| Package download (Dio streaming) | ✅ `_dio.download()` with `onReceiveProgress` |
| SHA-256 checksum verification | ✅ `crypto` package `sha256.convert()` |
| Corrupt download → delete | ✅ `File(path).delete()` on checksum failure |
| Restart prompt dialog | ✅ `showRestartPrompt(context, path)` |
| Silent install launch | ✅ `Process.start(path, ['/SILENT'])` + `exit(0)` |

---

## Build Status

**`flutter build windows --release`** → ❌ BLOCKED  
**Reason:** `windows-x64-release/windows-x64-flutter.zip` (~55 MB) cannot be downloaded due to network geographic restrictions.

**What is ready:**
- Project files: `windows/CMakeLists.txt`, `windows/runner/*.cpp` — ✅
- MSVC compiler: ✅ (14.44.35207)
- Windows SDK: ✅ (10.0.26100.0)
- Source code: ✅ (0 compile errors)
- All Windows-specific packages resolved: ✅

**Estimated CI/CD build time:** ~8-12 minutes on `windows-latest` runner.

---

## Post-Build Packaging (CI/CD)

```powershell
Compress-Archive -Path "build\windows\x64\runner\Release\*" `
    -DestinationPath "build\saas-client-windows.zip"
$hash = (Get-FileHash "build\saas-client-windows.zip" -Algorithm SHA256).Hash
"$hash  saas-client-windows.zip" | Out-File "saas-client-windows.zip.sha256"
```

---

## Asset Requirements (Pre-Launch)

| Asset | Path | Status |
|-------|------|--------|
| Tray icon (Windows ICO) | `assets/icons/tray_icon.ico` | ⚠️ Placeholder needed |
| Tray icon (Linux PNG) | `assets/icons/tray_icon.png` | ⚠️ Placeholder needed |
| Sound: incoming message | `assets/sounds/message_received.mp3` | ⚠️ Placeholder needed |
| Sound: assigned | `assets/sounds/assigned.mp3` | ⚠️ Placeholder needed |
| Sound: escalation | `assets/sounds/escalation.mp3` | ⚠️ Placeholder needed |

Asset directories created (`assets/icons/`, `assets/sounds/`, `assets/fonts/`, `assets/images/`). Production art to be added before release.
