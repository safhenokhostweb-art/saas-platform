<?php

namespace SaaSPlatform\CoreUtils\Contracts;

use Illuminate\Http\Request;

interface PaymentConnectionSettingsResolverContract
{
    /** @return array<string, mixed> */
    public function resolve(Request $request): array;
}
