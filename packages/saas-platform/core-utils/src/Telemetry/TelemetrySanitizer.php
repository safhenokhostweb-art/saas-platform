<?php

namespace SaaSPlatform\CoreUtils\Telemetry;

class TelemetrySanitizer
{
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'secret', 'token', 'key',
        'authorization', 'auth', 'license', 'license_key',
        'credential', 'credentials', 'api_key', 'apikey',
        'access_token', 'refresh_token', 'private_key',
        'client_secret', 'bearer', 'signature', 'hash',
    ];

    private const BEARER_PATTERN      = '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i';
    private const LICENSE_KEY_PATTERN = '/[A-Z0-9]{4,8}-[A-Z0-9]{4,8}-[A-Z0-9]{4,8}-[A-Z0-9]{4,8}/';

    public function sanitize(array $data): array
    {
        return $this->sanitizeRecursive($data);
    }

    public function sanitizeString(string $value): string
    {
        $value = preg_replace(self::BEARER_PATTERN, 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace(self::LICENSE_KEY_PATTERN, '[LICENSE-REDACTED]', $value) ?? $value;
        return $value;
    }

    private function sanitizeRecursive(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->sanitizeRecursive($value);
            } elseif (is_string($value)) {
                $result[$key] = $this->sanitizeString($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }
        return false;
    }
}
