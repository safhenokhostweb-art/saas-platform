<?php

namespace Tests\Feature\Modules\MotionGraphics\BespokeCommercialization;

use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use App\Modules\MotionGraphics\Models\MotionBespokeCommission;
use App\Modules\MotionGraphics\Models\MotionBespokePurchase;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\LicenseServerService;
use App\Services\ModuleLicenseService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase X task section 10's four required checks: Site A never sees Site
 * B's Motion; a customer can never touch the Platform Master Asset; an
 * unauthorized/unpublished Version can never be loaded; direct API access
 * without a valid Entitlement is always denied. Every case goes through
 * the real HTTP `tenant.api` channel or the real admin HTTP routes, not a
 * service-level shortcut — same convention as Phase 9's SecurityValidationTest.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
        (new ModuleSeeder())->run();
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

    private function grantBaseEntitlement($site): void
    {
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
        $admin = User::create(['mobile' => '0912'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_admin' => true]);
        $this->app->make(ModuleLicenseService::class)->grantByAdmin($site, $module->id, $admin);
    }

    private function issueLicenseFor($site, string $domain): string
    {
        return $this->app->make(LicenseServerService::class)->issue($site, $domain);
    }

    private function publishedAndAssignedCommission($site, string $assetId, int $version): MotionBespokeCommission
    {
        $product = Product::create([
            'sku' => 'bespoke-'.uniqid(), 'name_fa' => 'x', 'name_en' => 'x',
            'type' => Product::TYPE_MOTION_ASSET, 'price_irr' => 1, 'is_one_time' => true, 'is_active' => true,
        ]);
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => $assetId, 'product_id' => $product->id, 'published_at' => now(),
        ]);
        MotionBespokePurchase::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'amount_irr' => 1, 'purchased_at' => now(),
        ]);
        MotionBespokeAssignment::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'asset_id' => $assetId,
            'active_version' => $version, 'status' => MotionBespokeAssignment::STATUS_ACTIVE, 'assigned_at' => now(),
        ]);

        return $commission->fresh();
    }

    public function test_site_a_cannot_see_site_bs_bespoke_asset_via_the_real_api(): void
    {
        $siteA = $this->createSite('Site A');
        $this->enableSite($siteA);
        $this->grantBaseEntitlement($siteA);
        $this->bindPublishedAsset('bespoke-a', 1);
        $this->publishedAndAssignedCommission($siteA, 'bespoke-a', 1);

        $siteB = $this->createSite('Site B');
        $this->enableSite($siteB);
        $this->grantBaseEntitlement($siteB);
        // Site B has no commission/assignment of its own at all.
        $keyForB = $this->issueLicenseFor($siteB, 'site-b.test');

        $response = $this->withToken($keyForB)->getJson('/api/motion-runtime/bespoke-manifest?domain=site-b.test');

        $response->assertOk()->assertJson(['available' => true, 'assets' => []]);
    }

    public function test_direct_api_access_without_any_module_entitlement_is_denied(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        // Deliberately no grantBaseEntitlement() — no ModuleLicense at all.
        $this->bindPublishedAsset('bespoke-1', 1);
        $key = $this->issueLicenseFor($site, 'customer.test');

        $response = $this->withToken($key)->getJson('/api/motion-runtime/bespoke-manifest?domain=customer.test');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'license_denied']);
    }

    public function test_direct_api_access_when_the_feature_flag_is_off_is_denied(): void
    {
        $site = $this->createSite(); // flag never enabled
        $this->grantBaseEntitlement($site);
        $key = $this->issueLicenseFor($site, 'customer.test');

        $response = $this->withToken($key)->getJson('/api/motion-runtime/bespoke-manifest?domain=customer.test');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'motion_adapter_disabled']);
    }

    public function test_an_unpublished_commission_can_never_be_assigned_by_guessing_the_route(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $this->bindPublishedAsset('bespoke-1', 1);
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_DRAFT, 'asset_id' => 'bespoke-1',
        ]);
        MotionBespokePurchase::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'amount_irr' => 1, 'purchased_at' => now(),
        ]);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1])
            ->assertSessionHas('error');

        $this->assertSame(0, MotionBespokeAssignment::query()->count());
    }

    public function test_a_version_the_module_never_marked_readable_cannot_be_assigned(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        // bindPublishedAsset('bespoke-1', 1) is never called — findByCanonicalId
        // returns null for any version, simulating a draft/unreadable version.
        $this->bindUnpublishedAsset('bespoke-1');
        $product = Product::create([
            'sku' => 'bespoke-'.uniqid(), 'name_fa' => 'x', 'name_en' => 'x',
            'type' => Product::TYPE_MOTION_ASSET, 'price_irr' => 1, 'is_one_time' => true, 'is_active' => true,
        ]);
        $commission = MotionBespokeCommission::create([
            'site_id' => $site->id, 'title' => 'x', 'status' => MotionBespokeCommission::STATUS_PUBLISHED,
            'asset_id' => 'bespoke-1', 'product_id' => $product->id, 'published_at' => now(),
        ]);
        MotionBespokePurchase::create([
            'commission_id' => $commission->id, 'site_id' => $site->id, 'amount_irr' => 1, 'purchased_at' => now(),
        ]);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1])
            ->assertSessionHas('error');

        $this->assertSame(0, MotionBespokeAssignment::query()->count());
    }

    public function test_a_customer_actor_cannot_assign_or_rollback_or_revoke(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->grantBaseEntitlement($site);
        $this->bindPublishedAsset('bespoke-1', 1);
        $commission = $this->publishedAndAssignedCommission($site, 'bespoke-1', 1);
        $this->actingAs($this->customer($site));

        $this->post(route('admin.motion-graphics.bespoke.assign', [$site, $commission]), ['version' => 1])->assertForbidden();
        $this->post(route('admin.motion-graphics.bespoke.rollback', [$site, $commission]), ['version' => 1])->assertForbidden();
        $this->post(route('admin.motion-graphics.bespoke.revoke', [$site, $commission]))->assertForbidden();
    }

    public function test_the_commission_status_endpoint_never_leaks_another_sites_commissions(): void
    {
        $siteA = $this->createSite('Site A');
        $this->enableSite($siteA);
        $this->grantBaseEntitlement($siteA);
        $this->bindPublishedAsset('bespoke-a', 1);
        $this->publishedAndAssignedCommission($siteA, 'bespoke-a', 1);

        $siteB = $this->createSite('Site B');
        $this->enableSite($siteB);
        $keyForB = $this->issueLicenseFor($siteB, 'site-b2.test');

        $response = $this->withToken($keyForB)->getJson('/api/motion-runtime/bespoke-commissions?domain=site-b2.test');

        $response->assertOk()->assertJson(['commissions' => []]);
    }
}
