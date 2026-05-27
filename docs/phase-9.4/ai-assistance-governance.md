# AI Assistance Governance — Phase 9.4

## Design Principle

The AI assistance layer is **provider-agnostic** by architecture. No vendor SDK is hardcoded into the core services. Providers are registered at runtime via `AiSuggestionEngine::registerProvider()`.

## Service Inventory

| Service | Responsibility |
|---|---|
| `AiProviderInterface` | Contract all AI providers must implement |
| `AiCompletionResult` | Value object: text, success, confidence, meta |
| `AiPromptRegistry` | Centralized prompt template store with variable substitution |
| `AiSuggestionEngine` | Routing engine; selects active provider, caches results |
| `AiFormBuilderAssistant` | Form-specific AI suggestions + rule-based fallbacks |
| `AiSupportReplyAssistant` | Ticket reply suggestions, categorization, SLA risk, canned responses |
| `ContextAwareRecommendationService` | Dashboard insights, cross-module recommendations |
| `SmartAutomationAdvisor` | Workflow opportunity detection for forms + tickets |

## Provider Registration Pattern

```php
// In a service provider:
$engine = app(AiSuggestionEngine::class);
$engine->registerProvider(new MyOpenAiAdapter());
$engine->setActiveProvider('openai');
```

Providers must implement:
```php
interface AiProviderInterface {
    public function getName(): string;
    public function isAvailable(): bool;
    public function complete(string $prompt, array $options = []): AiCompletionResult;
    public function completeChat(array $messages, array $options = []): AiCompletionResult;
    public function embed(string $text): array;
    public function getCapabilities(): array;
}
```

## Fallback Guarantee

`AiSuggestionEngine::suggest()` **never throws** to the caller. When no provider is available or the call fails, a rule-based fallback is returned with `confidence = 0.0` and `source = 'fallback'`.

All assistants (`AiFormBuilderAssistant`, `AiSupportReplyAssistant`) implement rule-based logic that activates when `AiSuggestionEngine::isAvailable()` returns false.

## Prompt Registry

Prompts are stored in `AiPromptRegistry` with template variables using `{{variable}}` syntax:

```php
$registry->register('ticket.suggest_reply',
    'Draft a reply to: Subject: {{subject}}\nMessage: {{message}}',
    ['domain' => 'tickets', 'output' => 'text']
);
```

## Caching

Results are cached in Laravel's cache driver with a 3600-second TTL, keyed by `md5(promptKey + variables)`. Override with `$cached = false` for time-sensitive suggestions.

## Safety Contracts

1. AI output is **never automatically applied** — always presented as a suggestion
2. JSON output is sanitized (markdown fences stripped before `json_decode`)
3. AI categorization/priority is advisory only — human confirmation required for escalation
4. No PII (names, emails) is sent to AI providers in prompt templates by default
