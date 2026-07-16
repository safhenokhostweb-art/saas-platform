<?php

namespace App\Services\CustomerInstallation\Ssl;

use App\Models\Infrastructure\DnsProviderSetting;

/**
 * Phase 15-B1 — single decision point for "is SSL for this domain already
 * handled by ArvanCloud CDN, outside this pipeline?". Consulted by
 * IssueSslStage (to skip-success instead of calling SslProvisionerFactory)
 * and ConfigureWebServerStage (to decide whether the existing shared
 * platform certificate is an architecturally correct HTTPS-block choice for
 * this specific domain, or whether the block must wait for a real per-domain
 * certificate) — see the Phase 15-A0 audit (docs/ARVAN_SSL_FINAL_AUDIT.md):
 * when ArvanCloud proxies a domain (`cloud_proxy=true` on the active
 * DnsProviderSetting), it terminates a real Let's Encrypt certificate at its
 * own edge and the origin's certificate is never seen by a browser — so the
 * origin can safely keep using any certificate, including the shared one.
 *
 * The `isDomainUnderRoot()` check duplicates ConfigureDnsStage's private
 * method of the same name intentionally rather than extracting a shared
 * helper: it is five lines of stable string comparison, and Phase 15-B1's
 * scope is limited to SSL-related files (see task scope) — ConfigureDnsStage
 * itself is intentionally left untouched to avoid any regression risk to
 * the DNS pipeline. If this logic ever needs to change, update both.
 */
class ArvanCdnDomainDecider
{
    /**
     * True only when: an active + ready DNS provider setting exists, its
     * cloud_proxy flag is on, AND $domain is a subdomain of its root_domain.
     * A domain that isn't a subdomain of the platform's own root domain (a
     * customer's own custom domain) is never Arvan-managed, regardless of
     * cloud_proxy — Arvan can only proxy domains whose DNS it actually hosts.
     */
    public function isManagedByArvan(string $domain): bool
    {
        $setting = DnsProviderSetting::active();

        if (!$setting || !$setting->isReady() || !$setting->cloud_proxy) {
            return false;
        }

        return $this->isDomainUnderRoot($domain, $setting->root_domain);
    }

    private function isDomainUnderRoot(string $fqdn, string $rootDomain): bool
    {
        $fqdn       = strtolower(trim($fqdn, '. '));
        $rootDomain = strtolower(trim($rootDomain, '. '));

        if ($fqdn === $rootDomain) {
            return false; // apex record — not a subdomain
        }

        return str_ends_with($fqdn, '.' . $rootDomain);
    }
}
