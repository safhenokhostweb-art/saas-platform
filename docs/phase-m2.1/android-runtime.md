# Android Production Runtime — Phase M2.1

## Components

| Component | File | Purpose |
|-----------|------|---------|
| `AndroidProductionRuntime` | `core/android/android_production_runtime.dart` | Main Android runtime |
| `AndroidUpdateRuntime` | `core/ota/android_update_runtime.dart` | APK download + install |

## Feature Matrix

| Feature | Status | Implementation |
|---------|--------|----------------|
| Foreground service notification | ✓ | Ongoing notification ID 9001 |
| Support Messages channel | ✓ | `support_messages` (Importance.max) |
| Assignment notifications channel | ✓ | `support_assignments` (Importance.high) |
| Background service channel | ✓ | `support_background` (Importance.low) |
| POST_NOTIFICATIONS permission | ✓ | Requested at runtime |
| SCHEDULE_EXACT_ALARM permission | ✓ | Requested at runtime |
| WebSocket heartbeat (30s) | ✓ | `Timer.periodic` |
| Reconnect on network recovery | ✓ | `OfflineSyncService.stateStream` |
| Notification tap → deep link | ✓ | Payload → `ConversationDeepLinkService` |
| APK download | ✓ | `AndroidUpdateRuntime` |
| SHA-256 checksum verification | ✓ | `_verifyChecksum()` |
| Install packages permission | ✓ | `Permission.requestInstallPackages` |
| APK install intent | ✓ | `OpenFile.open()` with MIME type |

## Notification Channels

```
support_messages    ─── Importance.max   + vibration + sound
support_assignments ─── Importance.high  + sound
support_background  ─── Importance.low   (no sound, no vibration, no badge)
```

## Foreground Service

The foreground service notification (ID 9001) keeps the process alive for the WebSocket connection while the app is in background. It uses the `support_background` channel with low importance so it does not disturb the user.

To stop the foreground service (e.g., on logout):
```dart
await runtime.stopForegroundService();
```

## Heartbeat

A 30-second timer calls `RealtimeClientService.sendHeartbeat()` to maintain the WebSocket connection while the app is backgrounded. This prevents the OS from dropping the connection.

## APK Update Flow

1. `checkAndDownload()` → fetches manifest from platform OTA endpoint
2. Streams APK to external storage with progress callback
3. SHA-256 checksum verified against manifest value
4. `showUpdatePrompt()` shows dialog (dismissible unless `mandatory: true`)
5. User confirms → `INSTALL_PACKAGES` permission requested → `OpenFile.open(apkPath)`

## Required Android Manifest Entries

Add to `android/app/src/main/AndroidManifest.xml`:

```xml
<!-- Deep linking -->
<intent-filter android:autoVerify="true">
  <action android:name="android.intent.action.VIEW" />
  <category android:name="android.intent.category.DEFAULT" />
  <category android:name="android.intent.category.BROWSABLE" />
  <data android:scheme="support" />
</intent-filter>

<!-- Permissions -->
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
<uses-permission android:name="android.permission.REQUEST_INSTALL_PACKAGES" />
<uses-permission android:name="android.permission.SCHEDULE_EXACT_ALARM" />
```
