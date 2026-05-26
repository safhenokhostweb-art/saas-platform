# Queue Observability Guide — Phase 8.3

## Commands

```bash
# Queue telemetry snapshot (pending, failed, depths, performance)
php artisan queue:telemetry
php artisan queue:telemetry --json

# Queue diagnostics (failed job analysis, worker health)
php artisan queue:diagnostics
php artisan queue:diagnostics --json
php artisan queue:diagnostics --job=42          # Diagnose specific failed job
```

## Telemetry Events

Queue-related events emitted to `telemetry_events`:

| Event | Severity | Description |
|---|---|---|
| `queue.job.dispatched` | info | Job added to queue |
| `queue.job.processed` | info | Job completed with duration |
| `queue.job.failed` | error | Job failed with exception class |

## Worker Health Tracking

`WorkerHealthTracker` uses cache heartbeats to monitor worker liveness:

```php
// In a queue worker (call this from a custom Job middleware or base job):
app(WorkerHealthTracker::class)->recordHeartbeat($workerId);

// Check if a worker is on the current release:
app(WorkerHealthTracker::class)->isWorkerOnCurrentRelease($workerId);
```

Workers that haven't sent a heartbeat in 180 seconds are marked `stale`.

## Performance Health Levels

`QueuePerformanceAnalyzer::computeHealth()` returns:

| Level | Condition |
|---|---|
| `healthy` | < 10 failed, < 500 pending |
| `degraded` | 10–49 failed OR 500–999 pending |
| `critical` | ≥ 50 failed OR ≥ 1000 pending |

## Trace Context in Queue Jobs

When dispatching a job, serialize the current trace context:

```php
use SaaSPlatform\CoreUtils\Telemetry\Tracing\RequestTraceManager;

$tracePayload = app(RequestTraceManager::class)->forQueuePayload();
MyJob::dispatch($data, $tracePayload);
```

In the job's `handle()`:

```php
app(RequestTraceManager::class)->restoreFromQueuePayload($this->tracePayload);
```

This ensures all telemetry emitted inside the job carries the original `trace_id`.
