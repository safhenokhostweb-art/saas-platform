<?php

namespace SaaSPlatform\CoreUtils\Payment\Connection;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Contracts\PaymentConnectionSettingsResolverContract;

final class PaymentConnectionAdvisor
{
    public function __construct(
        private readonly TrustedCountryHeaderResolver $countryResolver,
        private readonly PaymentConnectionSettingsResolverContract $settingsResolver,
    ) {}

    /** @return array{allowed: bool, requires_warning: bool, status: string} */
    public function advise(Request $request, bool $explicitOverride = false): array
    {
        $settings = $this->settingsResolver->resolve($request);
        if (! ($settings['enabled'] ?? true)) {
            return ['allowed' => true, 'requires_warning' => false, 'status' => 'disabled'];
        }

        $resolution = $this->countryResolver->resolve($request);
        if (! $resolution->trusted || $resolution->country === null) {
            Log::notice('payment_connection.country_unavailable', ['reason' => $resolution->reason]);

            return ['allowed' => true, 'requires_warning' => false, 'status' => 'unknown_fail_open'];
        }

        $exempt = array_map('strtoupper', (array) ($settings['exempt_countries'] ?? ['IR']));
        if (in_array($resolution->country, $exempt, true)) {
            return ['allowed' => true, 'requires_warning' => false, 'status' => 'domestic'];
        }

        $warningCountries = array_map('strtoupper', (array) ($settings['warning_countries'] ?? ['*']));
        $requiresWarning = in_array('*', $warningCountries, true)
            || in_array($resolution->country, $warningCountries, true);

        if (! $requiresWarning || $explicitOverride) {
            return [
                'allowed' => true,
                'requires_warning' => false,
                'status' => $explicitOverride ? 'foreign_overridden' : 'country_not_configured',
            ];
        }

        return ['allowed' => false, 'requires_warning' => true, 'status' => 'foreign_warning'];
    }
}
