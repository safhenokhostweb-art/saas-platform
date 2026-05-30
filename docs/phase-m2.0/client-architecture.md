# Phase M2.0 — Unified Support Client Architecture

## Overview

Phase M2.0 delivers a single Flutter codebase that targets:

| Platform | Target | Build Output |
|----------|--------|-------------|
| Android | API 29+ (Android 10) | APK + AAB |
| iOS | iOS 15+ | IPA |
| Windows | Windows 10+ | EXE (MSIX) |
| Linux | Ubuntu 20.04+ | tar.gz bundle |
| macOS | macOS 12+ | .app |

**Single codebase. Zero platform forks. All platforms first-class.**

---

## Directory Structure

```
mobile/
├── pubspec.yaml                        # Single dependency manifest
├── lib/
│   ├── main.dart                       # Entry point (desktop window + bootstrap)
│   ├── app/
│   │   ├── app.dart                    # MaterialApp.router + theme
│   │   ├── router.dart                 # GoRouter — Login / Conversations / Settings
│   │   └── theme.dart                 # AppTheme (delegates to ClientDesignTokenBridge)
│   ├── core/
│   │   ├── api/
│   │   │   ├── client_api_service.dart # Auth, Conversations, Notifications, Presence, OTA
│   │   │   └── models/                # Typed response models
│   │   ├── realtime/
│   │   │   ├── realtime_client_service.dart  # Reverb WebSocket (Pusher protocol)
│   │   │   └── realtime_event.dart           # Typed event wrapper
│   │   ├── offline/
│   │   │   ├── offline_cache_service.dart    # File-based message cache
│   │   │   └── offline_sync_service.dart     # Outbound queue drain on reconnect
│   │   ├── notifications/
│   │   │   └── notification_service.dart     # 4-level transport hierarchy
│   │   ├── security/
│   │   │   └── secure_token_service.dart     # Keychain/KeyStore wrapper
│   │   ├── ota/
│   │   │   └── ota_update_service.dart       # Stable/Beta/Canary channel client
│   │   ├── design/
│   │   │   └── client_design_token_bridge.dart  # design-tokens.css → Flutter
│   │   ├── diagnostics/
│   │   │   └── client_diagnostics_service.dart  # 7-dimension health check
│   │   └── desktop/
│   │       └── desktop_service.dart          # Tray, badge, startup, wake
│   ├── features/
│   │   ├── auth/
│   │   │   └── login_screen.dart             # Server URL + mobile + password
│   │   ├── conversations/
│   │   │   ├── conversation_list_screen.dart # Search, unread badge, FAB
│   │   │   └── conversation_view_screen.dart # Realtime messages, typing, read receipts
│   │   └── settings/
│   │       └── settings_screen.dart         # Theme, startup, server, update channel
│   └── shared/
│       ├── models/user_role.dart              # Roles from backend (never hardcoded)
│       ├── platform/platform_info.dart        # Platform detection (no #ifdef)
│       ├── providers/                         # Riverpod providers
│       └── widgets/                           # Shared UI widgets
```

---

## Authority Architecture Compliance

| Authority | Source | Client usage |
|-----------|--------|-------------|
| Business logic | PHP services (`ConversationService`, etc.) | Client calls `/api/mobile/*` — no duplication |
| UI tokens | `design-tokens.css` | `ClientDesignTokenBridge` maps `--ui-*` → Flutter |
| Realtime | Reverb WebSocket | `RealtimeClientService` (Pusher protocol) |
| OTA | `MobileUpdateManifestBuilder` | `OtaUpdateService` calls `/api/mobile/ota/manifest` |
| Notifications | `MobileNotificationCoordinator` | 4-level transport hierarchy |
| Security | `DeviceSecurityValidator`, `MobileSessionCoordinator` | Flutter reads tokens via `SecureTokenService` |

---

## Transport Priority Hierarchy

```
1. Reverb WebSocket (primary — all platforms)
   └── pusher_channels_flutter → private-conversation.{id}
2. Firebase Push (optional — mobile only, inject token via API)
   └── updatePushToken(token, 'fcm')
3. Local Notifications (desktop OS + mobile fallback)
   └── flutter_local_notifications
4. Offline Sync Recovery (network loss, app restart, device reboot)
   └── OfflineCacheService → OfflineSyncService
```

Firebase is never required. The system is fully operational on Reverb + Redis + Local Notifications alone.

---

## State Management

**Riverpod** — all providers are `Provider`, `StateNotifierProvider`, or `FutureProvider.autoDispose`. No `ChangeNotifier`, no `setState` outside local widget state.

| Provider | Type | Purpose |
|----------|------|---------|
| `secureTokenServiceProvider` | `Provider` | Token read/write |
| `clientApiServiceProvider` | `Provider` | HTTP API layer |
| `realtimeClientServiceProvider` | `Provider` | WebSocket |
| `authStateProvider` | `StateNotifierProvider` | Auth state + restore |
| `conversationsProvider` | `FutureProvider.autoDispose` | Conversation list |
| `otaUpdateServiceProvider` | `Provider` | OTA checks |
| `notificationServiceProvider` | `Provider` | Push transport |
| `desktopServiceProvider` | `Provider` | Tray/startup (desktop) |

---

## Platform Differences (handled in single codebase)

| Feature | Mobile | Desktop |
|---------|--------|---------|
| Window management | N/A | `window_manager` |
| System tray | N/A | `system_tray` |
| Launch at startup | N/A | `launch_at_startup` |
| Secure storage | Keychain (iOS) / KeyStore (Android) | OS credential manager |
| File cache path | `getApplicationDocumentsDirectory()` | Same — path_provider |
| Notifications | Local + FCM | Local (OS notifications) |

No `#ifdef` macros. `PlatformInfo.isDesktop` guards desktop-only features in Dart.

---

## Screens

### Login Screen
- Server URL input (saved to secure storage)
- Mobile number (regex `09xxxxxxxxx`)
- Password with toggle
- Error feedback
- Calls `AuthStateNotifier.login()` → `ClientApiService.login()` → Sanctum token

### Conversation List Screen
- Pull-to-refresh
- Search (client-side filter)
- Unread count badge (per item + AppBar)
- Role-aware: admins/agents see all tenant conversations
- FAB → create new support conversation
- Populates from `/api/mobile/conversations`

### Conversation View Screen
- Realtime messages (Reverb subscription)
- Typing indicator animation (3-dot)
- Read receipts (double-tick + blue)
- Attachment picker (file upload M2.1)
- Load more (cursor-based pagination)
- Message grouping by sender
- Connection status bar (yellow → orange → red based on WebSocket state)
