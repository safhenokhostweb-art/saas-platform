<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Site;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Wave 8.0b — پس از پرداخت ماژول، فقط یک مسیر audit برای entitlement
 * (`entitlement_granted` از EntitlementLedger)؛ بدون ردیف تکراری module.activated.
 */
class Wave80bFulfillmentAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_addon_fulfillment_writes_entitlement_granted_not_module_activated(): void
    {
        $this->assertTrue(Schema::hasTable('activity_logs'));
        $this->assertTrue(Schema::hasTable('module_entitlements'));

        $customer = Customer::create([
            'name' => 'مشتری تست',
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'w80b_' . uniqid('', true) . '@example.com',
        ]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'w80b-' . uniqid() . '.example.com',
            'is_active' => true,
        ]);
        $module = Module::firstOrCreate(
            ['slug' => 'w80b_mod_' . uniqid()],
            [
                'title' => 'ماژول تست w80b',
                'type' => Module::TYPE_PAID,
                'is_active' => true,
            ]
        );

        $product = Product::create([
            'sku' => 'mod_addon_' . uniqid(),
            'name_fa' => 'خرید ماژول تست',
            'type' => Product::TYPE_MODULE_ADDON,
            'price_irr' => 500000,
            'is_active' => true,
            'module_id' => $module->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'amount_irr' => 500000,
            'status' => Invoice::STATUS_PAID,
            'gateway' => 'test',
            'paid_at' => now(),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_irr' => 500000,
            'site_id' => $site->id,
        ]);

        $invoice->load('items.product');

        app(OrderFulfillmentService::class)->fulfill($invoice);

        $this->assertGreaterThanOrEqual(1, ActivityLog::where('action', 'entitlement_granted')->count());
        $this->assertSame(0, ActivityLog::where('action', 'module.activated')->count());
    }
}
