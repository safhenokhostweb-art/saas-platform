# Notification Flow — Phase M2.1

## Priority Chain (ClientNotificationRuntime)

```
Event occurs (new message / assignment / escalation)
        │
        ▼
┌───────────────────────────────────────┐
│  Priority 1: Reverb WebSocket         │ ──── WebSocket connected?
│  RealtimeClientService.events stream  │         YES → show immediately
└────────────────┬──────────────────────┘         NO  → fall through
                 │ WebSocket unavailable
                 ▼
┌───────────────────────────────────────┐
│  Priority 2: Local OS notification    │ ──── Always shown as visual layer
│  FlutterLocalNotificationsPlugin      │      (even when WS is active)
│  - Android: high-priority channel     │
│  - Windows: Windows toast             │
└────────────────┬──────────────────────┘
                 │ App was offline
                 ▼
┌───────────────────────────────────────┐
│  Priority 3: Offline sync recovery    │
│  OfflineSyncService.syncNow()         │ ──── On reconnect: fetch missed
│  API: GET /api/notifications          │      notifications and show
└───────────────────────────────────────┘
```

## VoiceNotificationRuntime

Plays audio alongside each notification:

| Event Type | Sound Asset |
|-----------|-------------|
| `message` | `sounds/message_received.mp3` |
| `assigned` | `sounds/assigned.mp3` |
| `escalation` | `sounds/escalation.mp3` |

Sounds are **per-user configurable** via `VoiceSoundPreferences`.

## Android Notification Channels

| Channel ID | Name | Importance |
|-----------|------|-----------|
| `support_messages` | Support Messages | Max |
| `support_assignments` | Conversation Assignments | High |
| `support_background` | Background Service | Low |

## Notification Tap → Deep Link

```
User taps notification
        │
        ▼
ClientNotificationRuntime._handleTap()
        │ payload: "{conversationId}|{tenantId}|{type}"
        ▼
ConversationDeepLinkService.handleUri("support://conversation/{id}")
        │
        ▼
Navigator routes to ConversationViewScreen
```

## Firebase (Optional)

Firebase remains **disabled by default**. To enable:
1. Add `firebase_messaging` to `pubspec.yaml`
2. Call `api.updatePushToken(token, 'fcm')` on startup
3. The backend `PushDeliveryRuntime` handles FCM dispatch when device is offline

The entire notification system functions at 100% without Firebase.
