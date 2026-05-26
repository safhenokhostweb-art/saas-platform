# Sensitive Data Policy — Phase 8.3

## What is NEVER Logged

The following data classes are structurally prohibited from telemetry output:

| Category | Examples | Enforcement |
|---|---|---|
| Authorization headers | `Authorization: Bearer xxx` | `TelemetrySanitizer` key match |
| Raw tokens | `access_token`, `refresh_token`, `bearer` | Key match → `[REDACTED]` |
| License keys | `XXXX-YYYY-ZZZZ-AAAA` | Pattern → `[LICENSE-REDACTED]` |
| Passwords | `password`, `passwd` | Key match → `[REDACTED]` |
| Secrets | `secret`, `client_secret`, `api_key` | Key match → `[REDACTED]` |
| Private keys | `private_key` | Key match → `[REDACTED]` |
| Credentials | `credential`, `credentials` | Key match → `[REDACTED]` |

## Enforcement Mechanism

`TelemetrySanitizer` is injected into `TelemetryPayloadFactory` and called on every `data` payload before storage:

```php
// This happens automatically on every TelemetryManager::emit() call
$payload['data'] = $this->sanitizer->sanitize($data);
```

Security event loggers (`SecurityEventLogger`, `SecurityTelemetryService`) apply an additional sanitization layer before forwarding context to the telemetry bus.

## Testing Sanitization

```bash
php artisan observability:validate-governance
# The 'sanitization' check verifies redaction works correctly
```

```php
// Manual test:
$sanitizer = app(\SaaSPlatform\CoreUtils\Telemetry\TelemetrySanitizer::class);
$result = $sanitizer->sanitize(['authorization' => 'Bearer abc123', 'safe' => 'hello']);
// $result['authorization'] === '[REDACTED]'
// $result['safe'] === 'hello'
```

## Cross-Tenant Data Isolation

- `TenantObservabilityService::snapshotForTenant(int $tenantId)` reads only the specified tenant's data
- `TenantMetricsRepository::forTenant(int $tenantId)` always applies a `WHERE tenant_id = ?` filter
- The `TelemetryContext` singleton is request-scoped; `tenant_id` is set from the authenticated tenant context only
- No telemetry API returns mixed-tenant data — cross-tenant views are only available to platform admins via `allTenantSummary()` which omits per-tenant content

## Audit Trail for Sensitive Operations

`SensitiveOperationAuditStream` maintains an immutable log of security-relevant actions. The following trigger an audit entry:

- `tenant.delete`, `tenant.transfer`, `tenant.disable`
- `user.permission.grant`, `user.permission.revoke`
- `license.assign`, `license.revoke`
- `deployment.lock`, `deployment.unlock.force`
- `admin.impersonate`

All context passed to `record()` must be pre-sanitized by the caller.
