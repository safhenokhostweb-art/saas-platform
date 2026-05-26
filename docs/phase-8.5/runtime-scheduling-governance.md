# Runtime Scheduling Governance — Phase 8.5

## Overview

The scheduling governance stack classifies workloads, assigns tenant-aware priorities, and routes dispatch through governance checks before any job reaches the queue.

## Workload Classes

| Class | Priority | Queue | Deferred? |
|-------|----------|-------|-----------|
| critical | 100 | high | Never |
| high | 75 | high | No |
| normal | 50 | default | No |
| low | 25 | low | Only under pressure |
| deferred | 5 | low | Yes (always when high-pressure) |

## Priority Formula

```
priority = (tenant_priority × 0.4) + (workload_priority × 0.6) - pressure_penalty
pressure_penalty = pressure_score × 0.3
```

Critical-path jobs bypass the formula and always get priority 100.

## Governance-Aware Dispatch Flow

```
GovernanceAwareDispatcher.dispatch(tenantId, jobClass)
    ↓
1. TenantPriorityScheduler.schedule()  ← classify + compute priority
2. Check GovernanceContext.getState()  ← EMERGENCY blocks non-critical
3. Check RuntimeThrottleManager       ← factor < 0.2 → defer DEFERRED class
4. TenantQueueAffinityResolver        ← resolve sharded queue
5. Log telemetry                       ← every dispatch observable
```

## Deferred Execution Queue

- Max 500 deferred items; sorted by priority descending
- `RuntimeScheduler.drainDeferred(limit)` releases items to be dispatched
- Items auto-expire after 1 hour if not drained
- `DeferredExecutionCoordinator.clearForTenant(id)` removes all items for a tenant

## DispatchResult States

| Status | Meaning |
|--------|---------|
| `dispatched` | Job sent to named queue with throttle factor |
| `deferred` | Job held in deferred queue for later dispatch |
| `rejected` | Job dropped (platform_emergency + non-critical) |

## Integration

```php
$result = app(RuntimeScheduler::class)->dispatch($tenantId, MyJob::class, $payload);
if ($result->wasDispatched()) {
    MyJob::dispatch(...)->onQueue($result->queue);
}
```
