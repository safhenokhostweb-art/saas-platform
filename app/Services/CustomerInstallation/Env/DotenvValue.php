<?php

namespace App\Services\CustomerInstallation\Env;

use Dotenv\Dotenv;

/**
 * Encodes values for a customer site's .env exactly as vlucas/phpdotenv 5
 * (what Laravel reads) will decode them.
 *
 * Unquoted values are treacherous: phpdotenv starts a comment at ANY `#`,
 * not only after whitespace, so `DB_PASSWORD=aB3#x9` reads as `aB3`. That
 * truncated the database password of installation 51 (2026-11-17, MySQL
 * 1045 "using password: YES"). It hit about 36% of installations, because `#`
 * was in the password charset. `$` interpolates and whitespace ends the value.
 * Anything outside a plain safe set is therefore double-quoted, with `"`, `\`
 * and `$` escaped.
 */
final class DotenvValue
{
    public static function encode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_:\/+=.\-]+$/', $value) === 1) {
            return $value;
        }

        return '"' . addcslashes($value, '"\\$') . '"';
    }

    /**
     * What Laravel will see for these .env lines.
     *
     * @return array<string, string|null>
     */
    public static function parse(string $envContent): array
    {
        return Dotenv::parse($envContent);
    }

    /** `KEY=<encoded value>`, round-trip safe. */
    public static function line(string $key, string $value): string
    {
        return $key . '=' . self::encode($value);
    }
}
