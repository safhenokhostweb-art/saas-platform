<?php

namespace App\Services\CustomerDeletion\Guards;

/**
 * ADR-090 — a Certbot lineage name (`--cert-name`) a purge may delete: a
 * plain lowercase hostname, never an IP, wildcard, or the platform's own host.
 */
final class DomainGuard
{
    public static function assertCertificateName(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if (
            strlen($domain) > 253
            || !str_contains($domain, '.')
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
            || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/', $domain)
        ) {
            throw new PurgeGuardViolation("نام دامنهٔ گواهی معتبر نیست: «{$domain}»");
        }

        $platformHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($platformHost !== '' && in_array($domain, [$platformHost, 'www.' . $platformHost], true)) {
            throw new PurgeGuardViolation("گواهی دامنهٔ خود پلتفرم ({$domain}) هرگز حذف نمی‌شود.");
        }

        return $domain;
    }
}
