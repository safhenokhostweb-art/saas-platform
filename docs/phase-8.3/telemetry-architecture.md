# Telemetry Architecture — Phase 8.3

## Overview

The Phase 8.3 observability stack is structured as a set of layered services, all living inside `saas-platform/core-utils`. No `App\` imports exist in any telemetry class.

## Core Components

```
TelemetryContext          — Singleton carrying trace_id, request_id, tenant_id, module, release_id
TelemetrySanitizer        — Auto-redacts: authorization, token, license_key, password, secret, bearer
TelemetryPayloadFactory   — Builds canonical event payloads with all 10 required fields
TelemetryEventBus         — Routes events to registered channel handlers
TelemetryStorageManager   — Writes to Laravel Log + telemetry_events table (when available)
TelemetryManager          — Central API: emit(), info(), warning(), error(), critical(), debug()
```

## Canonical Event Shape

Every event emitted via `TelemetryManager` carries these fields:

```json
{
  "event_uuid":      "UUID v4",
  "trace_id":        "UUID v4 from X-Trace-Id header or auto-generated",
  "request_id":      "UUID v4 from X-Request-Id header or auto-generated",
  "tenant_id":       "int or null",
  "module":          "string (tickets | forms | core | security | queue | deployment | anomaly)",
  "environment":     "production | staging | local",
  "release_id":      "UUID of current release manifest or null",
  "runtime_context": { "php_version": "...", "memory_mb": 0.0, "hostname": "...", "pid": 0, "sapi": "..." },
  "timestamp":       "ISO 8601",
  "severity":        "debug | info | warning | error | critical",
  "event":           "dot.notation.event.name",
  "data":            { "sanitized payload" }
}
```

## Storage

| Backend | When Active | Location |
|---|---|---|
| Laravel Log | Always | `storage/logs/*.log` |
| DB (`telemetry_events`) | After `php artisan migrate` | `telemetry_events` table |

## Distributed Tracing Headers

| Header | Meaning |
|---|---|
| `X-Trace-Id` | Propagated across all services for a single user session |
| `X-Request-Id` | Per HTTP request — new UUID for each job dispatched to queue |
| `X-Release-Id` | Current release UUID for deployment correlation |

`CorrelationIdMiddleware` injects these at the beginning of each web request.

## Sensitive Data Policy

The following are NEVER written to telemetry:

- `Authorization` header values
- Bearer tokens
- License keys (pattern: `XXXX-YYYY-ZZZZ-AAAA`)
- `password`, `secret`, `api_key`, `access_token`, `refresh_token`, `client_secret`

`TelemetrySanitizer` enforces this via recursive key matching and pattern replacement.
