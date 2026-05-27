<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

class SensitiveDataRedactor
{
    private const REDACT_PLACEHOLDER = '[REDACTED]';

    // Regex patterns for common sensitive data
    private const PATTERNS = [
        'api_key'       => '/\b(?:api[_-]?key|access[_-]?token|secret[_-]?key)\s*[:=]\s*[\w\-\.]{10,}/i',
        'bearer_token'  => '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
        'jwt'           => '/eyJ[A-Za-z0-9\-_]+\.eyJ[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_.+\/]*/i',
        'password'      => '/\b(?:password|passwd|pwd)\s*[:=]\s*\S+/i',
        'credit_card'   => '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|6(?:011|5[0-9]{2})[0-9]{12})\b/',
        'ssn'           => '/\b\d{3}[-\s]?\d{2}[-\s]?\d{4}\b/',
        'phone_ir'      => '/\b09[0-9]{9}\b/',
        'national_id_ir'=> '/\b[0-9]{10}\b/',
        'license_key'   => '/\b[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}\b/',
        'private_key'   => '/-----BEGIN (?:RSA )?PRIVATE KEY-----.*?-----END (?:RSA )?PRIVATE KEY-----/s',
    ];

    // Field names whose values must always be redacted
    private const SENSITIVE_FIELDS = [
        'password', 'secret', 'token', 'api_key', 'access_token',
        'refresh_token', 'private_key', 'client_secret', 'authorization',
        'license_key', 'credit_card', 'card_number', 'cvv', 'ssn',
    ];

    public function redact(string $text): string
    {
        if (! config('ai.security.data_redaction', true)) {
            return $text;
        }

        foreach (self::PATTERNS as $type => $pattern) {
            $text = preg_replace($pattern, "[{$type}:REDACTED]", $text);
        }

        return $text;
    }

    public function redactArray(array $data, bool $deep = true): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $result[$key] = self::REDACT_PLACEHOLDER;
            } elseif ($deep && is_array($value)) {
                $result[$key] = $this->redactArray($value, true);
            } elseif (is_string($value)) {
                $result[$key] = $this->redact($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function isSensitiveKey(string $key): bool
    {
        $lower = mb_strtolower($key);
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (str_contains($lower, $field)) {
                return true;
            }
        }
        return false;
    }

    public function containsSensitiveData(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }
}
