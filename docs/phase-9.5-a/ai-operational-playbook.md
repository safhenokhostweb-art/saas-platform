# AI Operational Playbook — Phase 9.5-A

## Activating AI in Production

### Step 1 — Configure API Keys

```env
# Choose provider
AI_PROVIDER=claude

# Anthropic Claude
CLAUDE_API_KEY=sk-ant-your-key-here
CLAUDE_ENABLED=true
CLAUDE_MODEL=claude-haiku-4-5-20251001
CLAUDE_MODEL_ADVANCED=claude-sonnet-4-6

# OpenAI (optional, as fallback)
OPENAI_API_KEY=sk-your-key-here
OPENAI_ENABLED=true
OPENAI_MODEL=gpt-4o-mini

# Fallback chain
AI_FALLBACK_CHAIN=claude,openai
```

### Step 2 — Validate Configuration

```bash
php artisan ai:validate-governance
# Target: 90+/100 before going live

php artisan ai:test-provider
# All configured providers should pass
```

### Step 3 — Set Tenant Quotas

```php
// In your plan upgrade handler:
app(TenantAiQuotaManager::class)->setTenantPlan($tenantId, 'pro');
```

### Step 4 — Enable Features Gradually

```env
# Start with 20% of tenants
AI_ROLLOUT_PERCENTAGE=20

# Scale up after validation
AI_ROLLOUT_PERCENTAGE=100
```

---

## Emergency Procedures

### Disable All AI Immediately

```env
AI_KILL_SWITCH=true
```

All requests fall through to rule-based fallbacks. No restarts needed — config cache clear triggers it.

### Switch Provider on Degradation

```env
AI_PROVIDER=openai   # if Claude is down
```

### Investigate Provider Issues

```bash
php artisan ai:diagnostics --probe
php artisan ai:diagnostics --json | jq '.providers'
```

### Reset Circuit Breaker

Circuit breakers expire automatically via TTL. To force-reset, clear cache:
```bash
php artisan cache:clear
# OR more surgical:
php artisan tinker
>>> app(\SaaSPlatform\CoreUtils\AI\AiProviderManager::class)->recordSuccess('claude')
```

---

## Monitoring Checklist

| Metric | Warning | Critical | Action |
|---|---|---|---|
| Provider failure rate | >10% | >30% | Check API key, switch provider |
| Average latency | >3000ms | >8000ms | Switch to faster model |
| Quota utilization | >80% | >95% | Increase limit or throttle |
| Injection attempts | >5/day | >20/day | Review tenant activity |
| Acceptance rate | <40% | <20% | Review prompt quality |

---

## Cost Management

```bash
# Daily cost overview
php artisan ai:quota-report --json | jq '.total_cost'

# Per-tenant
php artisan ai:quota-report tenant-123 --days=30
```

Alert setup recommendation: schedule `ai:diagnostics` and send Slack webhook if system cost exceeds threshold.

---

## Capacity Planning

- **Free tier (10 req/day)**: claude-haiku only, no advanced model
- **Pro tier (500 req/day)**: haiku default, sonnet for summarization
- **Enterprise (2000/day)**: full model access, budget cap enabled
