# Incident Reconstruction Guide — Phase 8.3

## Reconstructing an Incident from a Trace ID

Every production incident that involved an HTTP request has a `trace_id` in the response header `X-Trace-Id`. Use it to reconstruct the full event sequence:

```bash
# From tinker:
php artisan tinker

>>> $service = app(\SaaSPlatform\CoreUtils\Telemetry\Incident\IncidentReconstructionService::class)
>>> $service->reconstruct('your-trace-id-here')
```

The result includes:
- `summary` — event count, modules involved, first/last event, anomaly count
- `root_cause` — inferred cause with confidence level
- `timeline` — ordered list of all events
- `next_steps` — actionable remediation steps

## Reconstructing a Deployment Incident

```bash
>>> $service->reconstructForDeployment('deployment-uuid-here')
```

This reads `deployment_logs` for the specified UUID and surfaces all failed phases.

## Building a Tenant Timeline

```bash
>>> $builder = app(\SaaSPlatform\CoreUtils\Telemetry\Incident\RuntimeTimelineBuilder::class)
>>> $builder->buildForTenant(tenantId: 42, from: '2026-05-26T00:00:00Z', to: '2026-05-26T23:59:59Z')
```

## Replaying a Trace

```bash
>>> $replay = app(\SaaSPlatform\CoreUtils\Telemetry\Incident\TraceReplayService::class)
>>> $replay->replay('trace-id-here')
>>> $replay->anomalyWindow('trace-id-here', windowSeconds: 60)
```

`replay()` returns the sequence of events with their modules and severities.
`anomalyWindow()` filters to only error/critical events — useful for finding the exact moment of failure.

## Prerequisite

The `telemetry_events` table must exist and have been receiving events before the incident. Without DB storage, reconstruction falls back to log file analysis only.

```bash
php artisan migrate
# Verify:
php artisan observability:validate-governance
```
