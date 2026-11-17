<?php

namespace App\Services\CustomerInstallation\Dns;

use Illuminate\Support\Facades\Http;

/**
 * Resolves through Google's public DNS-over-HTTPS (what Let's Encrypt and
 * visitors see, not a local cache), falling back to the system resolver
 * when DoH is unreachable. Same source ArvanDnsProvider::waitForPropagation
 * already uses.
 */
class PublicDnsResolver implements DnsResolver
{
    private const DOH_URL = 'https://dns.google/resolve';

    private const TYPE_A = 1;
    private const TYPE_CNAME = 5;

    // RFC 1035 RCODEs reported in the DoH JSON "Status" field.
    private const RCODE_NOERROR = 0;
    private const RCODE_NXDOMAIN = 3;

    public function lookupA(string $host): DnsLookup
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get(self::DOH_URL, ['name' => $host, 'type' => 'A']);
        } catch (\Throwable) {
            return $this->nativeLookup($host);
        }

        if (!$response->successful() || !is_array($response->json())) {
            return $this->nativeLookup($host);
        }

        $status = $response->json('Status');
        if ($status === self::RCODE_NXDOMAIN) {
            return DnsLookup::notFound();
        }
        if ($status !== self::RCODE_NOERROR) {
            return DnsLookup::unknown(); // SERVFAIL, REFUSED, ...: inconclusive
        }

        $ips = [];
        $cname = null;
        foreach ((array) $response->json('Answer', []) as $answer) {
            $type = (int) ($answer['type'] ?? 0);
            $data = rtrim((string) ($answer['data'] ?? ''), '.');
            if ($type === self::TYPE_A && filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[] = $data;
            } elseif ($type === self::TYPE_CNAME && $cname === null) {
                $cname = strtolower($data);
            }
        }

        return DnsLookup::resolved($ips, $cname);
    }

    private function nativeLookup(string $host): DnsLookup
    {
        $records = @dns_get_record($host, DNS_A);
        if ($records === false) {
            return DnsLookup::unknown();
        }

        return DnsLookup::resolved(array_values(array_filter(array_column($records, 'ip'))));
    }
}
