# Communication Runtime Architecture — Phase M1.0

## Design Intent

The communication runtime is built as a **reusable enterprise communication layer** — it is deliberately NOT coupled to the tickets module. Conversations can be linked to tickets via metadata but exist independently.

## Entities

| Entity | Table | Type |
|---|---|---|
| `Conversation` | `mobile_conversations` | Eloquent (HasUuids) |
| `ConversationParticipant` | `mobile_conversation_participants` | Eloquent |
| `ConversationMessage` | `mobile_conversation_messages` | Eloquent (HasUuids) |
| `ConversationAttachment` | `mobile_conversation_attachments` | Eloquent (HasUuids) |
| `ConversationPresence` | *(Cache-backed)* | Value Object |
| `ConversationTypingState` | *(Cache-backed)* | Value Object |

## Conversation Types

| Type | Use Case |
|---|---|
| `direct` | 1-on-1 between any two users |
| `group` | Multi-user group chat |
| `support` | Customer ↔ support agent |
| `ticket_linked` | Associated with a ticket (metadata.ticket_id) |

## Participant User Types

`platform_admin`, `tenant_admin`, `support_agent`, `end_user`, `ai_assistant`

## Participant Roles

| Role | Permissions |
|---|---|
| `owner` | Can archive/close, manage participants |
| `participant` | Can read/write messages |
| `observer` | Read-only (audit, compliance) |

## API

```
POST   /api/mobile/conversations                     Create conversation
GET    /api/mobile/conversations/{id}/messages       Cursor-paginated messages
POST   /api/mobile/conversations/{id}/messages       Send message
POST   /api/mobile/conversations/{id}/read           Mark read
POST   /api/mobile/conversations/{id}/archive        Archive
GET    /api/mobile/conversations/{conversationId}/presence  Presence summary
POST   /api/mobile/conversations/{conversationId}/typing/start
POST   /api/mobile/conversations/{conversationId}/typing/stop
```

## Real-Time Events

Every sent message broadcasts to `presence-conversation.{conversationId}` on Reverb. Typing events broadcast with 5-second TTL. Presence heartbeats extend device TTL (120s).

## Cursor Pagination

`GET /api/mobile/conversations/{id}/messages?cursor={messageId}` returns messages before the cursor, newest-first from DB, reversed for display (chronological order in response).

## Ticket Linkage

`ConversationService::linkToTicket($conversationId, $ticketId)` uses `JSON_SET` to write `ticket_id` into the conversation `metadata` column — no foreign key constraint to the tickets table.
