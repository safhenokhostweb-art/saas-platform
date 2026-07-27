<?php

namespace Tests\Unit\Services;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Product;
use App\Models\Site;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 17-D — Part 5: OrderFulfillmentService::fulfillModuleAddon() no
 * longer syncWithoutDetaching()s directly — it goes through
 * ModuleAssignmentService::activatePaidPurchase(), so a paid module
 * purchase now actually dispatches a real deployment, while still
 * guaranteeing entitlement regardless of dispatch outcome (the customer
 * already paid).
 */
class OrderFulfillmentServiceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeSite(bool $withCompletedInstallation = true): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);
        $site = Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
        ]);

        if ($withCompletedInstallation) {
            $server = CustomerRemoteServer::create([
                'label' => 'Test Server', 'type' => 'fake', 'host' => 'localhost',
                'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
            ]);
            CustomerInstallation::create([
                'remote_server_id' => $server->id, 'installer_user_id' => 1,
                'install_mode' => 'MODE_FRESH', 'status' => 'complete', 'site_id' => $site->id,
                'customer_company' => $customer->name, 'customer_email' => 'noreply@example.com',
                'customer_domain' => $site->domain,
            ]);
        }

        return $site;
    }

    private function makePaidModuleWithPackage(): Module
    {
        $module = Module::create([
            'slug' => 'crm-addon-' . uniqid('', true), 'title' => 'CRM',
            'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_PAID,
            'free_assignable' => false, 'license_required' => false,
            'price_irr' => 150000, 'marketplace_visible' => true,
        ]);
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'package_sha256' => str_repeat('a', 64),
            'package_path' => '/tmp/crm.zip', 'package_size_bytes' => 1024,
        ]);
        return $module;
    }

    private function makePaidInvoice(Site $site, Module $module): Invoice
    {
        $invoice = Invoice::create([
            'customer_id' => $site->customer_id, 'amount_irr' => 150000,
            'status' => Invoice::STATUS_PAID, 'paid_at' => now(),
        ]);
        $product = Product::create([
            'sku' => 'sku-' . uniqid('', true), 'name_fa' => 'CRM افزودنی',
            'type' => Product::TYPE_MODULE_ADDON, 'price_irr' => 150000,
            'is_one_time' => true, 'module_id' => $module->id, 'is_active' => true,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id,
            'quantity' => 1, 'price_irr' => 150000, 'site_id' => $site->id,
        ]);
        return $invoice->fresh(['items']);
    }

    public function test_paid_module_purchase_dispatches_real_deployment(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = $this->makePaidModuleWithPackage();
        $invoice = $this->makePaidInvoice($site, $module);

        app(OrderFulfillmentService::class)->fulfill($invoice);

        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_active);
        $this->assertSame('pending_install', $row->deployment_status);
        Bus::assertDispatched(CustomerInstallationPipelineJob::class);
        $this->assertDatabaseHas('site_module_purchases', ['site_id' => $site->id, 'module_id' => $module->id]);
    }

    /**
     * Phase 17-D — the core guarantee: a customer who already paid must
     * never end up with zero entitlement just because the site's remote
     * server couldn't be resolved at fulfillment time.
     */
    public function test_paid_module_purchase_still_grants_entitlement_when_dispatch_fails(): void
    {
        Bus::fake();
        $site = $this->makeSite(withCompletedInstallation: false); // no resolvable remote server
        $module = $this->makePaidModuleWithPackage();
        $invoice = $this->makePaidInvoice($site, $module);

        app(OrderFulfillmentService::class)->fulfill($invoice);

        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_active, 'payment already happened — entitlement must not be lost');
        $this->assertSame('failed', $row->deployment_status);
        // Purchase record and ledger entry must still be recorded — money was captured.
        $this->assertDatabaseHas('site_module_purchases', ['site_id' => $site->id, 'module_id' => $module->id]);
    }
}
