# Operational Analytics Architecture — Phase 9.4

## OperationalDashboardService

**Namespace:** `SaaSPlatform\CoreUtils\Analytics\OperationalDashboardService`

Aggregates operational metrics from multiple data sources with cache-backed responses to prevent DB thrashing.

### Methods

| Method | Cache TTL | Description |
|---|---|---|
| `getRealtimeOperations(tenantId)` | 30s | Active users, open tickets, queue depth, error rate |
| `getSupportAnalytics(tenantId, period)` | 300s | Created/resolved tickets, avg resolution, SLA compliance |
| `getFormAnalytics(tenantId, period)` | 300s | Form count, submissions, completion/abandonment rates |
| `getTenantHealth(tenantId)` | 60s | Uptime, error rate, cache hit rate, DB latency |
| `getQueueHealth()` | 30s | Pending jobs, failures, workers, throughput |
| `getSecurityPosture()` | 300s | Threat level, failed logins, suspicious IPs, patch compliance |
| `getSlaCompliance(tenantId, days)` | (inline) | Real-time SLA breach ratio |
| `getResourceFairness(tenantId)` | (inline) | CPU/memory/queue share + fairness index |

### Data Sources

- **Tickets**: `Ticket` model queries (with try/catch for module-not-loaded scenarios)
- **Forms**: `Form` + `FormSubmission` model queries
- **Queue**: `DB::table('jobs')` direct query
- **Security**: Computed from auth failure telemetry (future: SecurityTelemetryService integration)
- **Health**: Combination of Cache, DB latency probe, telemetry metrics

### Tenant Safety

Every method accepts `tenantId` and scopes all queries via `where('site_id', $tenantId)`. The dashboard never aggregates cross-tenant data.

## Dashboard View

The analytics dashboard (`tickets::admin.tickets.analytics`) renders:
- 6 KPI cards (realtime data)
- 3-column middle section (resolution time, SLA ring, security posture)
- 2-column bottom section (queue health, tenant health)

Alpine.js `operationalDashboard()` component:
- Accepts period selector (`7d`, `30d`, `90d`)
- Calls `/admin/tickets/analytics/data?period={period}` on period change or manual refresh
- Auto-refreshes every 60 seconds

## Extensibility

Add a new dashboard metric:
1. Add a method to `OperationalDashboardService`
2. Return it in `TicketAnalyticsController::data()`
3. Bind an `x-text` or chart to the returned value in the view

The architecture is intentionally simple — no dedicated analytics DB needed at this scale.
