<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

use Illuminate\Support\Facades\Log;

class PromptSecuritySanitizer
{
    public function __construct(
        private readonly PromptInjectionDetector $injectionDetector,
        private readonly SensitiveDataRedactor   $redactor,
        private readonly AiPromptAuditLogger     $auditLogger,
    ) {}

    public function sanitize(string $prompt, string $tenantId = ''): string
    {
        if (! config('ai.security.injection_detection', true)) {
            return $this->redactor->redact($prompt);
        }

        $detection = $this->injectionDetector->detect($prompt);

        if ($detection['detected']) {
            $this->auditLogger->logInjectionAttempt($prompt, $tenantId, $detection);
            Log::warning('AI prompt injection attempt', [
                'tenant_id' => $tenantId,
                'risk'      => $detection['risk'],
                'patterns'  => count($detection['patterns']),
            ]);
            // Sanitize rather than reject — removes injection patterns
            $prompt = $this->injectionDetector->sanitize($prompt);
        }

        // Always redact sensitive data from user input
        return $this->redactor->redact($prompt);
    }

    public function sanitizeMessages(array $messages, string $tenantId = ''): array
    {
        return array_map(function ($message) use ($tenantId) {
            if (is_array($message) && isset($message['content'])) {
                $message['content'] = $this->sanitize($message['content'], $tenantId);
            } elseif (is_string($message)) {
                $message = $this->sanitize($message, $tenantId);
            }
            return $message;
        }, $messages);
    }

    public function isUnsafe(string $prompt, string $tenantId = ''): bool
    {
        $injection = $this->injectionDetector->detect($prompt);
        $hasSensitive = $this->redactor->containsSensitiveData($prompt);
        return $injection['detected'] || $hasSensitive;
    }

    public function enforce(string $prompt, string $tenantId = ''): string
    {
        $maxLen = config('ai.security.max_prompt_length', 8000);
        $prompt = mb_substr($prompt, 0, $maxLen);
        return $this->sanitize($prompt, $tenantId);
    }
}
