<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Finance\FinanceLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/**
 * Tenant-facing endpoint: customer-site pushes payment events to the platform ledger.
 * Route: POST /api/ledger/push-event
 * Auth: tenant.api middleware (license key)
 */
class FinanceLedgerApiController extends Controller
{
    public function __construct(
        private FinanceLedgerService $ledger,
        private SiteContextContract $siteContext,
    ) {}

    public function pushEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type'     => 'required|string|in:subscription_payment,installment_payment,order_payment',
            'amount'         => 'required|integer|min:1',
            'description'    => 'nullable|string|max:500',
            'reference_type' => 'nullable|string|max:100',
            'reference_id'   => 'nullable|integer',
            'gateway'        => 'nullable|string|max:50',
            'gateway_ref'    => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'entry_date'     => 'nullable|date',
            'transaction_at' => 'nullable|date',
        ]);

        $validated['site_id'] = $this->siteContext->requireSiteId();

        $entry = $this->ledger->fromCustomerSitePush($validated);

        return response()->json([
            'success'  => true,
            'recorded' => $entry !== null,
            'entry_id' => $entry?->id,
        ]);
    }
}
