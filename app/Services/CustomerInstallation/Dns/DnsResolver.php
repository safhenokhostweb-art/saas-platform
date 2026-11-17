<?php

namespace App\Services\CustomerInstallation\Dns;

/** Public DNS A-record lookups used by the installation DNS preflight. */
interface DnsResolver
{
    public function lookupA(string $host): DnsLookup;
}
