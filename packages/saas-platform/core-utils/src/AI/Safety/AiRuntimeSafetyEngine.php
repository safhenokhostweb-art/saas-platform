<?php

namespace SaaSPlatform\CoreUtils\AI\Safety;

use Illuminate\Support\Facades\Cache;

class AiRuntimeSafetyEngine
{
    private array $blockedPatterns = [
        '/ignore (all |previous |above )?instructions/i',
        '/you are now/i',
        '/act as (a|an|the)/i',
        '/jailbreak/i',
        '/pretend (you are|to be)/i',
        '/override (your|the) (safety|guidelines|restrictions)/i',
        '/\bDAN\b/',
        '/do anything now/i',
        '/bypass (safety|filter|restriction)/i',
        '/system prompt/i',
        '/reveal (your|the) (system|instructions|prompt)/i',
        '/sudo/i',
        '/<\?php/i',
        '/eval\s*\(/i',
        '/exec\s*\(/i',
        '/shell_exec/i',
    ];

    private array $sensitiveDataPatterns = [
        '/\b\d{4}[- ]?\d{4}[- ]?\d{4}[- ]?\d{4}\b/',   // credit card
        '/\b\d{3}-\d{2}-\d{4}\b/',                       // SSN
        '/password\s*[:=]\s*\S+/i',
        '/api.?key\s*[:=]\s*\S+/i',
        '/secret\s*[:=]\s*\S+/i',
        '/bearer\s+[a-zA-Z0-9\-._~+\/]+=*/i',
    ];

    public function inspectRequest(array $payload, string $tenantId, string $operation): array
    {
        // Emergency kill switch
        if (config('ai.emergency.kill_switch', false)) {
            return ['allowed' => false, 'reason' => 'emergency_kill_switch'];
        }

        // Extract text content
        $text = $this->extractText($payload);

        // Prompt injection check
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $this->logViolation($tenantId, $operation, 'prompt_injection', $pattern);
                return ['allowed' => false, 'reason' => 'prompt_injection_detected'];
            }
        }

        // Sensitive data masking
        foreach ($this->sensitiveDataPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $this->logViolation($tenantId, $operation, 'sensitive_data', $pattern);
                // Don't block — just warn and mask at execution time
            }
        }

        // Abuse pattern — flood check
        $floodKey = "ai:safety:flood:{$tenantId}:{$operation}";
        $count = (int) Cache::get($floodKey, 0);
        if ($count > 100) {
            return ['allowed' => false, 'reason' => 'abuse_flood_detected'];
        }
        Cache::put($floodKey, $count + 1, now()->addMinutes(60));

        return ['allowed' => true];
    }

    public function inspectResponse(string $text, string $tenantId): array
    {
        $risks = [];

        // Hallucination risk indicators
        $hallucinations = ['I am not sure but', 'I think', 'possibly', 'might be', 'could be', 'I believe'];
        foreach ($hallucinations as $phrase) {
            if (stripos($text, $phrase) !== false) {
                $risks[] = 'potential_hallucination';
                break;
            }
        }

        // Cross-tenant leakage check
        if (preg_match('/tenant[_-]?id\s*[:=]\s*\d+/i', $text)) {
            $risks[] = 'potential_tenant_data_leak';
        }

        // Unsafe code detection
        if (preg_match('/<\?php|eval\s*\(|exec\s*\(|system\s*\(/i', $text)) {
            $risks[] = 'unsafe_code_detected';
        }

        $riskScore = count($risks) / 10.0;
        $confidence = max(0, 1 - $riskScore);

        return [
            'risks'      => $risks,
            'risk_score' => $riskScore,
            'confidence' => $confidence,
            'safe'       => empty($risks),
        ];
    }

    public function maskSensitiveData(string $text): string
    {
        foreach ($this->sensitiveDataPatterns as $pattern) {
            $text = preg_replace_callback($pattern, function ($m) {
                $len = strlen($m[0]);
                return str_repeat('*', min($len, 8)).'[REDACTED]';
            }, $text);
        }
        return $text;
    }

    private function extractText(array $payload): string
    {
        $parts = [];
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $parts[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_array($item) && isset($item['content'])) {
                        $parts[] = $item['content'];
                    } elseif (is_string($item)) {
                        $parts[] = $item;
                    }
                }
            }
        }
        return implode(' ', $parts);
    }

    private function logViolation(string $tenantId, string $operation, string $type, string $pattern): void
    {
        $key = "ai:safety:violations:{$tenantId}";
        $violations = Cache::get($key, []);
        $violations[] = [
            'type'       => $type,
            'operation'  => $operation,
            'pattern'    => substr($pattern, 0, 50),
            'at'         => now()->toISOString(),
        ];
        Cache::put($key, array_slice($violations, -100), now()->addDays(7));
    }
}
