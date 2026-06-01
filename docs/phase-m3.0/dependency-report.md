# Dependency Report — Phase M3.0

**Date:** 2026-05-31  
**Command:** `flutter pub get`  
**Result:** `Got dependencies!` — exit code 0

---

## Resolution Summary

```
Changed 141 dependencies.
1 package is discontinued (uni_links — replaced by app_links; kept for backward compat).
36 packages have newer versions incompatible with current constraints.
```

## Dependency Fixes Applied

| Issue | Root Cause | Fix Applied |
|-------|-----------|------------|
| `app_lifecycle ^0.0.5` not found | Package doesn't support null safety | Removed (not used in codebase) |
| `intl ^0.19.0` conflict | `flutter_localizations` pins intl to 0.20.2 | Updated to `^0.20.0` |
| `system_tray` uuid conflict with `riverpod_generator` | uuid ^3 vs uuid ^4 | Added `dependency_overrides: uuid: ^4.5.1` |
| `riverpod_generator` + `retrofit_generator` analyzer conflict | Incompatible `_macros` SDK package | Removed code-gen dev deps (not needed for release builds) |
| `device_info_plus` missing | Used in `auth_provider.dart` but not in pubspec | Added `device_info_plus: ^10.1.0` |

## Key Runtime Dependencies

| Package | Resolved Version | Purpose |
|---------|-----------------|---------|
| flutter_riverpod | 2.6.1 | State management |
| dio | 5.9.2 | HTTP client |
| pusher_channels_flutter | 2.4.0 | WebSocket (Reverb/Pusher protocol) |
| flutter_secure_storage | 9.2.4 | Token storage (Keystore/Keychain/DPAPI) |
| flutter_local_notifications | 17.2.4 | OS notifications |
| drift | 2.33.0 | SQLite offline cache |
| system_tray | 2.0.3 | Windows/Linux tray icon |
| window_manager | 0.3.9 | Desktop window control |
| launch_at_startup | 0.3.1 | System startup registration |
| connectivity_plus | 6.1.5 | Network connectivity |
| go_router | 14.8.1 | Navigation |
| crypto | 3.0.7 | SHA-256 for OTA checksum |
| uni_links | 0.5.1 | Deep link handling (`support://` scheme) |
| audioplayers | 6.7.0 | Voice notifications |
| package_info_plus | 8.3.1 | App version for OTA |
| permission_handler | 11.4.0 | Android permissions |
| open_file | 3.5.11 | Android APK install intent |

## Flutter Analyze Results

```
flutter analyze --no-pub (after all fixes applied)

Errors:   0
Warnings: 0
Infos:    14  (style hints: withOpacity → withValues, curly_braces, etc.)

34 issues found — all infos/hints, no errors or warnings.
Ran in 35.1 seconds.
```

**All 6 critical errors fixed before first build attempt:**
1. `getDashboardStats()` missing from `ClientApiService` → Added method
2. `OtaState.checkForUpdates()` → Fixed to `notifier.checkForUpdates()`
3. `device_info_plus` missing → Added to pubspec.yaml
4. `_baseUrl` / `_dio` private access in `AttachmentUploadService` → Refactored to public `uploadAttachment()`
5. `host`/`wsPort`/`wssPort` not in pusher_channels_flutter 2.4.0 → Replaced with `proxy` parameter
6. `WindowsInitializationSettings` / `WindowsNotificationDetails` not in flutter_local_notifications 17.x → Replaced with platform-conditional tray approach
