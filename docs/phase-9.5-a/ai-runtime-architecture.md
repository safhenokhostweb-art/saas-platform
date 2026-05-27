# AI Runtime Architecture — Phase 9.5-A

## Overview

Phase 9.5-A introduces a production-grade, provider-agnostic AI runtime connected to real AI providers (OpenAI and Claude/Anthropic). The architecture guarantees zero vendor lock-in, graceful degradation, tenant isolation, and enterprise security at every layer.

---

## Provider Abstraction Layer

### AiProviderInterface (existing, unchanged)
```php
interface AiProviderInterface {
    getName(): string
    isAvailable(): bool
    complete(string $prompt, array $options): AiCompletionResult
    completeChat(array $messages, array $options): AiCompletionResult
    embed(string $text): array
    getCapabilities(): array
}
```

### Concrete Implementations

| Class | Provider | Capabilities |
|---|---|---|
| `OpenAIProvider` | OpenAI API | chat, completion, embeddings, json_mode, summarization, classification |
| `ClaudeProvider` | Anthropic Claude | chat, completion, json_mode, summarization, classification |

Both use Laravel `Http` facade for HTTP — no vendor SDKs. Both check `AI_KILL_SWITCH` before executing.

---

## AiProviderManager

Central registry for all providers. Handles:
- Provider registration and activation
- Auto-selection from priority list (`AI_PROVIDER` config + `AI_FALLBACK_CHAIN`)
- Circuit breaker state (`ai:circuit:{provider}:open` cache key)
- Provider health failure recording
- `completeWithFallback()` — tries all candidates until one succeeds

**Circuit Breaker Pattern:**
- Threshold: 3 failures → circuit opens for 60 seconds
- Recovery: automatic via TTL expiry
- Manual reset: via `recordSuccess()` call

---

## AiRuntimeCoordinator

The governed execution entry point. Every AI request passes through:

```
1. Kill switch check
2. Quota check (TenantAiQuotaManager)
3. Rate limit check (AiRateLimitCoordinator)
4. Prompt sanitization (PromptSecuritySanitizer)
5. Provider execution (AiFallbackCoordinator → AiProviderManager)
6. Telemetry recording (AiTelemetryService)
7. Health metric update (AiProviderHealthMonitor)
8. Quota increment
```

---

## AiFallbackCoordinator

Provides a layered fallback strategy:
1. Try live providers via `AiProviderManager::completeWithFallback()`
2. Try domain-specific rule-based fallback (registered via `registerFallback()`)
3. Return empty/JSON fallback with confidence 0.0

All existing `AiFormBuilderAssistant` and `AiSupportReplyAssistant` rule-based logic is preserved as domain fallbacks.

---

## AiCapabilityRegistry

Maps feature requests to provider capabilities:

| Feature | Required Capability |
|---|---|
| form_generation | chat, completion |
| ticket_reply | chat, completion |
| summarization | summarization, chat |
| embeddings | embeddings |
| json_structured | json_mode, chat |
| sentiment_analysis | chat, classification |
| spam_detection | chat, classification |

Use `canExecute(capability)` before calling any AI feature to verify the active provider supports it.

---

## Configuration

Set in `config/ai.php`, overridable via `.env`:

```env
AI_PROVIDER=claude          # or openai, none
CLAUDE_API_KEY=sk-ant-...
CLAUDE_ENABLED=true
CLAUDE_MODEL=claude-haiku-4-5-20251001

AI_FALLBACK_CHAIN=claude,openai
AI_KILL_SWITCH=false
AI_ROLLOUT_PERCENTAGE=100
```

---

## Adding a New Provider

1. Implement `AiProviderInterface`
2. Register in `CoreUtilsServiceProvider`:
   ```php
   $manager->register($app->make(MyProvider::class));
   ```
3. Add cost rates to `config/ai.php` under `cost_rates.{provider}`
4. Add to `AI_FALLBACK_CHAIN`
