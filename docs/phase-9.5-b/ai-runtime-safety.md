# AI Runtime Safety — Phase 9.5-B

## Safety Architecture

```
Request → AiRuntimeSafetyEngine.inspectRequest()
            ├── Prompt injection detection (16 patterns)
            ├── Sensitive data detection (6 pattern types)
            ├── Abuse flood check (100 req/hr per tenant per op)
            └── Emergency kill switch check

Response → AiResponseRiskAnalyzer.analyze()
             ├── Hallucination risk scoring
             ├── Tenant data leak detection
             ├── PII exposure detection
             ├── Unsafe code detection
             └── Confidence scoring

Cross-tenant → AiTenantBoundaryValidator
                 ├── validateRequest() — no foreign tenant IDs in prompt
                 └── validateResponse() — no foreign tenant data in output

Circuit breaker → AiEmergencyCircuitBreaker
                    ├── Auto-opens after 10 consecutive failures
                    ├── Resets after 5 minutes (half-open state)
                    └── Manual kill switch via config('ai.emergency.kill_switch')
```

## Prompt Injection Patterns (blocked)

The `AiRuntimeSafetyEngine` blocks prompts matching any of these:
- "ignore (all/previous/above) instructions"
- "you are now / act as / pretend you are"
- "jailbreak / bypass safety / override guidelines"
- DAN (Do Anything Now) patterns
- PHP code injection (`<?php`, `eval(`, `exec(`, `shell_exec`)
- "reveal system prompt / instructions"
- "sudo" escalation attempts

## Sensitive Data Masking

Auto-masked before logging/telemetry:
- Credit card numbers (16-digit patterns)
- Social Security Numbers
- `password = ...`, `api_key = ...`, `secret = ...`
- Bearer tokens
- Authorization headers

## Risk Scoring

`AiResponseRiskAnalyzer` scores each response 0.0–1.0:

| Risk Type          | Weight |
|--------------------|--------|
| Tenant data leak   | 1.0    |
| PII exposure        | 0.9    |
| Unsafe code        | 0.8    |
| Hallucination      | 0.3    |
| Low confidence     | 0.2    |
| Off-topic          | 0.1    |

Risk level: `low` (<0.2) / `medium` (0.2–0.5) / `high` (0.5–0.8) / `critical` (≥0.8)

## Emergency Kill Switch

Set in `.env`:
```
AI_KILL_SWITCH=true
```

All AI requests immediately return degraded fallback responses. Circuit breaker state shows `kill_switch`.

## Commands

```bash
php artisan ai:validate-runtime
php artisan ai:validate-runtime --json --strict
php artisan ai:validate-runtime --fail-on-warning
```

## Monitoring

Violation logs stored in cache: `ai:safety:violations:{tenantId}` (last 100, 7-day TTL)
Flood detection: `ai:safety:flood:{tenantId}:{operation}` (100/hr threshold)
