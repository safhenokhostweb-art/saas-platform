<?php

namespace App\Services\CustomerInstallation\Dns;

/**
 * Result of one public DNS A lookup (CNAME chains already followed).
 *
 * NOT_FOUND is a definitive answer (NXDOMAIN, or NOERROR without any A
 * record). UNKNOWN means the lookup itself failed (resolver unreachable,
 * timeout, SERVFAIL), so nothing may be concluded about the domain.
 */
final class DnsLookup
{
    public const RESOLVED = 'resolved';
    public const NOT_FOUND = 'not_found';
    public const UNKNOWN = 'unknown';

    /** @param list<string> $ips */
    private function __construct(
        public readonly string $status,
        public readonly array $ips = [],
        public readonly ?string $cname = null,
    ) {}

    /** @param list<string> $ips */
    public static function resolved(array $ips, ?string $cname = null): self
    {
        $ips = array_values(array_unique($ips));
        sort($ips);

        return $ips === [] ? self::notFound() : new self(self::RESOLVED, $ips, $cname);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }

    public static function unknown(): self
    {
        return new self(self::UNKNOWN);
    }

    public function isResolved(): bool
    {
        return $this->status === self::RESOLVED;
    }
}
