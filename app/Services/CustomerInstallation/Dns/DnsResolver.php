<?php

namespace App\Services\CustomerInstallation\Dns;

/** Public DNS lookups used by the installation DNS preflight. */
interface DnsResolver
{
    public function lookupA(string $host): DnsLookup;

    /**
     * IPv6 (AAAA) records. Let's Encrypt prefers IPv6 when a name has one,
     * so an AAAA record that is not this server makes HTTP-01 fail even when
     * the A record is right.
     */
    public function lookupAAAA(string $host): DnsLookup;
}
