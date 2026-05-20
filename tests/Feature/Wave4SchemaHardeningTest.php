<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\ModulePurchase;
use App\Models\Product;
use App\Models\Site;
use App\Models\SupportPlan;
use App\Services\OrderFulfillmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Wave 5.4 — Regression tests for Wave 4 / P0-2 + P1-6.
 *
 * P0-2 — schema hardening:
 *   - modules.retired_at column exists (nullable timestamp).
 *   - Module::retire()/unretire()/isRetired() helpers behave correctly.
 *   - Retired modules drop out of scopeAssignableToSite / scopePaidMarketplace.
 *   - FK on module_id in site_modules / site_module_purchases /
 *     module_licenses is restrictOnDelete: deleting a module that is still
 *     referenced fails at the DB level.
 *
 * P1-6 — support_starts_at:
 *   - sites.support_starts_at column exists.
 *   - OrderFulfillmentService::fulfillSupportPlan sets support_starts_at
 *     on first purchase / after expired support.
 *   - support_starts_at is NOT overwritten when an active support plan
 *     is renewed (continuous subscription preserved).
 */
class Wave4SchemaHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'تست مشتری',
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'wave4_' . uniqid() . '@example.com',
        ]);
    }

    private function makeSite(Customer $customer): Site
    {
        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'wave4-' . uniqid() . '.example.com',
            'is_active' => true,
        ]);
    }

    private function makeModule(string $slug): Module
    {
        return Module::firstOrCreate(
            ['slug' => $slug],
            ['title' => 'ماژول ' . $slug, 'type' => Module::TYPE_PAID, 'is_active' => true]
        );
    }

    // =====================================================================
    // P0-2 — modules.retired_at + retire()/unretire()/isRetired() helpers
    // =====================================================================

    public function test_modules_retired_at_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('modules', 'retired_at'));
    }

    public function test_module_retire_and_unretire_round_trip(): void
    {
        $module = $this->makeModule('retire_round_trip');

        $this->assertFalse($module->isRetired());
        $this->assertNull($module->retired_at);

        $this->assertTrue($module->retire());
        $module->refresh();
        $this->assertTrue($module->isRetired());
        $this->assertNotNull($module->retired_at);
        $this->assertSame(Module::MODE_RETIRED, $module->getDistributionMode());

        $this->assertTrue($module->unretire());
        $module->refresh();
        $this->assertFalse($module->isRetired());
        $this->assertNull($module->retired_at);
        $this->assertNotSame(Module::MODE_RETIRED, $module->getDistributionMode());
    }

    public function test_retired_module_is_excluded_from_assignable_scope(): void
    {
        $module = $this->makeModule('retire_scope_test');

        $this->assertTrue(Module::assignableToSite()->where('id', $module->id)->exists());

        $module->retire();
        $this->assertFalse(Module::assignableToSite()->where('id', $module->id)->exists());

        $module->unretire();
        $this->assertTrue(Module::assignableToSite()->where('id', $module->id)->exists());
    }

    public function test_retired_module_is_excluded_from_paid_marketplace_scope(): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'retire_paid_market'],
            [
                'title' => 'ماژول مارکت',
                'type' => Module::TYPE_PAID,
                'is_active' => true,
                'price_irr' => 1500000,
                'license_required' => false,
                'marketplace_visible' => true,
            ]
        );

        $this->assertTrue(Module::paidMarketplace()->where('id', $module->id)->exists());

        $module->retire();
        $this->assertFalse(Module::paidMarketplace()->where('id', $module->id)->exists());
    }

    // =====================================================================
    // P0-2 — FK restrictOnDelete on site_modules / purchases / licenses
    // =====================================================================

    /**
     * Helper: assert that deleting the given module fails with an FK
     * violation. Captures and rolls back the (non-savepoint) error at the
     * test level so that the outer RefreshDatabase transaction stays sane.
     */
    private function assertModuleDeleteRejected(int $moduleId, string $tableName): void
    {
        $threw = false;
        try {
            DB::table('modules')->where('id', $moduleId)->delete();
        } catch (QueryException $e) {
            $threw = true;
            $this->assertStringContainsStringIgnoringCase(
                'foreign key',
                strtolower($e->getMessage()),
                "expected FK violation when deleting module referenced by {$tableName}; got: " . $e->getMessage()
            );
        }
        $this->assertTrue($threw, "expected FK violation when deleting module referenced by {$tableName}");
        $this->assertTrue(
            DB::table('modules')->where('id', $moduleId)->exists(),
            "module row should still exist after blocked delete (referenced by {$tableName})"
        );
    }

    public function test_module_with_active_site_module_row_cannot_be_deleted(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('protect_site_modules');

        DB::table('site_modules')->insert([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertModuleDeleteRejected($module->id, 'site_modules');
    }

    public function test_module_with_module_license_cannot_be_deleted(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('protect_module_licenses');

        ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(),
            'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $this->assertModuleDeleteRejected($module->id, 'module_licenses');
    }

    public function test_module_with_purchase_row_cannot_be_deleted(): void
    {
        $customer = $this->makeCustomer();
        $site = $this->makeSite($customer);
        $module = $this->makeModule('protect_purchases');

        ModulePurchase::create([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'invoice_id' => null,
            'invoice_item_id' => null,
            'amount_irr' => 1000000,
            'granted_by' => null,
            'purchased_at' => now(),
        ]);

        $this->assertModuleDeleteRejected($module->id, 'site_module_purchases');
    }

    public function test_unused_module_can_still_be_deleted(): void
    {
        $module = $this->makeModule('module_no_refs_' . uniqid());
        $id = $module->id;

        DB::table('modules')->where('id', $id)->delete();
        $this->assertFalse(Module::query()->where('id', $id)->exists());
    }

    // =====================================================================
    // P1-6 — sites.support_starts_at + fulfillSupportPlan behaviour
    // =====================================================================

    public function test_sites_support_starts_at_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('sites', 'support_starts_at'));
    }

    public function test_fulfill_support_plan_sets_support_starts_at_on_first_purchase(): void
    {
        $customer = $this->makeCustomer();
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت بدون پشتیبانی',
            'domain' => 'p16-first-' . uniqid() . '.example.com',
            'is_active' => true,
            'support_starts_at' => null,
            'support_expires_at' => null,
        ]);

        $plan = SupportPlan::create([
            'title' => 'پلن پشتیبانی تست',
            'price' => 500000,
            'duration_days' => 30,
            'has_ticket' => true,
            'has_sms' => false,
        ]);
        $product = Product::create([
            'sku' => 'sup_' . uniqid(),
            'name_fa' => 'پشتیبانی ۳۰ روزه',
            'type' => Product::TYPE_SUPPORT_PLAN,
            'price_irr' => 500000,
            'is_active' => true,
            'support_plan_id' => $plan->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'amount_irr' => 500000,
            'status' => Invoice::STATUS_PAID,
            'gateway' => 'zarinpal',
            'paid_at' => now(),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_irr' => 500000,
            'site_id' => $site->id,
        ]);

        app(OrderFulfillmentService::class)->fulfill($invoice);
        $site->refresh();

        // Note: sites.support_starts_at is cast to 'date' (no time component).
        $this->assertNotNull($site->support_starts_at, 'support_starts_at must be set on first purchase');
        $this->assertNotNull($site->support_expires_at);
        $this->assertSame(now()->toDateString(), $site->support_starts_at->toDateString());
    }

    public function test_fulfill_support_plan_does_not_overwrite_existing_support_starts_at(): void
    {
        $customer = $this->makeCustomer();
        $originalStartedAt = now()->subDays(15);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت با پشتیبانی فعال',
            'domain' => 'p16-renew-' . uniqid() . '.example.com',
            'is_active' => true,
            'support_starts_at' => $originalStartedAt,
            'support_expires_at' => now()->addDays(15),
        ]);

        $plan = SupportPlan::create([
            'title' => 'پلن تمدید',
            'price' => 300000,
            'duration_days' => 30,
            'has_ticket' => true,
            'has_sms' => false,
        ]);
        $product = Product::create([
            'sku' => 'sup_renew_' . uniqid(),
            'name_fa' => 'تمدید پشتیبانی',
            'type' => Product::TYPE_SUPPORT_PLAN,
            'price_irr' => 300000,
            'is_active' => true,
            'support_plan_id' => $plan->id,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'amount_irr' => 300000,
            'status' => Invoice::STATUS_PAID,
            'gateway' => 'zarinpal',
            'paid_at' => now(),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_irr' => 300000,
            'site_id' => $site->id,
        ]);

        app(OrderFulfillmentService::class)->fulfill($invoice);
        $site->refresh();

        // sites.support_starts_at is cast to 'date'. The stored value must
        // still match the original day, NOT today, to prove no overwrite.
        $this->assertSame(
            $originalStartedAt->toDateString(),
            $site->support_starts_at->toDateString(),
            'support_starts_at must not be overwritten on active-plan renewal'
        );
        $this->assertNotSame(now()->toDateString(), $site->support_starts_at->toDateString());
        $this->assertTrue($site->support_expires_at->greaterThan(now()->addDays(40)));
    }
}
