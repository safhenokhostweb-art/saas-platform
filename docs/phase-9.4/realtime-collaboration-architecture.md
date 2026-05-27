# Realtime Collaboration Architecture — Phase 9.4

## Overview

The realtime collaboration layer provides live presence tracking, collaborative editing protection, live activity broadcasting, and a notification hub — all without requiring a running WebSocket server at launch (polling fallback is built-in).

## Service Inventory

| Service | Namespace | Responsibility |
|---|---|---|
| `RealtimePresenceService` | `CoreUtils\Realtime` | Online/offline state via Cache TTL |
| `UserPresenceTracker` | `CoreUtils\Realtime` | High-level presence API + index management |
| `CollaborativeEditingCoordinator` | `CoreUtils\Realtime` | Soft field locking, conflict prevention |
| `LiveActivityBroadcastService` | `CoreUtils\Realtime` | Tenant feed, user-targeted events |
| `RealtimeNotificationHub` | `CoreUtils\Realtime` | Push notifications, unread counts |
| `ConnectionHealthMonitor` | `CoreUtils\Realtime` | Transport detection, degradation mode |

## Transport Strategy

```
WebSocket available? → Use Laravel Echo + Pusher/Reverb
          ↓ No
Polling fallback → /api/realtime/feed every N seconds
```

`ConnectionHealthMonitor::detectTransport()` determines capability at runtime. All UI components check this and degrade gracefully.

## Presence Isolation

Presence keys follow the pattern:
```
presence:tenant:{tenantId}:user:{userId}      — individual entry (TTL 90s)
presence:tenant:{tenantId}:index              — list of active user IDs (TTL 3600s)
```

**Tenant safety:** All keys are namespaced by `tenantId`. No cross-tenant data leakage is possible by design.

## Collaborative Editing

Lock key pattern:
```
collab:lock:{tenantId}:{resource}:{resourceId}   — TTL 120s
```

A lock can only be released by the user who acquired it (or by a force-release from an admin). The `CollaborativeEditingCoordinator::refreshLock()` method extends TTL on heartbeat.

The `collaborative-lock-banner` core-UI component polls `/api/collab/lock` every 20 seconds and renders:
- Orange warning if locked by another user
- Indigo banner if locked by self (with "release" button)
- Viewer count when multiple users are viewing

## Core-UI Components

| Component | Purpose |
|---|---|
| `realtime-avatar-stack` | Stack of online-user avatars with status dots |
| `live-presence-dot` | Single animated presence indicator |
| `collaborative-lock-banner` | Editing lock state + viewer list |
| `live-feed-panel` | Polling-driven activity feed with type icons |
| `activity-stream` | Static timeline of historical activity items |

All components accept a `pollingInterval` prop (default 15–30s) and degrade to a static snapshot if the API is unavailable.

## Queue-Safe Broadcasting

`LiveActivityBroadcastService::broadcast()` dispatches `realtime.{eventType}` via Laravel's `Event::dispatch()`. Listeners may queue events for broadcasting without blocking the HTTP response.

## RTL & Accessibility

- All components use `dir="rtl"` compatible CSS (`rtl:space-x-reverse`, flex direction)
- Status labels use Persian text by default (`آنلاین`, `آفلاین`, `مشغول`, `دور`)
- All interactive elements have `role`, `aria-label`, and keyboard focus support
