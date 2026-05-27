<?php

namespace SaaSPlatform\CoreUtils\AI\Safety;

class AiResponseRiskAnalyzer
{
    private const RISK_WEIGHTS = [
        'hallucination'       => 0.3,
        'tenant_data_leak'    => 1.0,
        'unsafe_content'      => 0.8,
        'low_confidence'      => 0.2,
        'pii_exposure'        => 0.9,
        'off_topic'           => 0.1,
    ];

    public function analyze(string $response, string $tenantId, string $domain, float $providerConfidence = 1.0): array
    {
        $risks  = [];
        $score  = 0.0;

        // Confidence too low
        if ($providerConfidence < 0.5) {
            $risks[] = ['type' => 'low_confidence', 'detail' => "Provider confidence: {$providerConfidence}"];
            $score += self::RISK_WEIGHTS['low_confidence'];
        }

        // Tenant ID leak check
        if (preg_match('/site_id|tenant_id|reseller_id/i', $response)) {
            $risks[] = ['type' => 'tenant_data_leak', 'detail' => 'Potential internal field reference in response'];
            $score += self::RISK_WEIGHTS['tenant_data_leak'];
        }

        // PII exposure
        if (preg_match('/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/', $response)) {
            $risks[] = ['type' => 'pii_exposure', 'detail' => 'Email address detected in response'];
            $score += self::RISK_WEIGHTS['pii_exposure'];
        }

        // Unsafe content indicators
        $unsafeTerms = ['<script', 'javascript:', 'onload=', 'onerror=', 'eval(', '<?php'];
        foreach ($unsafeTerms as $term) {
            if (stripos($response, $term) !== false) {
                $risks[] = ['type' => 'unsafe_content', 'detail' => "Unsafe term: {$term}"];
                $score += self::RISK_WEIGHTS['unsafe_content'];
                break;
            }
        }

        // Hallucination indicators
        $hedges = ['I am not certain', 'I cannot verify', 'I am not sure', 'may not be accurate'];
        foreach ($hedges as $hedge) {
            if (stripos($response, $hedge) !== false) {
                $risks[] = ['type' => 'hallucination', 'detail' => "Hedge phrase: {$hedge}"];
                $score += self::RISK_WEIGHTS['hallucination'];
                break;
            }
        }

        $riskLevel = match(true) {
            $score >= 0.8 => 'critical',
            $score >= 0.5 => 'high',
            $score >= 0.2 => 'medium',
            default       => 'low',
        };

        return [
            'risk_level'  => $riskLevel,
            'risk_score'  => round($score, 3),
            'risks'       => $risks,
            'safe'        => $score < 0.5,
            'confidence'  => round(max(0, 1 - $score), 3),
        ];
    }
}
