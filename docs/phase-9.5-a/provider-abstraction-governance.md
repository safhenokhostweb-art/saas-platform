# Provider Abstraction Governance — Phase 9.5-A

## Design Principles

1. **Zero vendor lock-in** — changing AI providers requires only `.env` change
2. **Contract-first** — all code depends on `AiProviderInterface`, never on concrete providers
3. **Runtime swappable** — `AiProviderManager::setActive()` at runtime, no restart needed
4. **Capability-driven routing** — `AiCapabilityRegistry` selects best provider for each task
5. **Graceful degradation** — every AI call has a rule-based fallback in `AiFallbackCoordinator`

---

## Provider Registration Pattern

```php
// In CoreUtilsServiceProvider (auto-configured)
$manager = new AiProviderManager();
$manager->register(new OpenAIProvider());
$manager->register(new ClaudeProvider());

// Config-driven activation
if (config('ai.provider') === 'claude') {
    $manager->setActive('claude');
}
```

---

## Capability Routing

```php
$registry = app(AiCapabilityRegistry::class);

// Check before executing
if ($registry->canExecute('embeddings')) {
    $embedding = $provider->embed($text);
}

// Find best provider for a capability
$bestProvider = $registry->getBestProviderFor('summarization');
// Returns 'claude' if Claude is available and supports summarization
```

---

## Fallback Registration

Domain-specific fallbacks are registered in service providers:

```php
app(AiFallbackCoordinator::class)->registerFallback('forms', function (string $prompt) {
    return ['fields' => $this->ruleBasedFields($prompt)];
});
```

The coordinator calls this if all live providers fail.

---

## Adding a Third-Party Provider

```php
// 1. Implement the interface
class GeminiProvider implements AiProviderInterface
{
    public function getName(): string { return 'gemini'; }
    public function isAvailable(): bool { return !empty(config('ai.gemini.api_key')); }
    // ...implement complete(), completeChat(), embed(), getCapabilities()
}

// 2. Register in CoreUtilsServiceProvider
$manager->register($app->make(GeminiProvider::class));

// 3. Add to config/ai.php
'gemini' => ['api_key' => env('GEMINI_API_KEY'), 'enabled' => env('GEMINI_ENABLED', false)],

// 4. Use in .env
AI_PROVIDER=gemini
AI_FALLBACK_CHAIN=claude,openai
```

---

## Circuit Breaker Configuration

```env
AI_FAILURE_THRESHOLD=3      # failures before circuit opens
AI_CIRCUIT_OPEN_TTL=60      # seconds circuit stays open
AI_RECOVERY_WINDOW=120      # seconds failure counter lives
AI_HEALTH_INTERVAL=300      # health check interval
```

---

## Monitoring Provider State

```bash
php artisan ai:test-provider claude      # Test a specific provider
php artisan ai:test-provider --json      # Test all, JSON output
php artisan ai:diagnostics --probe       # Full diagnostics with live probes
```
