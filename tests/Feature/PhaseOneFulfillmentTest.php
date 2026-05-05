<?php

namespace Tests\Feature;

use App\Mail\LicenseIssuedMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Site;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * تست فاز ۱: ارسال ایمیل لایسنس پس از صدور.
 */
class PhaseOneFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_email_sent_after_fulfillment(): void
    {
        Mail::fake();

        $customer = Customer::create([
            'name' => 'مشتری تست',
            'email' => 'customer@example.com',
            'mobile' => '09123456789',
        ]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'example.com',
            'is_active' => true,
        ]);
        $product = Product::create([
            'sku' => 'site_template_' . uniqid(),
            'name_fa' => 'لایسنس سایت',
            'type' => Product::TYPE_LICENSE_TEMPLATE,
            'price_irr' => 1000000,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'amount_irr' => 1000000,
            'status' => Invoice::STATUS_PAID,
            'gateway' => 'zarinpal',
            'paid_at' => now(),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_irr' => 1000000,
            'site_id' => $site->id,
        ]);

        $service = app(OrderFulfillmentService::class);
        $service->fulfill($invoice);

        Mail::assertSent(LicenseIssuedMail::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email)
                && str_contains($mail->licenseKey, '-');
        });
    }

    public function test_license_email_not_sent_when_customer_has_no_email(): void
    {
        Mail::fake();

        $customer = Customer::create([
            'name' => 'مشتری بدون ایمیل',
            'email' => null,
            'mobile' => '09123456789',
        ]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'example.com',
            'is_active' => true,
        ]);
        $product = Product::create([
            'sku' => 'site_template_' . uniqid(),
            'name_fa' => 'لایسنس سایت',
            'type' => Product::TYPE_LICENSE_TEMPLATE,
            'price_irr' => 1000000,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'amount_irr' => 1000000,
            'status' => Invoice::STATUS_PAID,
            'gateway' => 'zarinpal',
            'paid_at' => now(),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_irr' => 1000000,
            'site_id' => $site->id,
        ]);

        $service = app(OrderFulfillmentService::class);
        $service->fulfill($invoice);

        Mail::assertNotSent(LicenseIssuedMail::class);
    }
}
