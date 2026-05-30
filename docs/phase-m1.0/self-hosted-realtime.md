# Self-Hosted Realtime Runtime — Phase M1.0

## Transport

The realtime layer uses **Laravel Reverb** — a self-hosted WebSocket server bundled with Laravel. No third-party WebSocket SaaS (Pusher, Ably) is required or authorized.

Reverb config is read from `config('broadcasting.connections.reverb.*')`.

## Services

| Service | Responsibility |
|---|---|
| `MobileRealtimeCoordinator` | Provides connection config and preferred transport to mobile clients |
| `ConversationChannelRegistry` | Tracks which conversation channels are open per tenant (2h TTL) |
| `TypingIndicatorRuntime` | Manages 5-second TTL typing state, broadcasts to Reverb |
| `RealtimeConnectionTelemetry` | Tracks active connections, errors, latency per tenant |
| `RuntimeSocketAuthorizationService` | Issues HMAC-SHA256 tokens for WebSocket channel auth (5-min TTL) |
| `PresenceRuntimeManager` | Online/away/busy/offline state with 120s TTL per device |

## Channel Naming Convention

| Pattern | Channel Type | Usage |
|---|---|---|
| `presence-tenant.{tenantId}` | Presence | Tenant-wide online presence |
| `private-mobile.user.{userId}` | Private | Per-user notifications |
| `presence-conversation.{conversationId}` | Presence | Conversation participant presence |
| `conversation.{conversationId}` | Regular | Message broadcasts |

## Authentication Flow

1. Mobile client calls `POST /api/mobile/realtime/auth` with `channel_name` and `socket_id`
2. `MobileRealtimeController` → `RuntimeSocketAuthorizationService::authorizeChannel()`
3. Service validates channel type, checks participant membership for conversation channels
4. Returns Pusher-compatible auth response: `"key:HMAC_token"` (or with `channel_data` for presence)

## Graceful Degradation

If Reverb is not configured, `MobileRealtimeCoordinator::getPreferredTransport()` returns `'polling'` instead of `'websocket'`. Clients fall back to HTTP polling automatically.

## Artisan Commands

```bash
php artisan mobile:validate-realtime          # Validate transport config
php artisan mobile:validate-realtime --json   # JSON output for CI
php artisan mobile:runtime-status             # Live runtime status
```
