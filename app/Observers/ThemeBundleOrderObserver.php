<?php

namespace App\Observers;

use App\Models\ThemeBundleOrder;
use App\Services\Finance\FinanceLedgerService;
use Illuminate\Support\Facades\Log;

class ThemeBundleOrderObserver
{
    public function updated(ThemeBundleOrder $order): void
    {
        if (!$order->wasChanged('status') || $order->status !== ThemeBundleOrder::STATUS_PAID) {
            return;
        }

        try {
            app(FinanceLedgerService::class)->fromBundlePurchase($order);
        } catch (\Throwable $e) {
            Log::warning('FinanceLedger: failed to record bundle purchase', ['order' => $order->id, 'err' => $e->getMessage()]);
        }
    }
}
