<?php

namespace Tests\Feature\Modules\MotionGraphics\CustomerExperience;

use App\Models\Customer;
use App\Models\Module;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Models\MotionBespokePurchase;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\ModuleLicenseService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 11 — the customer-facing Motion Graphics dashboard section, its
 * self-service "request a custom motion" write path, and its "purchase"
 * action that hands off to the real, unmodified cart/checkout pipeline.
 * Every case goes through the real HTTP web routes (not a service-level
 * shortcut), matching the convention every prior phase's SecurityTest uses.
 */
class CustomerMotionGraphicsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
        (new ModuleSeeder())->run();
        // Same pre-existing, environment-only CSRF regression documented in
        // Phase X's PurchaseAndAssignmentFlowTest::setUp() — reproduced on
        // untouched Phase 6/7 admin tests, unrelated to this phase.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        // Not under test here — avoid the unrelated EnsureProfileIsComplete
        // redirect so these tests exercise Motion Graphics authorization,
        // not profile-completion middleware.
        Setting::create(['key' => 'force_complete_profile', 'value' => '0']);
    }

    private function enableSite(Site $site): void
    {
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
    }

    private function grantBaseEntitlement(Site $site): void
    {
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
        $admin = User::create(['mobile' => '0912'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_admin' => true]);
        $this->app->make(ModuleLicenseService::class)->grantByAdmin($site, $module->id, $admin);
    }

    /** A real, persisted customer actor — linked to the Customer that createSite() already created for $site. */
    private function customerUserFor(Site $site): User
    {
        $user = User::create([
            'mobile' => '0912'.random_int(1000000, 9999999),
            'password' => bcrypt('x'),
            'is_admin' => false,
            'site_id' => $site->id,
        ]);
        Customer::whereKey($site->customer_id)->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function publishedCommissionWithProduct(Site $site, string $title = 'Logo Animation'): MotionBespokeCommission
    {
        $product = Product::create([
            'sku' => 'bespoke-'.uniqid(), 'name_fa' => $title, 'name_en' => $title,
            'type' => Product::TYPE_MOTION_ASSET, 'price_irr' => 1500000, 'is_one_time' => true, 'is_active' => true,
        ]);

        return MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => $title, 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'product_id' => $product->id, 'published_at' => now(),
        ]);
    }

    public function test_the_customer_dashboard_shows_license_status_and_the_sites_own_commissions(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $commission = $this->publishedCommissionWithProduct($site, 'Brand Intro Motion');
        $user = $this->customerUserFor($site);

        $response = $this->actingAs($user)->get(route('customer.motion-graphics.index'));

        $response->assertOk();
        $response->assertSee('Brand Intro Motion');
        $response->assertSee('موتور موشن گرافیک فعال است');
    }

    public function test_a_customer_can_submit_a_custom_motion_request_for_their_own_site(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $user = $this->customerUserFor($site);

        $this->actingAs($user)->post(route('customer.motion-graphics.request'), [
            'site_id' => $site->id,
            'title' => 'Educational Motion Package',
            'description' => 'A short explainer animation for our onboarding page.',
        ])->assertSessionHas('success');

        $commission = MotionBespokeCommission::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertSame(MotionBespokeCommission::STATUS_DRAFT, $commission->status);
        $this->assertSame('Educational Motion Package', $commission->title);
        $this->assertNull($commission->product_id, 'a customer request must never auto-attach a sellable product');
    }

    public function test_a_customer_cannot_submit_a_request_for_a_site_they_do_not_own(): void
    {
        $ownSite = $this->createSite('My Site');
        $otherSite = $this->createSite('Someone Elses Site');
        $user = $this->customerUserFor($ownSite);

        $this->actingAs($user)->post(route('customer.motion-graphics.request'), [
            'site_id' => $otherSite->id,
            'title' => 'Attempted cross-site request',
        ])->assertSessionHas('error');

        $this->assertSame(0, MotionBespokeCommission::query()->where('site_id', $otherSite->id)->count());
    }

    public function test_purchasing_a_published_commission_adds_it_to_the_real_cart_and_never_auto_assigns(): void
    {
        $site = $this->createSite();
        $commission = $this->publishedCommissionWithProduct($site);
        $user = $this->customerUserFor($site);

        $this->actingAs($user)
            ->post(route('customer.motion-graphics.purchase', $commission))
            ->assertRedirect(route('store.cart'));

        $cartDetails = $this->app->make(\App\Services\CartService::class)->details();
        $this->assertCount(1, $cartDetails['items']);
        $this->assertSame($commission->product_id, $cartDetails['items'][0]['product']->id);
        $this->assertSame($site->id, $cartDetails['items'][0]['site_id']);

        // Purchase-record/assignment only ever happen via real fulfillment
        // (OrderFulfillmentService) or an explicit admin assign() call —
        // never as a side effect of adding to cart.
        $this->assertSame(0, MotionBespokePurchase::query()->count());
        $this->assertSame(0, MotionBespokeAssignment::query()->count());
    }

    public function test_a_customer_cannot_purchase_another_sites_bespoke_commission(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $commissionForB = $this->publishedCommissionWithProduct($siteB);
        $userA = $this->customerUserFor($siteA);

        $this->actingAs($userA)
            ->post(route('customer.motion-graphics.purchase', $commissionForB))
            ->assertForbidden();
    }

    public function test_an_unpublished_commission_cannot_be_purchased_by_guessing_the_route(): void
    {
        $site = $this->createSite();
        $product = Product::create([
            'sku' => 'bespoke-draft-'.uniqid(), 'name_fa' => 'x', 'name_en' => 'x',
            'type' => Product::TYPE_MOTION_ASSET, 'price_irr' => 1, 'is_one_time' => true, 'is_active' => true,
        ]);
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_DESIGNING,
            'product_id' => $product->id,
        ]);
        $user = $this->customerUserFor($site);

        $this->actingAs($user)
            ->post(route('customer.motion-graphics.purchase', $commission))
            ->assertSessionHas('error');

        $cartDetails = $this->app->make(\App\Services\CartService::class)->details();
        $this->assertCount(0, $cartDetails['items']);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('customer.motion-graphics.index'))->assertRedirect(route('login.show'));
    }

    public function test_a_bespoke_motion_asset_product_never_appears_in_the_generic_storefront(): void
    {
        $site = $this->createSite();
        $commission = $this->publishedCommissionWithProduct($site, 'Should Never Leak');
        $user = $this->customerUserFor($site);

        $indexResponse = $this->actingAs($user)->get(route('store.index'));
        $modulesResponse = $this->actingAs($user)->get(route('store.modules'));

        $indexResponse->assertOk()->assertDontSee('Should Never Leak');
        $modulesResponse->assertOk()->assertDontSee('Should Never Leak');
    }

    public function test_the_motion_graphics_module_itself_is_purchasable_from_the_existing_module_store_unchanged(): void
    {
        $site = $this->createSite();
        $user = $this->customerUserFor($site);

        $this->actingAs($user)->get(route('store.modules'))
            ->assertOk()
            ->assertSee('موشن گرافیک');
    }
}
