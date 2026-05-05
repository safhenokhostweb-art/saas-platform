<?php

namespace App\Services;

use App\Models\Reseller;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * تشخیص tenant از دامنه یا زیردامنهٔ درخواست.
 * از ResellerDetectionService برای Reseller استفاده می‌کند.
 */
class TenantResolver
{
    public function __construct(
        protected ResellerDetectionService $resellerDetection
    ) {}

    /**
     * تشخیص tenant از درخواست (دامنه/زیردامنه).
     * Reseller: custom_domain یا subdomain.platform.com
     */
    public function resolveFromRequest(Request $request): ?object
    {
        if (! config('tenant.resolver.reseller_from_domain', true)) {
            return null;
        }

        $reseller = $this->resellerDetection->fromRequest($request);
        if ($reseller) {
            return $reseller;
        }

        return null;
    }

    /**
     * تشخیص Reseller از host.
     */
    public function resolveResellerFromHost(string $host): ?Reseller
    {
        $host = strtolower(trim($host));

        $byDomain = Reseller::where('is_white_label', true)
            ->whereNotNull('custom_domain')
            ->where('custom_domain', $host)
            ->first();

        if ($byDomain) {
            return $byDomain;
        }

        $platformHost = $this->platformHost();
        if ($platformHost && str_ends_with($host, '.' . $platformHost)) {
            $subdomain = substr($host, 0, -strlen('.' . $platformHost));
            if ($subdomain !== '' && ! in_array($subdomain, config('tenant.resolver.ignore_subdomains', []), true)) {
                return Reseller::where('is_white_label', true)
                    ->where('subdomain', $subdomain)
                    ->first();
            }
        }

        return null;
    }

    protected function platformHost(): ?string
    {
        $url = config('app.url');
        if (! $url) {
            return null;
        }
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return null;
        }
        return strtolower($parsed['host']);
    }

    /**
     * نوع tenant را برگردان.
     */
    public function getTenantType(?object $tenant): ?string
    {
        if ($tenant === null) {
            return null;
        }
        if ($tenant instanceof Reseller) {
            return 'reseller';
        }
        if ($tenant instanceof Site) {
            return 'site';
        }
        return null;
    }
}
