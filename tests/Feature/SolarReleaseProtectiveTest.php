<?php

namespace Tests\Feature;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Models\MotionSiteAssetGrant;
use App\Modules\MotionGraphics\Services\MotionCustomerRuntimeService;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Stages\ApplySiteEntitlementsStage;
use Database\Seeders\MotionGraphicsDefaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

class SolarReleaseProtectiveTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
        $this->registerMotionGraphicsModule();
    }

    public function test_solar_homepage_has_slider_block(): void
    {
        $homeTemplate = TemplateLibrary::find('lib:solar-page-home');
        $this->assertNotNull($homeTemplate);

        $types = array_column($homeTemplate['blocks'] ?? [], 'type');
        $this->assertContains('slider', $types, 'solar-page-home template must contain the slider block');

        $sliderBlock = collect($homeTemplate['blocks'])->firstWhere('type', 'slider');
        $this->assertNotEmpty($sliderBlock['props']['slides']);
        $this->assertCount(2, $sliderBlock['props']['slides']);
    }

    public function test_solar_calculator_module_is_site_licensable_kit(): void
    {
        $calcModule = Module::firstOrCreate(
            ['slug' => 'solar_calculator'],
            ['title' => 'ماشین‌حساب خورشیدی', 'type' => 'paid', 'license_required' => true, 'is_active' => true]
        );

        $this->assertTrue($calcModule->isSiteLicensableKit(), 'solar_calculator must be a site licensable kit');

        $customerSiteCalcJson = base_path('../customer-site/resources/views/themes/solar-industry/solar-page-solar_calculator.json');
        if (file_exists($customerSiteCalcJson)) {
            $data = json_decode(file_get_contents($customerSiteCalcJson), true);
            $types = array_column($data['blocks'] ?? [], 'type');
            $this->assertContains('module:solar_calculator', $types, 'Customer-site calculator template must contain module:solar_calculator');
        }
    }

    public function test_solar_login_page_is_auth_canvas(): void
    {
        $loginTemplate = TemplateLibrary::find('lib:auth-solar-industrial');
        $this->assertNotNull($loginTemplate);

        $types = array_column($loginTemplate['blocks'] ?? [], 'type');
        $this->assertContains('auth_canvas', $types, 'auth-solar-industrial template must contain auth_canvas');
    }

    public function test_apply_site_entitlements_auto_enables_motion_feature_and_grants_solar_motion(): void
    {
        // 1. Setup motion catalog
        $this->seed(MotionGraphicsDefaultSeeder::class);
        $solarMotion = MotionGraphic::where('slug', 'solar-motion-graphic')->firstOrFail();

        // 2. Setup site & installing admin
        $admin = User::factory()->create(['is_admin' => true]);
        $site = $this->createSite();
        $site->business_vertical = null;
        $site->features = null;
        $site->save();

        // 3. Setup modules (motion-graphics-premium and solar_industry_kit)
        $mgPremium = Module::firstOrCreate(
            ['slug' => 'motion-graphics-premium'],
            ['title' => 'موشن گرافیک پرمیوم', 'type' => 'paid', 'license_required' => true, 'is_active' => true]
        );
        $solarKit = Module::firstOrCreate(
            ['slug' => 'solar_industry_kit'],
            ['title' => 'کیت صنعت برق', 'type' => 'paid', 'license_required' => true, 'is_active' => true]
        );

        $server = CustomerRemoteServer::create([
            'label' => 'Test VPS',
            'type' => 'ssh',
            'host' => '127.0.0.1',
            'port' => 22,
            'username' => 'root',
            'auth_method' => 'key',
        ]);

        $payload = [
            'company' => 'سولار',
            'owner_name' => 'مدیر سولار',
            'email' => 'solar@test.ir',
            'phone' => '09120000000',
            'domain' => 'solar.test',
            'remote_server_id' => $server->id,
            'business_vertical' => 'solar_energy',
            'kit_module_ids' => [$mgPremium->id, $solarKit->id],
        ];

        $installation = CustomerInstallation::create([
            'site_id' => $site->id,
            'remote_server_id' => $server->id,
            'installer_user_id' => $admin->id,
            'install_mode' => 'MODE_FRESH',
            'status' => 'running',
            'customer_company' => $payload['company'],
            'customer_email' => 'solar@test.ir',
            'customer_domain' => $payload['domain'],
            'request_payload' => $payload,
        ]);

        $ctx = new InstallationContext(
            $installation,
            CustomerInstallRequest::fromArray($installation->request_payload),
            new FakeRemoteDriver()
        );

        // 4. Run ApplySiteEntitlementsStage
        $stage = app(ApplySiteEntitlementsStage::class);
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success, 'Stage should succeed');

        // 5. Verify motion_graphics_integration is ENABLED in features
        $freshSite = $site->fresh();
        $this->assertTrue(
            (bool) ($freshSite->features['motion_graphics_integration'] ?? false),
            'motion_graphics_integration must be automatically enabled'
        );

        // 6. Verify solar-motion-graphic is granted to this site
        $this->assertTrue(
            MotionSiteAssetGrant::isGranted((int) $site->id, $solarMotion->uuid),
            'solar-motion-graphic must be automatically granted to solar_energy site'
        );
    }

    public function test_motion_customer_runtime_returns_three_motions_for_entitled_solar_site(): void
    {
        $this->seed(MotionGraphicsDefaultSeeder::class);
        $solarMotion = MotionGraphic::where('slug', 'solar-motion-graphic')->firstOrFail();

        $site = $this->createSite();
        $site->features = ['motion_graphics_integration' => true];
        $site->business_vertical = 'solar_energy';
        $site->save();

        // License motion-graphics-premium
        $mgPremium = Module::firstOrCreate(
            ['slug' => 'motion-graphics-premium'],
            ['title' => 'موشن گرافیک پرمیوم', 'type' => 'paid', 'license_required' => true, 'is_active' => true]
        );
        $admin = User::factory()->create(['is_admin' => true]);
        app(\App\Services\ModuleLicenseGrantService::class)->grant($site, $mgPremium->id, $admin);

        // Grant solar motion
        MotionSiteAssetGrant::create([
            'site_id' => $site->id,
            'asset_id' => $solarMotion->uuid,
            'status' => MotionSiteAssetGrant::STATUS_ACTIVE,
            'source' => MotionSiteAssetGrant::SOURCE_ADMIN_GRANT,
            'granted_by_actor_identifier' => 'admin:' . $admin->id,
            'granted_at' => now(),
        ]);

        $service = app(MotionCustomerRuntimeService::class);
        $context = new MotionAdapterContext('test', (string) $site->id, (string) Str::uuid());

        $manifest = $service->buildManifestForSite($site, $context);

        $assetSlugs = array_map(function ($a) {
            return MotionGraphic::where('uuid', $a->assetId)->value('slug');
        }, $manifest->assets);

        $this->assertContains('hero-motion', $assetSlugs);
        $this->assertContains('business-growth-motion', $assetSlugs);
        $this->assertContains('solar-motion-graphic', $assetSlugs);
        $this->assertEmpty($manifest->lockedAssets);
    }
}
