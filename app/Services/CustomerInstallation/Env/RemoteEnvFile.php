<?php

namespace App\Services\CustomerInstallation\Env;

use InvalidArgumentException;

/**
 * Shell commands that read and write single keys of a customer site's .env
 * on the remote server, in step with DotenvValue (what Laravel will decode).
 *
 * set() is an upsert. It replaces the first `KEY=` line (dropping any
 * duplicates) whatever its old value, empty, quoted or placeholder, and
 * otherwise appends the key, adding a missing final newline first. The new
 * line travels base64-encoded and reaches awk through ENVIRON, so no shell,
 * sed or awk escape can alter it. The shell then confirms the exact line is
 * present and prints SET_OK or SET_FAILED.
 *
 * Commands are POSIX-quoted explicitly (escapeshellarg() quotes for the
 * platform's own OS), and the file is rewritten in place (`cat tmp > f`),
 * so ownership and mode are kept.
 */
final class RemoteEnvFile
{
    public const SET_OK = 'env-set-ok';
    public const SET_FAILED = 'env-set-failed';

    public static function set(string $envPath, string $key, string $value): string
    {
        self::assertKey($key);
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new InvalidArgumentException("Multi-line value for .env key {$key} is not supported.");
        }
        $line = DotenvValue::line($key, $value);

        return implode("\n", [
            'f=' . self::quote($envPath),
            'line=$(printf %s ' . self::quote(base64_encode($line)) . ' | base64 -d) || { echo ' . self::SET_FAILED . '; exit 1; }',
            "if grep -q '^{$key}=' \"\$f\"; then",
            "  L=\"\$line\" awk 'index(\$0, \"{$key}=\") == 1 { if (!done) print ENVIRON[\"L\"]; done = 1; next } { print }' \"\$f\" > \"\$f.cie-tmp\" && cat \"\$f.cie-tmp\" > \"\$f\"; rm -f \"\$f.cie-tmp\"",
            'else',
            '  if [ -s "$f" ] && [ -n "$(tail -c 1 "$f")" ]; then printf \'\n\' >> "$f"; fi',
            '  printf \'%s\n\' "$line" >> "$f"',
            'fi',
            'if grep -qxF -- "$line" "$f"; then echo ' . self::SET_OK . '; else echo ' . self::SET_FAILED . '; exit 1; fi',
        ]);
    }

    /** @param list<string> $keys */
    public static function read(string $envPath, array $keys): string
    {
        foreach ($keys as $key) {
            self::assertKey($key);
        }

        return "grep -E '^(" . implode('|', $keys) . ")=' " . self::quote($envPath) . ' 2>/dev/null';
    }

    /**
     * Values of read() output, decoded exactly as Laravel will see them.
     *
     * @return array<string, string|null>
     */
    public static function decode(string $readOutput): array
    {
        $readOutput = trim($readOutput);

        return $readOutput === '' ? [] : DotenvValue::parse($readOutput);
    }

    private static function assertKey(string $key): void
    {
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
            throw new InvalidArgumentException("Unsafe .env key: {$key}");
        }
    }

    private static function quote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}
