# Tenant Observability Guide — Phase 8.3

## Isolation Guarantee

All tenant observability APIs are structurally tenant-scoped:

- `TenantObservabilityService::snapshotForTenant(int $tenantId)` — reads only that tenant
- `TenantMetricsRepository::forTenant(int $tenantId)` — DB query always filters `WHERE tenant_id = ?`
- `TenantRuntimeMetrics` — cache keys include `tenant_id`, no cross-reading possible
- Cross-tenant aggregation (`allTenantSummary()`) is restricted to platform admin context and returns only counts, never content

## Recording Tenant Activity

```php
$obs = app(\SaaSPlatform\CoreUtils\Telemetry\Tenant\TenantObservabilityService::class);

// Record a request (auto-uses TelemetryContext::getTenantId())
$obs->recordRequest('/tickets', 145.3, 200);

// Record an error
$obs->recordError('TicketService: file not found', 'attachments');

// Get snapshot for a specific tenant (admin use)
$obs->snapshotForTenant(42);
```

## Tenant Metrics (Cache-Based, 1 Hour Window)

`TenantRuntimeMetrics` accumulates in cache with a 1-hour TTL:

| Metric | Description |
|---|---|
| `request_count` | Total requests for this tenant |
| `error_count` | HTTP 5xx responses |
| `error_rate` | Percentage of error responses |
| `avg_duration_ms` | Average request duration |
| `status_distribution` | Breakdown by status class (2xx, 3xx, 4xx, 5xx) |

## Tenant Incident Tracking

`TenantIncidentTracker` maintains a rolling 50-entry incident list per tenant (in cache):

- Records errors from `TenantObservabilityService::recordError()`
- Records maintenance events from `TenantMaintenanceManager`
- Queries maintenance history from `tenant_maintenance` table

## Module Usage Telemetry

```php
$usage = app(\SaaSPlatform\CoreUtils\Telemetry\Tenant\TenantUsageTelemetry::class);
$usage->recordModuleUsage(42, 'tickets', 'create');
$usage->recordFeatureAccess(42, 'export');
$usage->getUsageForTenant(42);
```

## Operational Metrics for All Tenants (Admin Only)

```php
$repo = app(\SaaSPlatform\CoreUtils\Telemetry\Metrics\TenantMetricsRepository::class);

// Summary across all tenants (event counts, error counts per tenant)
$repo->allTenantSummary();

// Active maintenance windows
$repo->maintenanceSummary();
```
