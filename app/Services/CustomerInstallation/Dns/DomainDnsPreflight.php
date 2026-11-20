<?php

namespace App\Services\CustomerInstallation\Dns;

use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\Ssl\ArvanRealIpSnippet;

/**
 * Decides, before anything is uploaded or created, whether a domain can
 * actually reach the target server. The onboarding wizard and the legacy
 * start form call it on submit, and VALIDATE_DOMAIN_DNS calls it again at
 * the start of the pipeline.
 *
 * Platform subdomains (under the active DNS provider's root domain) get
 * their A record from CONFIGURE_DNS. They only need a known public server
 * IP and are not looked up here (see check()). Any other domain must have
 * its own A/CNAME record, not just a wildcard match. With SSL it must point
 * at this server (or at ArvanCloud's CDN edge), because the ACME HTTP-01
 * challenge is fetched from wherever the domain resolves.
 *
 * A lookup that fails (DoH and system resolver unreachable) does not block:
 * it proves nothing about the domain, and the pipeline's later stages still
 * report a real failure.
 */
class DomainDnsPreflight
{
    public const PASS_MANAGED = 'managed_subdomain';
    public const PASS_POINTS_HERE = 'points_to_server';
    public const PASS_UNVERIFIED = 'dns_unverified';
    public const PASS_NO_SSL_ELSEWHERE = 'points_elsewhere_without_ssl';
    public const PASS_SERVER_IP_UNKNOWN = 'server_ip_unknown';

    public const FAIL_NOT_REGISTERED = 'dns_not_registered';
    public const FAIL_WRONG_IP = 'dns_points_elsewhere';
    public const FAIL_WILDCARD_ONLY = 'dns_wildcard_only';
    public const FAIL_NO_SERVER_IP = 'server_ip_missing';
    public const FAIL_AAAA_CONFLICT = 'dns_aaaa_conflict';

    /**
     * Failures a customer fixes in their own DNS panel. A custom domain that
     * fails with one of these can still be registered in the awaiting-DNS
     * state (CustomerInstallRequest::$dnsPending); a platform subdomain never.
     */
    public const AWAITING_DNS_CODES = [
        self::FAIL_NOT_REGISTERED,
        self::FAIL_WRONG_IP,
        self::FAIL_WILDCARD_ONLY,
        self::FAIL_AAAA_CONFLICT,
    ];

    public function __construct(private readonly DnsResolver $dns) {}

    public static function isAwaitingDnsCode(string $code): bool
    {
        return in_array($code, self::AWAITING_DNS_CODES, true);
    }

    /**
     * The "www." name served (and redirected to the bare domain) next to a
     * customer's own domain. Platform subdomains get none: their DNS is ours
     * and nobody types www.shop.safhenokhost.ir.
     */
    public function wwwAlias(string $domain): ?string
    {
        $domain = strtolower(trim($domain, ". \t\n"));
        if ($domain === '' || str_starts_with($domain, 'www.') || !str_contains($domain, '.') || $this->isPlatformManaged($domain)) {
            return null;
        }

        return 'www.' . $domain;
    }

