# Runtime Policy Engine — Phase 8.4

## Overview

The policy engine provides declarative, deterministic governance rules. Policies define conditions and actions in data; no hardcoded logic per failure type.

## Policy Schema

```php
[
    'name'               => 'my_policy',         // unique identifier
    'version'            => '1.0.0',
    'enabled'            => true,
    'trigger'            => 'queue_anomaly',      // when to evaluate
    'condition_operator' => 'AND',               // AND | OR
    'conditions'         => [
        ['metric' => 'queue.failed', 'operator' => '>=', 'threshold' => 10],
    ],
    'actions'            => [
        ['type' => 'deployment_freeze'],
        ['type' => 'telemetry', 'event' => 'queue.critical'],
    ],
    'cooldown_seconds'   => 300,
]
```

## Supported Condition Operators

`>`, `>=`, `<`, `<=`, `=`, `!=`, `in`, `not_in`, `contains`

Metric paths use dot notation: `queue.failed`, `health.score`, `memory.usage_pct`

## Supported Action Types

| Type | Effect |
|------|--------|
| `state_transition` | Move platform to target state |
| `deployment_freeze` | Set `governance.deployment.frozen` cache key |
| `degradation` | Set tenant degradation level |
| `recovery` | Trigger recovery strategy |
| `queue_throttle` | Apply backpressure factor |
| `cache_clear` | Clear specific cache tags |
| `telemetry` | Emit telemetry event |

## Execution Order

Policies are ordered by computed priority before execution:
- `deployment_freeze` action → priority 100
- `state_transition:EMERGENCY` → 95, `state_transition:CRITICAL` → 90
- `recovery` → 80, `degradation` → 70, default → 50

## Cooldown

After a policy executes, a cooldown cache key prevents re-evaluation for `cooldown_seconds` (minimum 60s). This prevents reactive oscillation between states.

## Registering Custom Policies

```php
$engine = app(RuntimePolicyEngine::class);
$engine->register([
    'name' => 'my_custom_policy',
    // ...
]);
```
