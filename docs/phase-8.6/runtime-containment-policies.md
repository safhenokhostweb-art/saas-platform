# Runtime Containment Policies — Phase 8.6

## Violation Types

| Type | Severity | Auto-Terminate? |
|---|---|---|
| capability_violation | warning | after 3 total |
| cross_tenant_violation | critical | immediate |
| timeout_violation | warning | after 3 total |
| deployment_execute_attempt | critical | immediate |
| shell_execution_attempt | critical | immediate |
| execution_exception | warning | after 3 total |

## Containment Pipeline

```
PluginContainmentManager.guardExecution(context, callback)
  1. quarantine check → throw if quarantined
  2. run callback
  3. detectTimeoutViolation → evaluate for termination
  4. on exception → record violation
```

## Quarantine

`ExtensionQuarantineService` stores quarantine records in cache with 24h TTL.
Quarantine reason recorded. Admin can release via `quarantine.release(moduleId)`.

## Termination Policy (UnsafeExecutionTerminator)

```
evaluateAndTerminate(moduleId, violationType, context)
  → record violation
  → count = violationTelemetry.violationCount(moduleId)
  → if count >= 3 OR isImmediateTermination(type) → quarantine
  → return true if terminated
```

Immediate termination types: `cross_tenant_violation`, `deployment_execute_attempt`, `shell_execution_attempt`

## Violation Telemetry

All violations emit `plugin.sandbox.violation` event with module_id, violation_type, and context. Last 50 violations retained per module.
