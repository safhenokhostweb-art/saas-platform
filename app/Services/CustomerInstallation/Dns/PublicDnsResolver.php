<?php

namespace App\Services\CustomerInstallation\Dns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves through Google's public DNS-over-HTTPS (what Let's Encrypt and
 * visitors see, not a local cache), falling back to the system resolver
 * when DoH is unreachable. Same source ArvanDnsProvider::waitForPropagation
 * already uses.
 *
 * From the production host (2026-11-17) dns.google and cloudflare-dns.com
 * both time out while the system resolver works. So a connection failure
 * is detected quickly (2 s) and remembered for DOH_DOWN_TTL seconds. After
 * that, lookups go straight to the system resolver instead of paying the
 * timeout on every query.
 */
class PublicDnsResolver implements DnsResolver
{
    private const DOH_URL = 'https://dns.google/resolve';

    private const DOH_DOWN_KEY = 'customer_installation.dns.doh_unreachable';
    private const DOH_DOWN_TTL = 600;

    private const TYPE_A = 1;
    private const TYPE_CNAME = 5;
    private const TYPE_AAAA = 28;

    // RFC 1035 RCODEs reported in the DoH JSON "Status" field.
    private const RCODE_NOERROR = 0;
    private const RCODE_NXDOMAIN = 3;

    public function lookupA(string $host): DnsLookup
    {
        if (Cache::get(self::DOH_DOWN_KEY)) {
            return $this->nativeLookup($host);
        }

        try {
            $response = Http::connectTimeout(2)->timeout(5)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get(self::DOH_URL, ['name' => $host, 'type' => 'A']);
        } catch (ConnectionException) {
            Cache::put(self::DOH_DOWN_KEY, true, self::DOH_DOWN_TTL);

            return $this->nativeLookup($host);
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

    public function lookupAAAA(string $host): DnsLookup
    {
        if (Cache::get(self::DOH_DOWN_KEY)) {
            return $this->nativeLookupAAAA($host);
        }

        try {
            $response = Http::connectTimeout(2)->timeout(5)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get(self::DOH_URL, ['name' => $host, 'type' => 'AAAA']);
        } catch (ConnectionException) {
            Cache::put(self::DOH_DOWN_KEY, true, self::DOH_DOWN_TTL);

            return $this->nativeLookupAAAA($host);
        } catch (\Throwable) {
            return $this->nativeLookupAAAA($host);
        }

        if (!$response->successful() || !is_array($response->json())) {
            return $this->nativeLookupAAAA($host);
        }

        $status = $response->json('Status');
        if ($status === self::RCODE_NXDOMAIN) {
            return DnsLookup::notFound();
        }
        if ($status !== self::RCODE_NOERROR) {
            return DnsLookup::unknown();
        }

        $ips = [];
        foreach ((array) $response->json('Answer', []) as $answer) {
            $data = rtrim((string) ($answer['data'] ?? ''), '.');
            if ((int) ($answer['type'] ?? 0) === self::TYPE_AAAA && filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ips[] = strtolower($data);
            }
        }

        return DnsLookup::resolved($ips);
    }

    private function nativeLookup(string $host): DnsLookup
    {
        $records = @dns_get_record($host, DNS_A);
        if ($records === false) {
            return DnsLookup::unknown();
        }

        return DnsLookup::resolved(array_values(array_filter(array_column($records, 'ip'))));
    }

    private function nativeLookupAAAA(string $host): DnsLookup
    {
        $records = @dns_get_record($host, DNS_AAAA);
        if ($records === false) {
            return DnsLookup::unknown();
        }

        return DnsLookup::resolved(array_values(array_filter(array_map('strtolower', array_column($records, 'ipv6')))));
    }
}
