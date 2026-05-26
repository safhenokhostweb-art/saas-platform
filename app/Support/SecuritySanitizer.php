<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * PHASE 8.0 — Utility for masking secrets in logs, dumps, and responses.
 *
 * Use mask() anywhere a secret value might appear in output.
 * Use scrubRequest() in middleware after credential validation to remove
 * secrets from the request bag before they reach controllers / Telescope.
 */
final class SecuritySanitizer
{
    /** Fields that must never appear in logs or debug output. */
    private const SENSITIVE_KEYS = [
        'license_key',
        'api_key',
        'secret',
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'private_key',
        'credit_card',
        'card_number',
        'cvv',
    ];

    /**
     * Return a masked version of a string, keeping only the first 4 chars visible.
     * Empty strings return '[empty]'. Null returns '[null]'.
     */
    public static function mask(?string $value, int $visibleChars = 4): string
    {
        if ($value === null) {
            return '[null]';
        }
        if ($value === '') {
            return '[empty]';
        }

        $visible = mb_substr($value, 0, $visibleChars);
        return $visible . str_repeat('*', max(0, mb_strlen($value) - $visibleChars));
    }

    /**
     * Recursively mask all sensitive keys inside an array (for log context).
     */
    public static function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = static::maskArray($value);
            } elseif (is_string($value) && static::isSensitiveKey((string) $key)) {
                $data[$key] = static::mask($value);
            }
        }

        return $data;
    }

    /**
     * Remove sensitive request inputs from the request bag.
     * Called AFTER validation so the values are no longer needed downstream.
     */
    public static function scrubRequest(Request $request): void
    {
        foreach (static::SENSITIVE_KEYS as $key) {
            if ($request->has($key)) {
                $request->request->remove($key);
                $request->query->remove($key);
            }
        }
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        foreach (static::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }
        return false;
    }
}
