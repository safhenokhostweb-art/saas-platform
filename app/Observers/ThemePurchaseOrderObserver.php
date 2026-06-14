<?php

namespace App\Observers;

use App\Models\ThemePurchaseOrder;
use App\Services\AdminNotificationService;

class ThemePurchaseOrderObserver
{
    public function updated(ThemePurchaseOrder $order): void
    {
        if ($order->isDirty('status') && $order->status === 'paid') {
            AdminNotificationService::send(
                type: 'success',
                title: 'خرید قالب انجام شد',
                body: "قالب «{$order->theme->name}» توسط سایت {$order->site_id} خریداری شد.",
                link: "/admin/theme-marketplace/licenses"
            );
        }
    }
}
