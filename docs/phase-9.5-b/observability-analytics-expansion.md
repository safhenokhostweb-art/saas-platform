# Observability & Analytics Expansion — Phase 9.5-B

## New Services

### AiUsageAnalyticsService

Tracks AI usage per tenant per operation per hour bucket:

```php
$analytics->recordUsage($tenantId, 'suggest', ['tokens' => 150, 'cost_usd' => 0.003]);
$analytics->getUsageSummary($tenantId, 7);     // 7-day breakdown
$analytics->getEffectivenessScore($tenantId);  // acceptance rate
$analytics->getAdoptionMetrics($tenantId);     // which features are used
$analytics->recordSuggestionFeedback($tenantId, $id, 'accepted');
```

**Metrics tracked:**
- Requests per operation per hour bucket
- Token consumption
- USD cost per operation
- Error count and rate
- Acceptance/rejection/modification of AI suggestions

### UxInteractionTelemetryService

Tracks user interactions and surface UX issues:

```php
$ux->recordInteraction($tenantId, $userId, 'form-builder', 'field-added');
$ux->recordRageClick($tenantId, $userId, '#submit-btn', 5, '/admin/forms/1');
$ux->detectAbandonment($tenantId, $userId, 'form-builder', 'step-3-conditions');
$ux->getAbandonmentAnalytics($tenantId, 7);
$ux->getFeatureAdoptionMetrics($tenantId, 30);
$ux->recordPerformanceSample('page_load_p95', 1250.0);
```

**Metrics tracked:**
- Component interaction frequency
- Rage-click detection (≥3 clicks in 2 seconds)
- Abandonment by feature + step
- Performance: page_load_p95, api_p95, lcp_p75, js_error_rate

## UX Telemetry API

**POST** `/api/ux/interaction`
```json
{ "component": "form-builder", "action": "field-added", "meta": { "field_type": "text" } }
```

**POST** `/api/ux/rage-click`
```json
{ "element": "#save-button", "clicks": 5, "page": "/admin/forms/1" }
```

**POST** `/api/ux/performance`
```json
{ "metric": "page_load_p95", "value": 1450.5 }
```

## Alpine.js Integration

The `aiRuntime` Alpine store includes automatic rage-click detection:
- Listens to global click events
- Detects ≥3 clicks on same element within 2 seconds
- Sends to `/api/ux/rage-click` asynchronously (fire-and-forget)

## Observability Dashboards

| Dashboard          | Data Source                   | Key Metrics              |
|--------------------|-------------------------------|--------------------------|
| AI adoption        | AiUsageAnalyticsService       | Feature usage rate, cost |
| AI effectiveness   | Suggestion feedback           | Acceptance rate          |
| UX friction        | UxInteractionTelemetry        | Rage clicks, abandonment |
| Performance        | Performance samples           | P95 latency, LCP         |
| Realtime health    | RealtimeRuntimeCoordinator    | Disconnects, transport   |
| Notification delivery | NotificationDeliveryTracker | Success/failure rates   |

## Retention Policy

| Data Type          | TTL      | Storage      |
|--------------------|----------|--------------|
| AI usage buckets   | 8 days   | Cache        |
| Rage clicks        | 7 days   | Cache        |
| Abandonment events | 7 days   | Cache        |
| Performance samples| 2 hours  | Cache        |
| AI effectiveness   | 30 days  | Cache        |
| Interaction events | 25 hours | Cache        |
