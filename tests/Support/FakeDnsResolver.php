<?php

namespace Tests\Support;

use App\Services\CustomerInstallation\Dns\DnsLookup;
use App\Services\CustomerInstallation\Dns\DnsResolver;

/**
 * In-memory DNS for tests. Exact hosts win, then a wildcard for the parent
 * zone (`*.parent`), then the default: UNKNOWN unless a test says otherwise,
 * which the DNS preflight treats as "could not verify" and does not block.
 */
class FakeDnsResolver implements DnsResolver
{
    /** @var list<string> */
    public array $queries = [];

    /** @var array<string, DnsLookup|list<DnsLookup>> */
    private array $hosts = [];

    /** @var array<string, DnsLookup> */
    private array $wildcards = [];

    public function __construct(private ?DnsLookup $default = null) {}

    /** A list of lookups is answered in order, the last one repeating. */
    public function set(string $host, DnsLookup|array $lookup): self
    {
        $this->hosts[strtolower($host)] = $lookup;

        return $this;
    }

    public function setWildcard(string $parentZone, DnsLookup $lookup): self
    {
        $this->wildcards[strtolower($parentZone)] = $lookup;

        return $this;
    }

    public function lookupA(string $host): DnsLookup
    {
        $host = strtolower($host);
        $this->queries[] = $host;

        if (isset($this->hosts[$host])) {
            $answer = $this->hosts[$host];
            if (is_array($answer)) {
                return count($answer) > 1 ? array_shift($this->hosts[$host]) : $answer[0];
            }

            return $answer;
        }

        $dot = strpos($host, '.');
        if ($dot !== false && isset($this->wildcards[substr($host, $dot + 1)])) {
            return $this->wildcards[substr($host, $dot + 1)];
        }

        return $this->default ?? DnsLookup::unknown();
    }
}
