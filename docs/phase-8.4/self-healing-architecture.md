# Self-Healing Architecture — Phase 8.4

## Overview

The self-healing stack autonomously recovers from runtime incidents without operator intervention for auto-resolvable failures (TRANSIENT, RESOURCE, DEPENDENCY classes).

## Failure Classification

| Class | Examples | Auto-Recoverable |
|-------|----------|-----------------|
| TRANSIENT | Timeout, connection blip | Yes |
| RESOURCE | Memory pressure, queue backlog | Yes |
| DEPENDENCY | External service failure | Yes |
| CONFIGURATION | Bad config value | No |
| SECURITY | Auth burst, boundary violation | No |
| DATA_CORRUPTION | Inconsistent data | No |
| INFRASTRUCTURE | Hardware failure | No |

## Recovery Strategies (Idempotent-Safe)

The following strategies execute automatically: `queue_restart`, `cache_rebuild`, `health_check_retry`, `alert`

Operator-required strategies (flagged, not auto-executed): `deployment_rollback_trigger`, `maintenance_mode`

## Self-Healing Flow

```
RuntimeAnomalyEngine.scan()
    ↓
SelfHealingEngine.healFromAnomalies()
    ↓
FailureClassificationEngine.classify()
    ↓
RuntimeRecoveryCoordinator.coordinateRecovery()
    ↓
RecoveryStrategyManager.execute()  ← idempotency gate
    ↓
RecoveryExecutionLog.record()      ← every action logged
```

## Idempotency Guarantee

`RecoveryStrategyManager::isIdempotentSafe()` gates all automatic executions. Non-idempotent strategies (rollback, maintenance) are flagged as signals for operator review rather than auto-executed. This prevents double-execution side effects on repeated healing cycles.

## No Infinite Loops

- Every anomaly triggers at most one heal cycle per `scan()` call
- Policy cooldowns (min 60s) prevent policy thrashing
- `SelfHealingEngine` does not recurse — it scans once and returns
- `RuntimeGovernanceKernel::tick()` is a single non-looping cycle
