<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThemeBundleOrder;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Http\Request;

class ThemeBundlePaymentCallbackController extends Controller
{
    public function __construct(
        protected PaymentGatewayResolver $gatewayResolver,
        protected PaymentGatewayFactory  $gatewayFactory
    ) {}

    public function handle(Request $request)
    {
        // Detect gateway and authority from query string
        $authority = $request->query('Authority') ?? $request->query('id') ?? '';
        $status    = $request->query('Status')    ?? $request->query('status') ?? '';
        $orderId   = $request->query('order_id')  ?? null;

        // Find pending order by gateway authority
        $order = ThemeBundleOrder::where('gateway_authority', $authority)
            ->where('status', ThemeBundleOrder::STATUS_PENDING)
            ->with('bundle')
            ->first();

        // Fallback: match via order_id param (IDPay style)
        if (!$order && $orderId) {
            $order = ThemeBundleOrder::find((int) $orderId);
            if ($order && $order->status !== ThemeBundleOrder::STATUS_PENDING) {
                $order = null;
            }
        }

        if (!$order) {
            return redirect('/admin/theme-marketplace')->with('error', 'سفارش باندل یافت نشد.');
        }

        $redirectBack = $order->redirect_back_url ?? '/admin/theme-marketplace/my-licenses';

        // Resolve gateway
        $gateway = null;
        if ($order->gateway) {
            try {
                $gateway = $this->gatewayFactory->fromName($order->gateway);
            } catch (\Throwable) {}
        }
        if (!$gateway) {
            $gateway = $this->gatewayResolver->resolveForSite($order->site_id);
        }

        // IDPay cancel/fail check
        if ($gateway->name() === 'idpay') {
            if ((int) $status !== 100 || empty($authority)) {
                $order->update(['status' => ThemeBundleOrder::STATUS_FAILED]);
                return redirect($redirectBack)->with('error', 'پرداخت باندل لغو یا ناموفق بود.');
            }
        } else {
            // Zarinpal
            if (strtoupper($status) !== 'OK' || empty($authority)) {
                $order->update(['status' => ThemeBundleOrder::STATUS_FAILED]);
                return redirect($redirectBack)->with('error', 'پرداخت باندل لغو شد.');
            }
        }

        try {
            $verify = $gateway->verify($authority, $order->amount_rial, $orderId);

            if (empty($verify['verified'])) {
                $order->update(['status' => ThemeBundleOrder::STATUS_FAILED]);
                return redirect($redirectBack)->with('error', $verify['error'] ?? 'تایید پرداخت ناموفق بود.');
            }

            $order->update(['status' => ThemeBundleOrder::STATUS_PAID]);
            $count = $order->bundle->grantLicensesToSite($order->site_id);

            return redirect($redirectBack)->with('success', "باندل «{$order->bundle->name}» خریداری شد. {$count} لایسنس صادر گردید.");

        } catch (\Throwable) {
            $order->update(['status' => ThemeBundleOrder::STATUS_FAILED]);
            return redirect($redirectBack)->with('error', 'خطا در تایید پرداخت باندل.');
        }
    }
}
