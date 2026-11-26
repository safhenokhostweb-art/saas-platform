<?php

namespace App\Http\Controllers;

use App\Models\CanvasAssetPurchaseOrder;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\SiteAssetGrant;
use App\Services\AssetProvisioning\CanvasAssetProvisioningService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

class CanvasAssetPaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayResolver $gatewayResolver,
        protected PaymentGatewayFactory $gatewayFactory,
        protected CanvasAssetProvisioningService $provisioningService,
    ) {}

    /**
     * Callback from payment gateway after purchasing a Canvas asset.
     */
    public function callback(Request $request, int $order): RedirectResponse
    {
        $purchaseOrder = CanvasAssetPurchaseOrder::find($order);

        if (!$purchaseOrder || !$purchaseOrder->isPending()) {
            return $this->redirectBack($purchaseOrder, false, 'سفارش یافت نشد یا قبلاً پردازش شده است.');
        }

        // Resolve gateway
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
            $status    = $request->query('status') ?? $request->input('status');
            $authority = $request->query('id') ?? $request->input('id');
            $orderId   = $request->query('order_id') ?? $request->input('order_id');

            if ((int) $status !== 100 || empty($authority)) {
                $purchaseOrder->update(['status' => CanvasAssetPurchaseOrder::STATUS_FAILED]);
                return $this->redirectBack($purchaseOrder, false, 'پرداخت لغو یا ناموفق بود.');
            }
        } else {
            // Zarinpal / standard
            $authority = $request->query('Authority') ?? $request->input('Authority');
            $status    = $request->query('Status') ?? $request->input('Status');

            if (strtoupper((string) $status) !== 'OK' || empty($authority)) {
                $purchaseOrder->update(['status' => CanvasAssetPurchaseOrder::STATUS_CANCELLED]);
                return $this->redirectBack($purchaseOrder, false, 'پرداخت توسط کاربر لغو شد.');
            }
        }

        $verify = $gateway->verify($authority, $purchaseOrder->amount_rial, $orderId);

        if (empty($verify['verified'])) {
            $purchaseOrder->update(['status' => CanvasAssetPurchaseOrder::STATUS_FAILED]);
            return $this->redirectBack($purchaseOrder, false, $verify['error'] ?? 'تأیید پرداخت از سوی درگاه ناموفق بود.');
        }

        $ref = $verify['ref'] ?? ('REF-' . time());

        $purchaseOrder->update([
            'status'      => CanvasAssetPurchaseOrder::STATUS_PAID,
            'gateway_ref' => $ref,
            'paid_at'     => now(),
        ]);

        if ($purchaseOrder->invoice_id) {
            Invoice::where('id', $purchaseOrder->invoice_id)->update([
                'status'      => Invoice::STATUS_PAID,
                'gateway_ref' => $ref,
                'paid_at'     => now(),
            ]);
        }

        // Issue license grant
        $site = Site::find($purchaseOrder->site_id);
        if ($site) {
            $this->provisioningService->grantToSite(
                $site,
                $purchaseOrder->asset_kind,
                $purchaseOrder->asset_key,
                $purchaseOrder->asset_version ?: '1.0.0',
                SiteAssetGrant::SOURCE_PURCHASE,
                'خرید آنلاین از مارکت‌پلیس (کد پیگیری: ' . $ref . ')',
                null
            );
        }

        return $this->redirectBack($purchaseOrder, true, 'پرداخت با موفقیت انجام شد و دسترسی فعال گردید.', $ref);
    }

    protected function redirectBack(?CanvasAssetPurchaseOrder $order, bool $success, string $message, ?string $ref = null): RedirectResponse
    {
        $redirectUrl = $order?->redirect_back_url;

        $tab = match ($order?->asset_kind) {
            AssetRef::KIND_BUTTON_PACK => 'buttons',
            AssetRef::KIND_ICON_PACK => 'icons',
            AssetRef::KIND_HEADER => 'headers',
            AssetRef::KIND_MOTION => 'motions',
            default => 'buttons',
        };

        if ($redirectUrl) {
            $separator = str_contains($redirectUrl, '?') ? '&' : '?';
            $redirectUrl .= $separator . 'tab=' . $tab
                . ($success ? '&payment=success' : '&payment=failed')
                . ($ref ? '&ref=' . urlencode($ref) : '')
                . '&msg=' . urlencode($message);

            return redirect()->away($redirectUrl);
        }

        // Fallback: platform admin canvas assets page
        return $success
            ? redirect()->route('admin.canvas-assets.index', ['tab' => $tab])->with('success', $message)
            : redirect()->route('admin.canvas-assets.index', ['tab' => $tab])->with('error', $message);
    }
}
