<?php

namespace Tests\Feature\Modules\MotionGraphics\BespokeCommercialization;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignmentEvent;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Models\MotionBespokePurchase;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\ModuleLicenseService;
use App\Services\OrderFulfillmentService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase X task section 5's full workflow: Product -> Invoice -> Payment ->
 * Order Fulfillment -> (purchase recorded, NOT activated) -> Platform
 * Admin assign (a real, separate step) -> customer runtime sees it.
 * Mirrors Phase 9's SiteActivationFlowTest structure for the module-level
 * flow, applied to the new per-asset bespoke flow.
 */
class PurchaseAndAssignmentFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
        (new ModuleSeeder())->run();
        // Confirmed pre-existing, environment-only CSRF regression on this
        // machine's `web` middleware group under `php artisan test` —
        // reproduced identically on Phase 7's own untouched
        // AdminWorkflowSuccessTest (419 instead of the expected redirect),
        // unrelated to any Phase X code. Disabled here only so these tests
        // exercise real controller/service logic instead of tripping the
        // same environment bug; not a change to any application code.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function enableSite($site): void
    {
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
    }

    private function grantBaseEntitlement($site): void
    {
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
        $admin = User::create(['mobile' => '0912'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_admin' => true]);
        $this->app->make(ModuleLicenseService::class)->grantByAdmin($site, $module->id, $admin);
    }

    private function commissionProduct(): Product
    {
        return Product::create([
            'sku' => 'bespoke-'.uniqid(), 'name_fa' => 'x', 'name_en' => 'x',
            'type' => Product::TYPE_MOTION_ASSET, 'price_irr' => 5000000, 'is_one_time' => true, 'is_active' => true,
        ]);
    }

    public function test_a_paid_invoice_records_a_purchase_but_does_not_assign_or_activate(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $product = $this->commissionProduct();
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'Business Hero Animation',
            'status' => MotionBespokeCommission::STATUS_PUBLISHED, 'asset_id' => 'bespoke-1',
            'product_id' => $product->id, 'published_at' => now(),
        ]);

        $invoice = Invoice::create([
            'customer_id' => $site->customer_id, 'amount_irr' => $product->price_irr,
            'status' => Invoice::STATUS_PAID, 'paid_at' => now(),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'product_id' => $product->id,
            'quantity' => 1, 'price_irr' => $product->price_irr, 'site_id' => $site->id,
        ]);

        $this->app->make(OrderFulfillmentService::class)->fulfill($invoice->fresh(['items']));

        $this->assertDatabaseHas('motion_bespoke_purchases', [
            'commission_id' => $commission->id, 'site_id' => $site->id, 'invoice_id' => $invoice->id,
        ]);
        // Explicitly no assignment, no site_modules row for this product —
        // "after payment, must NOT be directly/automatically activated".
        $this->assertSame(0, MotionBespokeAssignment::query()->count());
    }

    public function test_assignment_is_rejected_without_a_purchase_even_if_published(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $this->bindPublishedAsset('bespoke-1', 1);
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => 'bespoke-1', 'published_at' => now(),
        ]);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1])
            ->assertSessionHas('error');

        $this->assertSame(0, MotionBespokeAssignment::query()->count());
    }

    public function test_assignment_is_rejected_when_commission_is_not_yet_published(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $this->bindPublishedAsset('bespoke-1', 1);
        $product = $this->commissionProduct();
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_REVIEW,
            'asset_id' => 'bespoke-1', 'product_id' => $product->id,
        ]);
        MotionBespokePurchase::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'amount_irr' => 1, 'purchased_at' => now(),
        ]);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1])
            ->assertSessionHas('error');

        $this->assertSame(0, MotionBespokeAssignment::query()->count());
    }

    public function test_a_purchased_and_published_commission_can_be_assigned_by_an_admin(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $this->bindPublishedAsset('bespoke-1', 1);
        $product = $this->commissionProduct();
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => 'bespoke-1', 'product_id' => $product->id, 'published_at' => now(),
        ]);
        MotionBespokePurchase::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'amount_irr' => $product->price_irr, 'purchased_at' => now(),
        ]);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('motion_bespoke_assignments', [
            'commission_id' => $commission->id, 'site_id' => $site->id, 'active_version' => 1, 'status' => 'active',
        ]);
        $this->assertDatabaseHas('motion_bespoke_assignment_events', ['event_type' => MotionBespokeAssignmentEvent::TYPE_INSTALL, 'version' => 1]);
    }

    public function test_rollback_requires_the_target_version_to_have_been_previously_assigned(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $this->bindPublishedAsset('bespoke-1', 1);
        $product = $this->commissionProduct();
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => 'bespoke-1', 'product_id' => $product->id, 'published_at' => now(),
        ]);
        MotionBespokePurchase::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'amount_irr' => 1, 'purchased_at' => now(),
        ]);
        $this->actingAs($this->admin());
        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1]);

        // version 2 was never assigned — rollback to it must be rejected.
        $this->post(route('admin.motion-graphics.bespoke.rollback', [$site, $commission]), ['version' => 2])
            ->assertSessionHas('error');

        $this->assertSame(1, MotionBespokeAssignment::query()->first()->active_version);
    }

    public function test_upgrade_then_rollback_never_deletes_prior_history(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $product = $this->commissionProduct();
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => 'bespoke-1', 'product_id' => $product->id, 'published_at' => now(),
        ]);
        MotionBespokePurchase::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'amount_irr' => 1, 'purchased_at' => now(),
        ]);
        $this->actingAs($this->admin());

        $this->bindPublishedAsset('bespoke-1', 1);
        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1])
            ->assertSessionHas('success');

        $this->bindPublishedAsset('bespoke-1', 2);
        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 2])
            ->assertSessionHas('success');

        $this->assertSame(2, MotionBespokeAssignment::query()->first()->active_version);
        $this->assertDatabaseHas('motion_bespoke_assignment_events', ['event_type' => 'upgrade', 'version' => 2, 'previous_version' => 1]);

        $this->bindPublishedAsset('bespoke-1', 1);
        $this->post(route('admin.motion-graphics.bespoke.rollback', [$site, $commission]), ['version' => 1])
            ->assertSessionHas('success');

        $this->assertSame(1, MotionBespokeAssignment::query()->first()->active_version);
        $this->assertSame(3, MotionBespokeAssignmentEvent::query()->count(), 'install + upgrade + rollback — never overwritten, never deleted');
    }

    public function test_revoke_marks_the_assignment_inactive_without_deleting_it(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $this->bindPublishedAsset('bespoke-1', 1);
        $product = $this->commissionProduct();
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => 'bespoke-1', 'product_id' => $product->id, 'published_at' => now(),
        ]);
        MotionBespokePurchase::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'amount_irr' => 1, 'purchased_at' => now(),
        ]);
        $this->actingAs($this->admin());
        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1]);

        $this->post(route('admin.motion-graphics.bespoke.revoke', [$site, $commission]))
            ->assertSessionHas('success');

        $assignment = MotionBespokeAssignment::query()->first();
        $this->assertSame(MotionBespokeAssignment::STATUS_REVOKED, $assignment->status);
        $this->assertNotNull($assignment); // row still exists — never deleted
    }
}
