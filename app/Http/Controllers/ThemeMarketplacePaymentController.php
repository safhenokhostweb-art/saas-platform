<?php
namespace App\Http\Controllers;

use App\Models\ThemeLicense;
use App\Models\ThemeMarketplace;
use App\Models\ThemePurchaseOrder;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Http\Request;

/**
 * پرداخت قالب — callback از درگاه پرداخت پس از خرید
 */
class ThemeMarketplacePaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayResolver $gatewayResolver,
        protected PaymentGatewayFactory  $gatewayFactory
    ) {}

    public function callback(Request $request, int $order): \Illuminate\Http\RedirectResponse
    {
        $purchaseOrder = ThemePurchaseOrder::find($order);

        if (!$purchaseOrder || !$purchaseOrder->isPending()) {
            return $this->redirectBack($purchaseOrder, false, 'سفارش یافت نشد یا قبلاً پردازش شده.');
        }

        $gatewayName = $purchaseOrder->gateway ?? 'zarinpal';

        // Resolve which gateway handled this order
        $gateway = null;
        if ($purchaseOrder->gateway) {
            try {
                $gateway = $this->gatewayFactory->fromName($purchaseOrder->gateway);
            } catch (\Throwable) {}
        }
        if (!$gateway) {
            $gateway = $this->gatewayResolver->resolveForSite($purchaseOrder->site_id);
        }

        $authority = null;
        $orderId   = null;

        if ($gateway->name() === 'idpay') {
            $status   = $request->query('status') ?? $request->input('status');
            $authority = $request->query('id') ?? $request->input('id');
            $orderId   = $request->query('order_id') ?? $request->input('order_id');

            if ((int) $status !== 100 || empty($authority)) {
                $purchaseOrder->update(['status' => ThemePurchaseOrder::STATUS_FAILED]);
                return $this->redirectBack($purchaseOrder, false, 'پرداخت لغو یا ناموفق بود.');
            }
        } else {
            // zarinpal
            $authority = $request->query('Authority');
            $status    = $request->query('Status');

            if ($status !== 'OK' || empty($authority)) {
                $purchaseOrder->update(['status' => ThemePurchaseOrder::STATUS_CANCELLED]);
                return $this->redirectBack($purchaseOrder, false, 'پرداخت لغو شد.');
            }
        }

        $verify = $gateway->verify($authority, $purchaseOrder->amount_rial, $orderId);

        if (empty($verify['verified'])) {
            $purchaseOrder->update(['status' => ThemePurchaseOrder::STATUS_FAILED]);
            return $this->redirectBack($purchaseOrder, false, $verify['error'] ?? 'تأیید پرداخت ناموفق بود.');
        }

        $purchaseOrder->update([
            'status'      => ThemePurchaseOrder::STATUS_PAID,
            'gateway_ref' => $verify['ref'],
            'paid_at'     => now(),
        ]);

        // Grant license
        ThemeLicense::grant($purchaseOrder->site_id, $purchaseOrder->theme_id, $purchaseOrder->id);

        // Increment sales count
        ThemeMarketplace::where('theme_id', $purchaseOrder->theme_id)
            ->increment('sales_count');

        return $this->redirectBack($purchaseOrder, true, 'قالب با موفقیت خریداری شد. لایسنس صادر گردید.');
    }

    private function redirectBack(?ThemePurchaseOrder $order, bool $success, string $message): \Illuminate\Http\RedirectResponse
    {
        $redirectUrl = $order?->redirect_back_url;

        if ($redirectUrl) {
            $separator = str_contains($redirectUrl, '?') ? '&' : '?';
            $redirectUrl .= $separator . ($success ? 'purchased=1' : 'failed=1')
                . '&msg=' . urlencode($message);

            return redirect()->away($redirectUrl);
        }

        // Fallback: platform admin marketplace page
        return $success
            ? redirect()->route('admin.theme-marketplace.licenses')->with('success', $message)
            : redirect()->route('admin.theme-marketplace.licenses')->with('error', $message);
    }
}
