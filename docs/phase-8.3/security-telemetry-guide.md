# Security Telemetry Guide — Phase 8.3

## Security Event Taxonomy

| Event | Severity | Class | Trigger |
|---|---|---|---|
| `security.auth.failure` | warning | `AuthFailureTelemetry` | Failed login attempt |
| `security.auth.success` | info | `SecurityTelemetryService` | Successful authentication |
| `security.tenant.boundary_violation` | critical | `TenantBoundaryViolationTelemetry` | Cross-tenant access attempt |
| `security.access.unauthorized` | warning | `SecurityEventLogger` | 403 Forbidden |
| `security.operation.audit` | warning/info | `SensitiveOperationAuditStream` | Sensitive admin action |
| `security.rate_limit.exceeded` | warning | `SecurityTelemetryService` | Rate limit hit |
| `security.request.suspicious` | warning | `SecurityTelemetryService` | Heuristic flag |

## Recording Security Events

```php
$sec = app(\SaaSPlatform\CoreUtils\Telemetry\Security\SecurityTelemetryService::class);

// Auth failure (already sanitized by the service)
$sec->onAuthFailure('invalid_credentials', ['ip' => $request->ip()]);

// Cross-tenant access violation
$sec->onTenantBoundaryViolation(attemptedTenantId: 99, resource: 'tickets');

// Sensitive admin operation
$sec->onSensitiveOperation('user.permission.grant', actorId: 1, context: [
    'target_user' => 42,
    'permission'  => 'admin',
]);
```

## Auth Failure Burst Detection

`SecurityAnomalyDetector` checks auth failure counters written by `AuthFailureTelemetry::record()`:

- Threshold: 20 failures in a 15-minute window
- Anomaly code: `SECURITY_AUTH_FAILURE_BURST`
- When triggered: emits a `critical` telemetry event and sets 5-minute cooldown

## Audit Log Queries

```php
$audit = app(\SaaSPlatform\CoreUtils\Telemetry\Security\SensitiveOperationAuditStream::class);

// Get recent security events (from telemetry_events)
$audit->queryAuditLog(['from' => now()->subHour()->toIso8601String()]);

// Manually triggered operations that are auto-audited:
$audit->getAuditedOperations();
```

## Tenant Boundary Violation Counter

The `TenantBoundaryViolationTelemetry` class increments a global cache counter on every violation. This counter is read by `TenantIsolationAnomalyDetector`. Reset it after a false-positive investigation:

```php
app(\SaaSPlatform\CoreUtils\Telemetry\Security\TenantBoundaryViolationTelemetry::class)->resetCounter();
```
