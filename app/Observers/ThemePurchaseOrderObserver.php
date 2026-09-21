<?php

namespace App\Observers;

use App\Models\ThemePurchaseOrder;
use App\Services\AdminNotificationService;
use App\Services\Finance\FinanceLedgerService;
use Illuminate\Support\Facades\Log;

class ThemePurchaseOrderObserver
{
    public function updated(ThemePurchaseOrder $order): void
    {
        if (! $order->wasChanged('status') || $order->status !== ThemePurchaseOrder::STATUS_PAID) {
            return;
        }

        try {
            app(FinanceLedgerService::class)->fromThemePurchase($order);
        } catch (\Throwable $exception) {
            Log::warning('FinanceLedger: failed to record theme purchase', [
                'order' => $order->id,
                'err' => $exception->getMessage(),
            ]);
        }

        AdminNotificationService::send(
            type: 'success',
            title: 'Theme purchase completed',
            body: "Theme [{$order->theme->name}] was purchased by site {$order->site_id}.",
            link: '/admin/theme-marketplace/licenses',
        );

        // Marketplace payment grants an entitlement only. The customer-site
        // downloads and installs later, after the customer explicitly
        // confirms replacement of the active Theme. Direct Support Install
        // deliberately remains a separate ThemeInstallGateway workflow.
    }
}
