# Phase M2.1 — Production Support Client Architecture

## Overview

Phase M2.1 transforms the M2.0 runtime scaffold into a production-ready support client for Android and Windows, preserving the existing M1.x and M2.0 infrastructure as single sources of truth.

## Layer Map

```
┌─────────────────────────────────────────────────────────────────┐
│                     CLIENT APPLICATIONS                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   Android    │  │   Windows    │  │  Future (iOS │          │
│  │   (Flutter)  │  │   (Flutter)  │  │  Linux macOS)│          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         └─────────────────┼─────────────────┘                  │
│              Shared Flutter Core (M1.x + M2.1)                 │
└─────────────────────────────┬───────────────────────────────────┘
                              │  HTTPS / WebSocket (Reverb)
┌─────────────────────────────▼───────────────────────────────────┐
│                    LARAVEL BACKEND                               │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  API Gateway  /api/mobile/*  (Sanctum auth)            │    │
│  └───────────────────────────┬─────────────────────────────┘    │
│                              │                                  │
│  ┌───────────┐  ┌───────────┐│  ┌───────────┐  ┌───────────┐  │
│  │ TenantInbox│  │ ConvHub  ││  │Assignment │  │ Presence  │  │
│  │ Service   │  │ Service  ││  │ Service   │  │ Service   │  │
│  └───────────┘  └───────────┘│  └───────────┘  └───────────┘  │
│  ┌───────────┐  ┌───────────┐│  ┌───────────┐  ┌───────────┐  │
│  │ReadReceipt│  │  Typing   ││  │   OTA     │  │Attachment │  │
│  │ Service  │  │ Service  ││  │  Runtime  │  │ Upload    │  │
│  └───────────┘  └───────────┘│  └───────────┘  └───────────┘  │
│                              │                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │      M1.x Infrastructure (unchanged, reused)           │    │
│  │  ConversationService · PresenceRuntimeManager          │    │
│  │  TypingIndicatorRuntime · MobileNotificationCoordinator │    │
│  │  ConversationChannelRegistry · MobileDeviceRegistry    │    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Reverb WebSocket Server       Redis Cache               │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## New Services — M2.1

| Service | Layer | Purpose |
|---------|-------|---------|
| `TenantInboxService` | Backend PHP | Multi-tenant inbox with SLA counters |
| `ConversationHubService` | Backend PHP | Unified inbox for all agent roles |
| `ConversationAssignmentService` | Backend PHP | Assign/escalate with audit log |
| `ConversationReadReceiptService` | Backend PHP | sent/delivered/read via Reverb |
| `ConversationTypingService` | Backend PHP | Typing indicators wrapping TypingIndicatorRuntime |
| `ConversationPresenceService` | Backend PHP | Conversation presence, delegates to PresenceRuntimeManager |
| `AttachmentUploadService` | Flutter | Multipart upload with MIME/size validation |
| `ClientNotificationRuntime` | Flutter | Priority: Reverb → LocalOS → OfflineSync |
| `VoiceNotificationRuntime` | Flutter | Per-type sounds, user-configurable |
| `ConversationDeepLinkService` | Flutter | support://conversation/{id} and support://tenant/{id} |
| `OfflineSyncService` (completed) | Flutter | Messages + attachments + retry + conflict resolution |
| `DesktopProductionRuntime` | Flutter | Tray, badge, startup, reconnect on wake |
| `AndroidProductionRuntime` | Flutter | Foreground service, notification channels, bg sync |
| `OtaUpdateService` (completed) | Flutter | stable/beta/canary channel management |
| `WindowsUpdateRuntime` | Flutter | Download, SHA-256 verify, restart prompt |
| `AndroidUpdateRuntime` | Flutter | APK download, SHA-256 verify, install intent |
| `SupportDashboardScreen` | Flutter UI | 6-widget realtime dashboard |

## Transport Authority

Reverb + Redis remain the **primary and only transport authority**. The priority chain:

```
1. Reverb WebSocket (primary — always preferred)
2. Local OS notification (fallback display layer)
3. Offline sync recovery (last resort — fires on reconnect)
```

Firebase is **optional** and **never required** for any feature to function.

## Tenant Hierarchy

```
Platform Admin
└─ Customer Sites (Tenants)
   └─ Customer Admins
      └─ End Users
```

All services are tenant-aware and use the `tenant_id` from the authenticated session.

## Critical Constraints Respected

- No parallel presence system created
- No duplicate API endpoints
- Existing M1.x services extended, not replaced
- All storage is self-hosted (Redis + MySQL)
- Firebase remains strictly optional
