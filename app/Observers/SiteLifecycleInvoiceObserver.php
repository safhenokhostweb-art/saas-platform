<?php

namespace App\Observers;

use App\Models\SiteLifecycleInvoice;
use App\Services\Finance\FinanceLedgerService;
use Illuminate\Support\Facades\Log;

class SiteLifecycleInvoiceObserver
{
    public function updated(SiteLifecycleInvoice $invoice): void
    {
        if (!$invoice->wasChanged('status') || $invoice->status !== SiteLifecycleInvoice::STATUS_PAID) {
            return;
        }

        try {
            app(FinanceLedgerService::class)->fromLifecycleInvoice($invoice);
        } catch (\Throwable $e) {
            Log::warning('FinanceLedger: failed to record lifecycle invoice', ['invoice' => $invoice->id, 'err' => $e->getMessage()]);
        }
    }
}
