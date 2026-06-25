<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\Finance\FinanceLedgerService;
use Illuminate\Support\Facades\Log;

class InvoiceLedgerObserver
{
    public function updated(Invoice $invoice): void
    {
        if (!$invoice->wasChanged('status') || $invoice->status !== Invoice::STATUS_PAID) {
            return;
        }

        try {
            app(FinanceLedgerService::class)->fromInvoice($invoice);
        } catch (\Throwable $e) {
            Log::warning('FinanceLedger: failed to record invoice', ['invoice' => $invoice->id, 'err' => $e->getMessage()]);
        }
    }
}
