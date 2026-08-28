<?php

namespace SaaSPlatform\CoreUtils\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Payment\Connection\PaymentConnectionAdvisor;

final class PaymentConnectionPreflightController
{
    public function __invoke(Request $request, PaymentConnectionAdvisor $advisor): JsonResponse
    {
        $result = $advisor->advise($request, $request->boolean('override'));

        return response()->json($result)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
