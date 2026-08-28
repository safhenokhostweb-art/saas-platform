<?php

namespace SaaSPlatform\CoreUtils\Payment\Connection;

use Illuminate\Http\Request;

final class TrustedCountryHeaderResolver
{
    public function __construct(private readonly CidrMatcher $cidrMatcher) {}

    public function resolve(Request $request): CountryResolution
    {
        if (! config('payment-connection-warning.country_header_verified', false)) {
            return CountryResolution::unknown('country_header_provenance_unverified');
        }

        $peer = trim((string) $request->header(config('payment-connection-warning.origin_peer_header')));
        if (filter_var($peer, FILTER_VALIDATE_IP) === false) {
            return CountryResolution::unknown('origin_peer_missing_or_invalid');
        }

        $cidrs = $this->trustedCidrs();
        if ($cidrs === []) {
            return CountryResolution::unknown('trusted_proxy_list_unavailable');
        }

        if (! $this->cidrMatcher->contains($peer, $cidrs)) {
            return CountryResolution::unknown('origin_peer_untrusted');
        }

        $country = strtoupper(trim((string) $request->header(config('payment-connection-warning.country_header'))));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return CountryResolution::unknown('country_header_missing_or_invalid');
        }

        return CountryResolution::trusted($country);
    }

    /** @return array<int, string> */
    private function trustedCidrs(): array
    {
        $cidrs = config('payment-connection-warning.trusted_proxy_cidrs', []);
        $file = (string) config('payment-connection-warning.trusted_proxy_cidr_file', '');

        if ($file !== '' && is_readable($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $candidate = trim(preg_replace('/\s+#.*$/', '', $line) ?? '');
                if ($candidate !== '') {
                    $cidrs[] = $candidate;
                }
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', is_array($cidrs) ? $cidrs : []))));
    }
}
