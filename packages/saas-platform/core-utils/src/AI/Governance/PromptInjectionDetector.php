<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

class PromptInjectionDetector
{
    // Patterns that indicate prompt injection attempts
    private const INJECTION_PATTERNS = [
        // Direct instruction override attempts
        '/ignore\s+(?:all\s+)?(?:previous|above|prior)\s+instructions?/i',
        '/disregard\s+(?:all\s+)?(?:previous|above|prior)\s+instructions?/i',
        '/forget\s+(?:all\s+)?(?:your\s+)?(?:previous|prior)\s+instructions?/i',
        '/you\s+are\s+now\s+(?:a|an|the)\s+/i',
        '/act\s+as\s+(?:if\s+you\s+(?:are|were)\s+)?(?:a|an|the)\s+/i',
        '/pretend\s+(?:you\s+are|to\s+be)\s+/i',
        '/roleplay\s+as\s+/i',
        '/jailbreak/i',
        '/dan\s+mode/i',
        '/developer\s+mode/i',
        // System prompt exfiltration
        '/(?:show|reveal|print|output|repeat|tell\s+me)\s+(?:your\s+)?(?:system\s+)?(?:prompt|instructions?|rules?)/i',
        '/what\s+(?:are\s+your|were\s+your)\s+(?:instructions?|rules?|system\s+prompt)/i',
        // Context escape attempts
        '/\]\]\s*\[\[/i',
        '/<\/?(?:system|human|assistant|instructions?)>/i',
        '/SYSTEM:/i',
        '/ASSISTANT:/i',
        // Cross-tenant leakage fishing
        '/(?:other|another|different)\s+(?:tenant|customer|user|company)\s+(?:data|info|record)/i',
        '/show\s+(?:me\s+)?(?:other|all)\s+(?:users?|tenants?|customers?)/i',
    ];

    public function detect(string $text): array
    {
        if (! config('ai.security.injection_detection', true)) {
            return ['detected' => false, 'patterns' => []];
        }

        $detected = [];
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                $detected[] = $pattern;
            }
        }

        return [
            'detected' => ! empty($detected),
            'patterns' => $detected,
            'risk'     => $this->riskLevel(count($detected)),
        ];
    }

    public function isInjection(string $text): bool
    {
        return $this->detect($text)['detected'];
    }

    public function sanitize(string $text): string
    {
        // Remove or neutralize injection patterns
        $cleaned = $text;
        foreach (self::INJECTION_PATTERNS as $pattern) {
            $cleaned = preg_replace($pattern, '[filtered]', $cleaned);
        }
        return $cleaned;
    }

    private function riskLevel(int $matchCount): string
    {
        return match(true) {
            $matchCount === 0 => 'none',
            $matchCount === 1 => 'low',
            $matchCount <= 3  => 'medium',
            default           => 'high',
        };
    }
}
