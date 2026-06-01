# REALTIME VERIFICATION — Phase M2.1.5

**Date:** 2026-05-31

---

## Reverb Configuration (Verified)

| Parameter | Value | Status |
|-----------|-------|--------|
| REVERB_APP_KEY | `dbybffmkmvvfy9ckxnlg` | ✅ Configured |
| REVERB_APP_ID | `102708` | ✅ Configured |
| REVERB_HOST | `localhost` | ✅ Configured |
| REVERB_PORT | `8080` | ✅ Configured |
| REVERB_SCHEME | `http` | ✅ Configured |
| BROADCAST_DRIVER | `reverb` | ✅ Verified in .env |

## Governance Validation Score

`php artisan mobile:validate-realtime` → **100/100**

```
✓  Reverb Configuration: Reverb key configured
✓  Redis Connection: Redis is reachable
✓  Presence Service: PresenceRuntimeManager active
✓  Channel Registry: ConversationChannelRegistry active
✓  Socket Authorization: RuntimeSocketAuthorizationService active
Transport: websocket
```

## Runtime Services (Verified via Autoload)

| Service | Class | Registered |
|---------|-------|-----------|
| TypingIndicatorRuntime | `MobileRuntime\Realtime\TypingIndicatorRuntime` | ✅ Singleton |
| ConversationChannelRegistry | `MobileRuntime\Realtime\ConversationChannelRegistry` | ✅ Singleton |
| MobileRealtimeCoordinator | `MobileRuntime\Realtime\MobileRealtimeCoordinator` | ✅ Singleton |
| RuntimeSocketAuthorizationService | `MobileRuntime\Realtime\RuntimeSocketAuthorizationService` | ✅ Singleton |
| PresenceRuntimeManager | `MobileRuntime\Presence\PresenceRuntimeManager` | ✅ Singleton |
| ConversationTypingService | `MobileRuntime\Typing\ConversationTypingService` | ✅ Singleton (M2.1) |
| ConversationPresenceService | `MobileRuntime\Presence\ConversationPresenceService` | ✅ Singleton (M2.1) |
| ConversationReadReceiptService | `MobileRuntime\ReadReceipts\ConversationReadReceiptService` | ✅ Singleton (M2.1) |

## Broadcast Events (M2.1)

| Event | Channel | Event Name |
|-------|---------|-----------|
| `ConversationAssignmentChanged` | `private-conversation.{id}`, `private-tenant.{tenantId}.inbox` | `conversation.assignment.changed` |
| `ReadReceiptEvent` | `private-conversation.{id}` | `message.receipt` |
| `TypingEvent` | `private-conversation.{id}` | `conversation.typing` |
| `ConversationPresenceEvent` | `presence-conversation.{id}` | `conversation.presence.updated` |

All events implement `ShouldBroadcastNow` — no queue delay.

## Redis Key Patterns (Live Tested)

```
mobile:presence:t:{tenantId}:u:{userId}:d:{deviceId}  TTL=120s   ✅ VERIFIED
mobile:presence:tenant_index:{tenantId}               TTL=7200s  ✅ VERIFIED
mobile:typing:{conversationId}:{userId}               TTL=5s     ✅ VERIFIED
mobile:typing:conv_idx:{conversationId}               TTL=60s    ✅ VERIFIED
mobile:receipt:{messageId}:{recipientId}              TTL=3600s  ✅ VERIFIED (by code inspection)
mobile:conv_presence:{conversationId}                 TTL=300s   ✅ VERIFIED (by code inspection)
```

## Read Receipts — State Machine

```
Message created → state: sent
Client receives via WS → ConversationReadReceiptService.markDelivered() → state: delivered
User opens message → ConversationReadReceiptService.markRead() → state: read
```

Broadcast: `message.receipt` event on `private-conversation.{id}` — **no polling**.

## Typing Indicators — Lifecycle

```
User starts typing → ConversationTypingService.startTyping()
  → TypingIndicatorRuntime.startTyping() (Redis TTL=5s)
  → broadcast TypingEvent{typing: true}

User stops typing → ConversationTypingService.stopTyping()
  → TypingIndicatorRuntime.stopTyping() (Redis DEL)
  → broadcast TypingEvent{typing: false}

Auto-cleanup → cleanExpired() prunes stale entries → broadcasts stop for expired users
```

## Limitation

Reverb WebSocket server (`php artisan reverb:start`) must be running for live WS connections. Reverb process start is not within scope of this verification (requires a running HTTP server environment). Configuration is verified; live WS handshake requires browser/app client.
