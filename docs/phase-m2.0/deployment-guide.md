# Phase M2.0 — Deployment Guide

## Server-Side Requirements

Before distributing the client, the following must be live on the platform server:

### Required API Routes (`/api/mobile/*`)

| Route | Controller | Purpose |
|-------|-----------|---------|
| POST `/api/mobile/auth/token` | `MobileAuthController` | Login + session create |
| POST `/api/mobile/auth/refresh` | `MobileAuthController` | Token refresh |
| POST `/api/mobile/auth/logout` | `MobileAuthController` | Session revoke |
| POST `/api/mobile/auth/logout-all` | `MobileAuthController` | All sessions revoke |
| GET `/api/mobile/conversations` | `MobileConversationController` | Conversation list |
| POST `/api/mobile/conversations` | `MobileConversationController` | Create conversation |
| GET `/api/mobile/conversations/{id}` | `MobileConversationController` | Conversation detail |
| GET `/api/mobile/conversations/{id}/messages` | `MobileConversationController` | Messages (cursor-paginated) |
| POST `/api/mobile/conversations/{id}/messages` | `MobileConversationController` | Send message |
| POST `/api/mobile/conversations/{id}/read` | `MobileConversationController` | Mark read |
| GET `/api/mobile/presence/status` | `MobilePresenceController` | User presence |
| POST `/api/mobile/presence/update` | `MobilePresenceController` | Update my presence |
| GET `/api/mobile/notifications` | `MobileNotificationController` | Notification list |
| POST `/api/mobile/notifications/register-push` | `MobileNotificationController` | Register FCM/APNs token |
| GET `/api/mobile/ota/manifest` | `MobileOtaController` | OTA update check |
| GET `/api/mobile/runtime/manifest` | `MobileRuntimeManifestController` | Feature flags + capabilities |
| POST `/api/mobile/realtime/socket-auth` | `MobileRealtimeController` | Pusher channel auth |

### Environment Variables

```env
# Reverb WebSocket
REVERB_APP_ID=saas-platform
REVERB_APP_KEY=your-reverb-key
REVERB_APP_SECRET=your-reverb-secret
REVERB_HOST=platform.yourdomain.com
REVERB_PORT=8080

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Mobile
MOBILE_MIN_APP_VERSION=1.0.0
MOBILE_OTA_SIGNING=true
MOBILE_FEATURE_CHAT=true
MOBILE_FEATURE_TICKETS=true
MOBILE_FEATURE_REALTIME=true

# Optional Firebase
MOBILE_FCM_SERVER_KEY=     # leave blank if not using Firebase
MOBILE_FCM_PROJECT_ID=     # leave blank if not using Firebase
```

## Client Distribution

### Android

Distribute via:
- Google Play Store (AAB)
- Direct APK download (enterprise / internal)

The client auto-checks OTA at launch and on each foreground event.

### Windows

Distribute via:
- MSIX package (Microsoft Store or direct install)
- ZIP archive (internal distribution)

Auto-starts on login if enabled in Settings → "Launch at startup."

### Linux

Distribute via:
- tar.gz bundle (extract and run `./saas_platform_client`)
- AppImage (future M2.1)
- Flatpak / Snap (future M2.1)

### macOS

Distribute via:
- Signed `.dmg` (notarized via Apple)
- ZIP archive (internal)

### iOS

Distribute via:
- App Store (IPA)
- TestFlight (beta)

Requires Apple Developer account. Build must be signed with valid provisioning profile.

## Client Configuration

The client prompts the user for the **Server URL** on first login. This is saved to secure storage and pre-filled on subsequent launches.

The Server URL format: `https://platform.yourdomain.com`

The client will fetch the runtime manifest (`/api/mobile/runtime/manifest`) after login to load:
- Feature flags
- Tenant capabilities
- OTA channel assignment
- Design token overrides

## Validation After Deployment

Run governance validation on the server:

```bash
php artisan mobile:validate-client-runtime --json
php artisan mobile:validate-client-security --json
php artisan mobile:validate-client-updates --json
php artisan mobile:validate-client-governance --json
```

All 4 commands should report ≥ 85/100 before distributing to end users.
