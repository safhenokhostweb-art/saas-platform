# AI Runtime API Architecture — Phase 9.5-B

## Overview

Phase 9.5-B wires the AI infrastructure (built in 9.5-A) into live HTTP API endpoints, making all AI features fully operational across both `saas-platform` and `customer-site`.

## Endpoint Catalogue

| Endpoint                     | Method | Purpose                            | Auth         |
|------------------------------|--------|------------------------------------|--------------|
| `/api/ai/suggest`            | POST   | Multi-item suggestion generation   | sanctum      |
| `/api/ai/chat`               | POST   | Multi-turn chat completion         | sanctum      |
| `/api/ai/complete`           | POST   | Raw text completion                | sanctum      |
| `/api/ai/analyze`            | POST   | Sentiment, classification, entities| sanctum      |
| `/api/ai/generate`           | POST   | Email, template, report generation | sanctum      |
| `/api/ai/translate`          | POST   | Multi-language translation         | sanctum      |
| `/api/ai/summarize`          | POST   | Thread / document summarization    | sanctum      |
| `/api/ai/form-assistant`     | POST   | Form generation and optimization   | sanctum      |
| `/api/ai/ticket-assistant`   | POST   | Ticket reply / analysis assistant  | sanctum      |
| `/api/ai/automation-assistant`| POST  | Automation rule suggestions        | sanctum      |

## Request Pipeline (per endpoint)

```
1. AiRuntimeSafetyEngine.inspectRequest()   ← prompt injection block
2. TenantAiQuotaManager.check()             ← quota enforcement
3. AiRateLimitCoordinator.attempt()         ← rate limit (30 RPM per key)
4. AiRuntimeCoordinator.complete/Chat()     ← provider → fallback chain
5. AiTelemetryService.record()              ← observability
6. JSON response with correlation_id        ← structured output
```

## Request DTO

All requests share a common structure:
- `prompt` / `messages` — content payload
- `domain` — routing hint (general, forms, tickets, analysis, etc.)
- Optional `max_tokens`, `temperature`, `context`

## Response DTO

```json
{
  "success": true,
  "data": { ... operation-specific ... },
  "correlation_id": "uuid",
  "operation": "suggest",
  "latency_ms": 450
}
```

## Error Responses

| Status | Meaning             |
|--------|---------------------|
| 422    | Safety/injection block |
| 429    | Quota or rate limit exceeded |
| 503    | AI provider unavailable (degraded mode) |

## Security Controls

- Prompt injection detection (15+ regex patterns)
- Sensitive data masking (credit cards, SSN, API keys, Bearer tokens)
- Tenant boundary validation — cross-tenant reference detection
- Emergency kill switch (`AI_KILL_SWITCH=true` in `.env`)
- Circuit breaker (auto-open after 10 consecutive failures, reset after 5 min)

## Rate Limits

- Global: configured via `ai.rate_limiting.global_rpm`
- Per-tenant: derived from plan tier (free/starter/professional/enterprise/unlimited)
- Per-user: 30 requests/minute default

## Correlation IDs

Every request generates a UUID correlation ID:
- Returned in `X-Correlation-Id` response header
- Logged in `ai:telemetry` cache key per tenant
- Traceable through `AiInteractionTimeline`
