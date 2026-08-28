<?php

namespace SaaSPlatform\CoreUtils\Payment\Connection;

use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Contracts\PaymentConnectionSettingsResolverContract;

final class ConfigPaymentConnectionSettingsResolver implements PaymentConnectionSettingsResolverContract
{
    public function resolve(Request $request): array
    {
        return (array) config('payment-connection-warning', []);
    }
}
