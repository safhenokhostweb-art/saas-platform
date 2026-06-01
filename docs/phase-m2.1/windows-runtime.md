# Windows Production Runtime — Phase M2.1

## Components

| Component | File | Purpose |
|-----------|------|---------|
| `DesktopProductionRuntime` | `core/desktop/desktop_production_runtime.dart` | Main Windows runtime |
| `WindowsUpdateRuntime` | `core/ota/windows_update_runtime.dart` | Auto-update download + install |

## Feature Matrix

| Feature | Status | Implementation |
|---------|--------|----------------|
| System tray icon | ✓ | `system_tray` package |
| Tray context menu | ✓ | Open / Launch at startup / Quit |
| Unread badge (tray title) | ✓ | `updateUnreadBadge(count)` |
| Unread badge (window title) | ✓ | `windowManager.setTitle()` |
| Launch at startup | ✓ | `launch_at_startup` package |
| Minimize to tray on close | ✓ | `WindowCloseBehavior.minimize` |
| Native Windows notifications | ✓ | `WindowsNotificationDetails` |
| Reconnect on window focus | ✓ | `onWindowFocus()` → reconnect |
| Reconnect on network recovery | ✓ | `OfflineSyncService.stateStream` |
| Auto-update download | ✓ | `WindowsUpdateRuntime` |
| SHA-256 checksum verification | ✓ | `_verifyChecksum()` |
| Restart prompt | ✓ | `showRestartPrompt()` |
| Silent installer launch | ✓ | `Process.start(path, ['/SILENT'])` |

## Tray Icon Assets

Place assets at:
- `mobile/assets/icons/tray_icon.ico` — Windows (ICO format, 16×16 + 32×32)
- `mobile/assets/icons/tray_icon.png` — Linux/macOS (PNG, 32×32)

## Window Close Behavior

```dart
// Minimize to tray (default)
runtime.closeBehavior = WindowCloseBehavior.minimize;

// Quit on close
runtime.closeBehavior = WindowCloseBehavior.quit;
```

## Reconnect Logic

The runtime listens to two signals to trigger a WebSocket reconnect:
1. **Window focus** (`onWindowFocus`) — fires when user switches back to the app after sleep/lock
2. **Network recovery** (`OfflineSyncService.stateStream`) — fires when connectivity is restored

Both signals debounce for 2 seconds before calling `RealtimeClientService.reconnect()`.

## Startup Registration

```dart
await runtime.setLaunchAtStartup(true); // registered with OS startup
await runtime.isLaunchAtStartup();      // check current state
```

The executable path is resolved automatically via `Platform.resolvedExecutable`.
