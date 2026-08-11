<?php

namespace Tests\Feature\Modules\MotionGraphics\Commercialization;

use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\LicenseServerService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 9 task section 9 — the security cases specific to the new
 * commercial entitlement layer. AI-metadata/source-definition/Master
 * Asset exposure and cross-tenant manifest isolation were already proven
 * exhaustively in Phases 6-8 (unaffected by this phase, still passing —
 * see TEST_REPORT.md) and are not re-duplicated here; this file covers
 * only what's genuinely new: expired/revoked license rejection *through
 * the real HTTP API*, and direct unauthorized access to the admin
 * dashboard's new licensing fields.
 */
class SecurityValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
        (new ModuleSeeder())->run();
    }

    private function enableSite(Site $site): void
    {
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
    }

    private function activePackageFor(Site $site): SiteAssetPackage
    {
        $templatePackage = TemplateAssetPackage::query()->firstOrCreate(
            ['template_reference' => 'template-1', 'package_version' => 1],
            ['required_assets' => [['asset_id' => 'motion-1', 'version' => 1]]],
        );
        $package = SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id,
            'activation_status' => SiteAssetPackage::STATUS_ACTIVE, 'activated_at' => now(),
        ]);
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $package->id, 'asset_id' => 'motion-1',
            'active_version' => 1, 'bound_checksum' => 'checksum-1', 'installed_at' => now(),
        ]);

        return $package->fresh(['items']);
    }

    private function issueLicenseFor(Site $site, string $domain = 'customer.test'): string
    {
        return $this->app->make(LicenseServerService::class)->issue($site, $domain);
    }

    public function test_direct_api_access_with_an_expired_module_license_is_denied(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->activePackageFor($site);
        $this->bindPublishedAsset('motion-1', 1);
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
        ModuleLicense::create([
            'site_id' => $site->id, 'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME, 'purchased_at' => now(),
            'expires_at' => now()->subDay(), 'status' => ModuleLicense::STATUS_ACTIVE,
        ]);
        $key = $this->issueLicenseFor($site);

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'license_denied']);
    }

    public function test_direct_api_access_with_a_revoked_module_license_is_denied(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->activePackageFor($site);
        $this->bindPublishedAsset('motion-1', 1);
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
        ModuleLicense::create([
            'site_id' => $site->id, 'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME, 'purchased_at' => now(),
            'status' => ModuleLicense::STATUS_REVOKED, 'revoked_at' => now(),
        ]);
        $key = $this->issueLicenseFor($site);

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'license_denied']);
    }

    public function test_a_site_with_no_module_license_at_all_is_denied_via_the_real_api(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->activePackageFor($site);
        $this->bindPublishedAsset('motion-1', 1);
        $key = $this->issueLicenseFor($site);

        $response = $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'license_denied']);
    }

    public function test_a_licensed_sites_manifest_can_never_be_fetched_using_another_sites_credentials(): void
    {
        $siteA = $this->createSite('Site A');
        $this->enableSite($siteA);
        $this->activePackageFor($siteA);
        $module = Module::where('slug', RealMotionLicenseGateway::MODULE_SLUG)->firstOrFail();
        ModuleLicense::create([
            'site_id' => $siteA->id, 'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME, 'purchased_at' => now(),
            'status' => ModuleLicense::STATUS_ACTIVE,
        ]);
        $this->bindPublishedAsset('motion-1', 1);

        // Site B is enabled (flag on) but deliberately NOT licensed — this
        // isolates the assertion to the *license* layer specifically,
        // rather than being satisfied by the (also-correct, but
        // different) flag-layer denial a never-enabled site would hit.
        $siteB = $this->createSite('Site B');
        $this->enableSite($siteB);
        $keyForB = $this->issueLicenseFor($siteB, 'site-b.test');

        $response = $this->withToken($keyForB)->getJson('/api/motion-runtime/manifest?domain=site-b.test');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'license_denied']);
    }

    public function test_unauthorized_customer_cannot_read_admin_dashboard_licensing_fields(): void
    {
        $site = $this->createSite();
        $this->enableSite($site);
        $this->actingAs($this->customer($site));

        $this->get(route('admin.motion-graphics.dashboard', $site))->assertForbidden();
    }
}
