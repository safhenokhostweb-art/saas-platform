# Interaction Recovery Architecture — Phase 9.4

## InteractionRecoveryEngine

**Namespace:** `SaaSPlatform\CoreUtils\InteractionRecovery\InteractionRecoveryEngine`

A self-healing system that detects and recovers from common frontend and session-state failures without requiring a page reload.

## Failure Types Handled

| Type | Detection | Recovery |
|---|---|---|
| `alpine_state` | `$data()` throws or component is in invalid state | `reload_component` — re-init Alpine data |
| `modal_deadlock` | `body.overflow = 'hidden'` but no modal present | Reset scroll lock |
| `stale_optimistic_ui` | `data-optimistic-pending` element > 15s old | Remove optimistic attributes |
| `duplicate_listeners` | Multiple elements with same `data-listener-id` | Remove duplicates |
| `stale_session` | Auth token expired mid-session | Refresh token |
| `race_condition` | Concurrent mutations detected | Queue + retry |

## Client-Side Recovery Script

`InteractionRecoveryEngine::getClientRecoveryScript()` generates a self-executing JavaScript module that:

1. **Runs on DOMContentLoaded** to scan Alpine component states
2. **Runs on 30-second interval** to check:
   - Modal scroll lock deadlocks
   - Stale optimistic UI markers (`data-optimistic-pending` + `data-optimistic-since`)
   - Duplicate event listener IDs
3. **Reports failures** to `/api/interaction-recovery/report` for server-side telemetry

## Server-Side Recovery Log

Failures are logged to cache:
```
interaction:recovery:log:{tenantId}   — array of recovery entries (200 max, 7-day TTL)
```

Each entry:
```json
{
  "id": "rf_abc123",
  "type": "modal_deadlock",
  "context": {},
  "reported_at": "2026-05-27T...",
  "recovered": true,
  "recovery_action": "reset_scroll_lock"
}
```

## Custom Strategy Registration

```php
$engine->registerStrategy('my_failure_type', function(string $tenantId, array $context): string {
    // Perform recovery
    return 'custom_recovery_action';
});
```

## Integration Points

- **Telemetry**: Failure events dispatched through `LiveActivityBroadcastService`
- **Observability**: Recovery stats exposed via `getStats(tenantId)` for dashboards
- **Diagnostics**: `FrontendRuntimeDiagnosticsCommand` can trigger recovery reports
