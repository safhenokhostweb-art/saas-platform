<?php

namespace Tests\Feature\Modules\MotionGraphics\BespokeCommercialization;

use App\Models\Product;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase X — business/commission lifecycle: draft -> designing -> review ->
 * approved -> published -> archived, through the real admin HTTP routes
 * (same convention as Phase 7's AccessControlTest). "assigned" is
 * deliberately NOT a status here — see AssignmentAndSecurityTest.
 */
class CommissionLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
        // See PurchaseAndAssignmentFlowTest::setUp() — confirmed
        // pre-existing, environment-only CSRF regression, reproduced on
        // Phase 7's own untouched tests, unrelated to Phase X.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function enableSite($site): void
    {
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
    }

    public function test_an_admin_can_create_a_commission_in_draft_status(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.bespoke.store', $site), ['title' => 'Business Hero Animation'])
            ->assertRedirect(route('admin.motion-graphics.bespoke.index', $site));

        $this->assertDatabaseHas('motion_bespoke_commissions', [
            'site_id' => $site->id, 'title' => 'Business Hero Animation', 'status' => 'draft',
        ]);
    }

    public function test_a_customer_actor_cannot_create_a_commission(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->actingAs($this->customer($site));

        $this->post(route('admin.motion-graphics.bespoke.store', $site), ['title' => 'x'])
            ->assertForbidden();

        $this->assertSame(0, MotionBespokeCommission::query()->count());
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);

        $this->get(route('admin.motion-graphics.bespoke.index', $site))->assertRedirect(route('login'));
    }

    public function test_the_full_forward_lifecycle_is_allowed_in_sequence(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->actingAs($this->admin());
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_DRAFT,
        ]);

        foreach (['designing', 'review', 'approved', 'published'] as $next) {
            $this->post(route('admin.motion-graphics.bespoke.transition', [$site, $commission]), ['status' => $next])
                ->assertRedirect(route('admin.motion-graphics.bespoke.index', $site))
                ->assertSessionHas('success');
            $commission->refresh();
            $this->assertSame($next, $commission->status);
        }

        $this->assertNotNull($commission->fresh()->published_at);
    }

    public function test_a_status_cannot_be_skipped(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->actingAs($this->admin());
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_DRAFT,
        ]);

        $this->post(route('admin.motion-graphics.bespoke.transition', [$site, $commission]), ['status' => 'published'])
            ->assertSessionHas('error');

        $this->assertSame('draft', $commission->fresh()->status);
    }

    public function test_only_a_motion_asset_type_product_can_be_linked(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->actingAs($this->admin());
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_DRAFT,
        ]);
        $wrongProduct = Product::create([
            'sku' => 'wrong-'.uniqid(), 'name_fa' => 'x', 'name_en' => 'x',
            'type' => Product::TYPE_SUPPORT_PLAN, 'price_irr' => 1000, 'is_one_time' => true, 'is_active' => true,
        ]);

        $this->post(route('admin.motion-graphics.bespoke.link-product', [$site, $commission]), ['product_id' => $wrongProduct->id])
            ->assertSessionHas('error');

        $this->assertNull($commission->fresh()->product_id);
    }

    public function test_a_real_motion_asset_product_links_successfully(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->actingAs($this->admin());
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_DRAFT,
        ]);
        $product = Product::create([
            'sku' => 'bespoke-'.uniqid(), 'name_fa' => 'x', 'name_en' => 'x',
            'type' => Product::TYPE_MOTION_ASSET, 'price_irr' => 5000000, 'is_one_time' => true, 'is_active' => true,
        ]);

        $this->post(route('admin.motion-graphics.bespoke.link-product', [$site, $commission]), ['product_id' => $product->id])
            ->assertSessionHas('success');

        $this->assertSame($product->id, $commission->fresh()->product_id);
    }
}
