# Realtime Runtime Governance — Phase 9.5-B

## Architecture

The realtime layer is **provider-agnostic** — it works with Reverb, Soketi, Pusher, or falls back to polling automatically.

```
WebSocketAbstractionLayer
  ├── publishToTenant(tenantId, event, data)
  ├── publishToUser(userId, tenantId, event, data)
  └── isWebSocketAvailable() → driver check

GracefulPollingFallback (always active)
  ├── queue(channel, event)     ← writes to cache
  ├── poll(channel, since)      ← reads events since timestamp
  └── pollForTenant(tenantId, since)

RealtimeRuntimeCoordinator
  ├── broadcastTicketUpdate()
  ├── broadcastFormActivity()
  ├── broadcastAiResult()
  ├── broadcastNotification()
  ├── broadcastGovernanceAlert()
  └── pollForTenant()           ← fallback for clients
```

## Transport Selection

```
config('broadcasting.default')
  ├── 'pusher'  → WebSocket (Pusher protocol)
  ├── 'reverb'  → WebSocket (Laravel Reverb)
  ├── 'soketi'  → WebSocket (Soketi)
  └── 'log'/'null' → Polling fallback (GracefulPollingFallback)
```

## Polling API

**GET** `/api/realtime/poll?tenant_id={id}&since={iso_timestamp}`

Returns queued events since the given timestamp. Alpine.js `aiRuntime` store's `startPolling()` method calls this automatically every 5 seconds.

## Channel Authorization

`TenantChannelManager` enforces tenant isolation:
- `tenant.{id}` — all members of tenant
- `user.{tenantId}.{userId}` — single user only
- `presence.{tenantId}.{context}` — presence channels for live cursors

Cross-tenant channel access is structurally impossible.

## Reconnection

`ReconnectionEngine` applies exponential backoff:
- 1 failure → 1s delay
- 2 failures → 2s delay
- n failures → min(2^n × 1000ms, 30s)
- After 10 failures → switch to polling permanently

## Heartbeat

`HeartbeatMonitor` pings connections every 30 seconds. Connections stale > 90 seconds are considered dead.

## Realtime Features

| Feature                     | Status         | Transport       |
|-----------------------------|----------------|-----------------|
| Live ticket updates          | Active         | WS / polling    |
| Typing indicators            | Active         | WS / polling    |
| AI streaming responses       | Ready (SSE*)   | SSE fallback    |
| Notification center          | Active         | polling         |
| Deployment alerts            | Active         | WS / polling    |
| Collaborative form editing   | Active         | WS / polling    |
| Governance alerts            | Active         | WS / polling    |

*SSE streaming: enable via `AI_FEATURE_STREAMING=true` (next phase)

## Commands

```bash
php artisan realtime:validate-runtime
php artisan realtime:validate-runtime --json
php artisan realtime:validate-runtime --strict
```