    public function check(string $domain, ?string $serverIp, bool $sslEnabled): DnsPreflightResult
    {
        $domain = strtolower(trim(preg_replace('#^https?://#i', '', $domain) ?? '', "/. \t\n"));

        if ($this->isPlatformManaged($domain)) {
            // No public lookup here. Through a caching resolver (production
            // falls back to the ISP's, because DoH is unreachable there) a
            // "not found" answer would be cached negatively and could hide
            // the record CONFIGURE_DNS creates moments later. CONFIGURE_DNS
            // creates or updates the record through the Arvan API and then
            // verifies that it resolves to this server.
            $notQueried = DnsLookup::notQueried();
            if ($serverIp === null) {
                return DnsPreflightResult::fail(self::FAIL_NO_SERVER_IP,
                    'IP عمومی سرور مقصد مشخص نیست، پس رکورد DNS زیردامنه را نمی‌توان ساخت. در تنظیمات سرور، آدرس (host) را IP عمومی یا نام دامنهٔ قابل‌دسترس آن قرار دهید.',
                    $domain, $serverIp, $notQueried, true);
            }

            return DnsPreflightResult::pass(self::PASS_MANAGED, $domain, $serverIp, $notQueried, true,
                ["CONFIGURE_DNS creates the A record to {$serverIp} and verifies it before any upload."]);
        }

        $managed = false;
        $lookup = $this->dns->lookupA($domain);

        if ($lookup->status === DnsLookup::UNKNOWN) {
            return DnsPreflightResult::pass(self::PASS_UNVERIFIED, $domain, $serverIp, $lookup, $managed,
                ['DNS lookup failed (resolver unreachable); not blocking.']);
        }

        $pointsHere = $lookup->isResolved() && $this->pointsHere($lookup, $serverIp);
        $viaWildcard = $lookup->isResolved() && $this->matchesWildcard($domain, $lookup);

        if (!$lookup->isResolved()) {
            $target = $serverIp !== null ? " به IP این سرور ({$serverIp})" : '';

            return DnsPreflightResult::fail(self::FAIL_NOT_REGISTERED,
                "دامنه یا زیردامنهٔ «{$domain}» هنوز در DNS ثبت نشده است (هیچ رکورد A یا CNAME معتبری ندارد). ابتدا رکورد A آن را{$target} تنظیم کنید و پس از انتشار DNS دوباره تلاش کنید.",
                $domain, $serverIp, $lookup, $managed);
        }

        if ($pointsHere) {
            $notes = $viaWildcard ? ['Resolves through a wildcard record that points to this server.'] : [];
            $viaCdnEdge = $serverIp === null || !in_array($serverIp, $lookup->ips, true);
            if ($viaCdnEdge) {
                $notes[] = 'Resolves to ArvanCloud CDN edge addresses; the CDN origin must be this server.';
            }

            // Behind the CDN the edge answers IPv6 too. Pointed straight at
            // this IPv4 server, any AAAA record sends Let's Encrypt elsewhere.
            if ($sslEnabled && !$viaCdnEdge && ($ipv6 = $this->aaaaRecords($domain)) !== []) {
                $list = implode('، ', $ipv6);

                return DnsPreflightResult::fail(self::FAIL_AAAA_CONFLICT,
                    "رکورد A دامنهٔ «{$domain}» درست به IP این سرور ({$serverIp}) اشاره می‌کند، اما رکورد AAAA (IPv6) هم دارد: {$list}. Let's Encrypt برای اعتبارسنجی IPv6 را ترجیح می‌دهد و گواهی SSL صادر نمی‌شود. رکورد AAAA را در پنل DNS حذف کنید (یا به IPv6 همین سرور تغییر دهید).",
                    $domain, $serverIp, $lookup, $managed);
            }

            return DnsPreflightResult::pass(self::PASS_POINTS_HERE, $domain, $serverIp, $lookup, $managed, $notes);
        }

        if ($viaWildcard) {
            $parent = substr($domain, strpos($domain, '.') + 1);
            $here = $serverIp !== null ? " که IP این سرور ({$serverIp}) نیست" : '';

            return DnsPreflightResult::fail(self::FAIL_WILDCARD_ONLY,
                "زیردامنهٔ «{$domain}» رکورد مستقلی در DNS ندارد و فقط رکورد Wildcard (*.{$parent}) با {$this->ipList($lookup)} پاسخ می‌دهد{$here}. برای آن یک رکورد A مستقل به IP این سرور ثبت کنید.",
                $domain, $serverIp, $lookup, $managed);
        }

        if ($serverIp === null) {
            return DnsPreflightResult::pass(self::PASS_SERVER_IP_UNKNOWN, $domain, $serverIp, $lookup, $managed,
                ['Server public IP unknown; the record target could not be compared.']);
        }

        if ($sslEnabled) {
            return DnsPreflightResult::fail(self::FAIL_WRONG_IP,
                "دامنه یا زیردامنهٔ «{$domain}» به IP این سرور متصل نیست: در DNS به {$this->ipList($lookup)} اشاره می‌کند، اما IP سرور مقصد {$serverIp} است. تا رکورد A اصلاح نشود، گواهی SSL صادر نمی‌شود.",
                $domain, $serverIp, $lookup, $managed);
        }

        return DnsPreflightResult::pass(self::PASS_NO_SSL_ELSEWHERE, $domain, $serverIp, $lookup, $managed,
            ["Points to {$this->ipList($lookup)}, not {$serverIp}; allowed because SSL is not requested."]);
    }

    public function isPlatformManaged(string $domain): bool
    {
        $setting = DnsProviderSetting::active();
        if (!$setting || !$setting->isReady()) {
            return false;
        }

        $root = strtolower(trim((string) $setting->root_domain, '. '));

        return $root !== '' && $domain !== $root && str_ends_with($domain, '.' . $root);
    }

    /** The server's own IP, or (CDN-proxied) only ArvanCloud edge addresses. */
    public function pointsHere(DnsLookup $lookup, ?string $serverIp): bool
    {
        if ($serverIp !== null && in_array($serverIp, $lookup->ips, true)) {
            return true;
        }

        return $lookup->ips !== [] && array_reduce($lookup->ips, fn (bool $all, string $ip) => $all && self::isArvanEdge($ip), true);
    }

    public static function isArvanEdge(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        foreach (ArvanRealIpSnippet::RANGES as $range) {
            [$network, $bits] = explode('/', $range);
            $mask = (~0 << (32 - (int) $bits)) & 0xFFFFFFFF;
            if (($long & $mask) === (ip2long($network) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when a random sibling label resolves to the same addresses, so the
     * answer may come from a wildcard record rather than one for this name.
     */
    private function matchesWildcard(string $domain, DnsLookup $lookup): bool
    {
        $dot = strpos($domain, '.');
        $parent = $dot === false ? '' : substr($domain, $dot + 1);
        if (!str_contains($parent, '.')) {
            return false; // never probe directly under a TLD
        }

        $probe = $this->dns->lookupA('cie-dns-probe-' . bin2hex(random_bytes(6)) . '.' . $parent);

        return $probe->isResolved() && $probe->ips === $lookup->ips;
    }

    /** @return list<string> IPv6 addresses; none when the lookup failed (proves nothing). */
    private function aaaaRecords(string $domain): array
    {
        $lookup = $this->dns->lookupAAAA($domain);

        return $lookup->isResolved() ? $lookup->ips : [];
    }

    private function ipList(DnsLookup $lookup): string
    {
        return implode('، ', $lookup->ips);
    }
}
