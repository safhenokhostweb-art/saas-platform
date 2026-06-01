# Assignment Flow — Phase M2.1

## Escalation Chain

```
Agent (Tier 1)
  └─ Senior Agent (Tier 2)
       └─ Supervisor (Tier 3)
            └─ Platform Admin (Tier 4)
```

## API Endpoints

| Method | URI | Action |
|--------|-----|--------|
| POST | `/api/mobile/conversations/{id}/assign` | Assign to agent |
| POST | `/api/mobile/conversations/{id}/reassign` | Reassign to different agent |
| POST | `/api/mobile/conversations/{id}/unassign` | Remove assignment |
| POST | `/api/mobile/conversations/{id}/escalate` | Escalate to higher tier |
| GET | `/api/mobile/conversations/{id}/assignment` | Current assignment |
| GET | `/api/mobile/conversations/{id}/assignment/history` | Full audit trail |

## Assignment Lifecycle

```
[Unassigned]
    │ POST /assign
    ▼
[Assigned → Agent]
    │ POST /reassign
    ├──────────────▶ [Assigned → Different Agent]
    │ POST /escalate target_tier=2
    ├──────────────▶ [Assigned → Senior Agent]
    │ POST /escalate target_tier=3
    ├──────────────▶ [Assigned → Supervisor]
    │ POST /escalate target_tier=4
    ├──────────────▶ [Assigned → Platform Admin]
    │ POST /unassign
    └──────────────▶ [Unassigned]
```

## Realtime Broadcast

Every assignment change broadcasts a `conversation.assignment.changed` event on:
- `private-conversation.{conversationId}` — all conversation participants notified
- `private-tenant.{tenantId}.inbox` — inbox view updated for all agents in that tenant

## Audit Log

All assignment actions are written to the `assignment_audit` table:

```json
{
  "conversation_id": "uuid",
  "action": "assign | reassign | unassign | escalate",
  "payload": {
    "assignee_id": "uuid",
    "assigned_by": "uuid",
    "previous": "uuid | null",
    "reason": "string"
  },
  "created_at": "ISO 8601"
}
```

Audit failures **never** block the assignment operation — they are logged and continue.

## Metadata Storage

Assignment state is stored in `conversations.metadata` JSON column:

```json
{
  "assigned_to": "user-uuid",
  "assigned_by": "user-uuid",
  "assigned_at": "2026-05-31T10:00:00Z",
  "escalated": true,
  "escalation_tier": 2,
  "escalation_chain": [
    {
      "from_tier": 1,
      "to_tier": 2,
      "escalated_by": "user-uuid",
      "escalated_at": "2026-05-31T10:30:00Z",
      "reason": "Customer is VIP"
    }
  ]
}
```
