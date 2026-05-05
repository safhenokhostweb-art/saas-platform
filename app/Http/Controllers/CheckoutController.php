<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderFulfillmentService;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected PaymentGatewayResolver $gatewayResolver,
        protected PaymentGatewayFactory $gatewayFactory,
        protected OrderFulfillmentService $fulfillment
    ) {}

    public function process(Request $request)
    {
        $customer = auth()->user()->customer;
        if (!$customer) {
            return redirect()->route('dashboard')->with('error', 'پروفایل مشتری یافت نشد.');
        }

        $details = $this->cart->details();
        if (empty($details['items'])) {
            return redirect()->route('store.cart')->with('error', 'سبد خرید خالی است.');
        }

        $request->validate([
            'site_id' => 'nullable|array',
            'site_id.*' => 'exists:sites,id',
            'new_site_title' => 'nullable|string|max:255',
            'new_site_domain' => 'nullable|string|max:255',
        ]);

        $invoice = new Invoice([
            'customer_id' => $customer->id,
            'amount_irr' => $details['total'],
            'status' => Invoice::STATUS_PENDING,
        ]);
        $invoice->save();

        $newSite = null;
        if ($request->filled('new_site_title')) {
            $newSite = $customer->sites()->create([
                'title' => $request->new_site_title,
                'domain' => $request->new_site_domain,
                'is_active' => true,
            ]);
        }

        $siteIdByKey = $request->site_id ?? [];
        $effectiveSiteId = null;

        foreach ($details['items'] as $row) {
            $product = $row['product'];
            $siteId = $row['site_id'] ?? $siteIdByKey[$row['key']] ?? null;

            if ($product->type === Product::TYPE_LICENSE_TEMPLATE && !$siteId && $newSite) {
                $siteId = $newSite->id;
            }

            if ($effectiveSiteId === null && $siteId) {
                $effectiveSiteId = $siteId;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'quantity' => $row['quantity'],
                'price_irr' => $product->price_irr,
                'site_id' => $siteId,
            ]);
        }

        if ($effectiveSiteId === null && $newSite) {
            $effectiveSiteId = $newSite->id;
        }

        $gateway = $this->gatewayResolver->resolveForSite($effectiveSiteId);
        $gatewayId = $this->gatewayResolver->resolveGatewayIdForSite($effectiveSiteId);

        $invoice->update(['payment_gateway_id' => $gatewayId]);

        $callbackBase = config('services.payment_callback_url');
        $callbackUrl = $callbackBase
            ? rtrim($callbackBase, '/') . '/payment/callback?invoice=' . $invoice->id
            : route('checkout.callback', ['invoice' => $invoice->id]);
        $description = 'فاکتور #' . $invoice->id;
        $result = $gateway->request($details['total'], $callbackUrl, $description, (string) $invoice->id);

        if (empty($result['success'])) {
            $invoice->update(['status' => Invoice::STATUS_FAILED]);
            return back()->with('error', $result['error'] ?? 'خطا در اتصال به درگاه.');
        }

        $this->cart->clear();
        return redirect()->away($result['redirect_url']);
    }

    public function callback(Request $request, Invoice $invoice)
    {
        $gateway = $this->resolveGatewayForInvoice($invoice);
        $gatewayName = $gateway->name();

        $authority = null;
        $orderId = null;

        if ($gatewayName === 'idpay') {
            $authority = $request->query('id') ?? $request->input('id');
            $orderId = $request->query('order_id') ?? $request->input('order_id');
            $status = $request->query('status') ?? $request->input('status');
            if ((int) $status !== 100 || empty($authority)) {
                $invoice->update(['status' => Invoice::STATUS_FAILED]);
                return redirect()->route('store.cart')->with('error', 'پرداخت لغو یا ناموفق بود.');
            }
        } else {
            $authority = $request->query('Authority');
            $status = $request->query('Status');
            if ($status !== 'OK' || empty($authority)) {
                $invoice->update(['status' => Invoice::STATUS_FAILED]);
                return redirect()->route('store.cart')->with('error', 'پرداخت لغو یا ناموفق بود.');
            }
        }

        if ($invoice->status === Invoice::STATUS_PAID) {
            return redirect()->route('dashboard')->with('success', 'این فاکتور قبلاً پرداخت شده است.');
        }

        $verify = $gateway->verify($authority, $invoice->amount_irr, $orderId);

        if (empty($verify['verified'])) {
            $invoice->update(['status' => Invoice::STATUS_FAILED]);
            return redirect()->route('store.cart')->with('error', $verify['error'] ?? 'تأیید پرداخت ناموفق بود.');
        }

        $invoice->update([
            'status' => Invoice::STATUS_PAID,
            'gateway' => $gateway->name(),
            'gateway_ref' => $verify['ref'],
            'paid_at' => now(),
        ]);

        $this->fulfillment->fulfill($invoice);

        return redirect()->route('dashboard')->with('success', 'پرداخت با موفقیت انجام شد. لایسنس و ماژول‌ها فعال شدند.');
    }

    private function resolveGatewayForInvoice(Invoice $invoice): PaymentGatewayInterface
    {
        if ($invoice->payment_gateway_id) {
            $gateway = $this->gatewayFactory->fromId($invoice->payment_gateway_id);
            if ($gateway) {
                return $gateway;
            }
        }

        $model = PaymentGateway::getDefault();
        if ($model) {
            return $this->gatewayFactory->fromModel($model);
        }

        return app(PaymentGatewayInterface::class);
    }
}
