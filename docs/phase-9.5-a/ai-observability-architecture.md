# AI Observability Architecture — Phase 9.5-A

## Services

| Service | Responsibility |
|---|---|
| `AiTelemetryService` | Per-tenant and system-wide request stats, domain breakdown |
| `AiInteractionTimeline` | Per-interaction event chain (started → completed → user actions) |
| `AiDecisionAuditLog` | Immutable log of AI decisions and accept/reject outcomes |
| `AiProviderDiagnostics` | Provider health report + live probe execution |
| `AiFailureAnalytics` | Failure classification, hourly trends, provider/type breakdown |
| `AiSuggestionEffectivenessAnalyzer` | Acceptance/rejection/edit tracking, per-domain effectiveness |

---

## AiTelemetryService

Records every AI request (success or failure):

```php
$telemetry->record([
    'tenant_id'  => $tenantId,
    'domain'     => 'forms',
    'provider'   => 'claude',
    'success'    => true,
    'latency_ms' => 234,
    'tokens'     => 450,
    'source'     => 'provider',
]);
```

Retrieval:
```php
$telemetry->getStats($tenantId, 7);
// {total, success, failures, tokens, avg_latency, success_rate}

$telemetry->getDomainBreakdown($tenantId, 7);
// ['forms' => 120, 'tickets' => 80, ...]
```

---

## AiInteractionTimeline

Tracks the lifecycle of individual AI interactions:

```php
$id = $timeline->startInteraction($tenantId, 'tickets');
// ... AI executes ...
$timeline->completeInteraction($tenantId, $id, true, $result->meta);
$timeline->recordUserAction($tenantId, $id, 'accepted');
```

---

## AiDecisionAuditLog

Immutable log of every AI response and how the user acted on it:

```php
$auditLog->log(
    $tenantId, 'tickets', 'reply_suggestion', $suggestion,
    'claude', $accepted, $rejectionReason
);

$auditLog->getAcceptanceRate($tenantId, 'tickets');
// {total, accepted, rejected, rate}
```

---

## AiProviderDiagnostics

```php
$report = $diagnostics->getDiagnosticsReport();
// {providers: {openai: {available, circuit_open, healthy, avg_latency, ...}, ...}, system_health, recommendations}

$result = $diagnostics->testProvider('claude');
// {provider, success, latency, response, meta}
```

---

## AiSuggestionEffectivenessAnalyzer

Records user feedback on AI suggestions:

```php
// From /api/ai/feedback endpoint
$analyzer->recordAccepted($tenantId, 'forms', $suggestionHash);
$analyzer->recordRejected($tenantId, 'tickets', $suggestionHash, 'off_topic');
$analyzer->recordEdited($tenantId, 'tickets', $suggestionHash, 0.3);

$report = $analyzer->getEffectivenessReport($tenantId, 30);
// {total, accepted, rejected, edited, acceptance_rate, by_domain}
```

---

## Observability CLI

```bash
php artisan ai:diagnostics                # Full diagnostics report
php artisan ai:diagnostics --probe        # Run live API probes
php artisan ai:diagnostics --json         # Machine-readable output
php artisan ai:quota-report               # Quota + cost report
```
