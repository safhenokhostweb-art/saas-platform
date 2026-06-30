<?php

namespace App\Contracts;

interface DnsProviderInterface
{
    /**
     * Create an A record for the given subdomain pointing to $ip.
     * Returns ['success' => bool, 'record_id' => string|null, 'message' => string].
     */
    public function createARecord(string $subdomain, string $ip, int $ttl = 120): array;

    /**
     * Update an existing A record by its provider record ID.
     * Returns ['success' => bool, 'message' => string].
     */
    public function updateARecord(string $recordId, string $subdomain, string $ip, int $ttl = 120): array;

    /**
     * Delete a DNS record by provider record ID.
     * Returns ['success' => bool, 'message' => string].
     */
    public function deleteRecord(string $recordId): array;

    /**
     * Check whether an A record for the given subdomain exists.
     * Returns the record_id if found, or null.
     */
    public function findARecord(string $subdomain): ?string;

    /**
     * Poll until the given FQDN resolves to $expectedIp via public DNS.
     * Returns true if propagation confirmed, false if timed out.
     *
     * @param string $fqdn          Full domain name (subdomain + root domain)
     * @param string $expectedIp    IP address to match
     * @param int    $maxWaitSec    Maximum seconds to wait
     * @param int    $pollIntervalSec How often to poll
     */
    public function waitForPropagation(string $fqdn, string $expectedIp, int $maxWaitSec = 300, int $pollIntervalSec = 15): bool;

    /**
     * Test connectivity and token validity.
     * Returns ['ok' => bool, 'message' => string, 'domain_count' => int|null].
     */
    public function healthCheck(): array;
}
