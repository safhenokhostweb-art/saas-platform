# Offline Sync — Phase M2.1

## Design Principles

- **Zero message loss**: every outbound operation is persisted to local queue before sending
- **No polling when WebSocket available**: connectivity is event-driven
- **Server wins on conflict**: HTTP 409 responses cause the local copy to be silently discarded
- **Bounded retries**: messages retry max 10×, attachments retry max 5×

## Queue Architecture

```
User sends message/attachment
         │
         ├── Online? ──YES──▶ Try immediate delivery
         │                          │
         │                    Success ──▶ Done
         │                          │
         │                    Failure ──▶ Enqueue
         │
         └── Offline? ──────▶ Enqueue (always persisted first)

Connectivity restored
         │
         ▼
OfflineSyncService._sync()
         ├── _syncMessages()
         └── _syncAttachments()
```

## Message Queue Item

```json
{
  "conversation_id": "uuid",
  "content": "Hello",
  "type": "text",
  "queued_at": "2026-05-31T10:00:00Z",
  "_retry_count": 0
}
```

## Attachment Queue Item

```json
{
  "__type": "attachment",
  "conversation_id": "uuid",
  "file_path": "/data/user/0/.../cache/photo.jpg",
  "mime_type": "image/jpeg",
  "queued_at": "2026-05-31T10:00:00Z",
  "_retry_count": 0
}
```

## Conflict Resolution

| Scenario | Behavior |
|----------|----------|
| HTTP 200 | Success — remove from queue |
| HTTP 409 | Conflict — server already has message → **server wins**, discard local |
| HTTP 4xx (not 409) | Permanent failure — discard after max retries |
| HTTP 5xx / network error | Transient — re-enqueue, increment `_retry_count` |

## State Stream

`OfflineSyncService.stateStream` emits `SyncState` on every change:

```dart
SyncState {
  online:             bool,
  syncing:            bool,
  pendingMessages:    int,
  pendingAttachments: int,
  lastSyncAt:         DateTime?,
  lastError:          String?,
}
```

UI widgets subscribe to `stateStream` to show pending badges and sync indicators.
