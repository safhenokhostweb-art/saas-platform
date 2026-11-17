<?php

namespace Tests\Feature\Onboarding;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\CustomerInstallation;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Models\Theme;
use App\Services\AssetProvisioning\SiteAssetEntitlementService;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;
use App\Services\CustomerInstallation\Stages\ApplySiteEntitlementsStage;
use App\Services\CustomerInstallation\Stages\ProvisionLicenseStage;
use App\Services\Onboarding\SiteOnboardingService;
use Database\Seeders\SolarIndustryThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessGate;
use Tests\TestCase;

/**
 * Installation 53 (solar.safhenokhost.ir, 2026-11-17) was rolled back at
 * APPLY_SITE_ENTITLEMENTS: "Kit «marketing_automation» cannot be licensed
 * to a site". The wizard listed every license-only module as a kit: 56 on
 * production, 53 of them core platform modules with license_required, which
 * ModuleLicenseGrantService::grant() never licenses (paid only). The admin
 * ticked them; the stage failed on the first core one.
 */
class OnboardingSiteKitsTest extends TestCase
{
    use OnboardingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->platformRootDomain();
        config(['services.customer_provision_secret' => 'provision-secret-for-tests']);
    }

    /** The catalog's marketing_automation with production's flags (core, license_required). */
    private function coreLicenseRequired(string $slug = 'marketing_automation'): Module
    {
        return Module::updateOrCreate(['slug' => $slug], [
            'title' => 'اتوماسیون بازاریابی', 'type' => Module::TYPE_CORE,
            'is_active' => true, 'license_required' => true, 'free_assignable' => true,
        ]);
    }

    /** A second paid, license-only kit, like production's motion-graphics-premium. */
    private function paidKit(string $slug = 'motion-graphics-premium'): Module
    {
        return Module::updateOrCreate(['slug' => $slug], [
            'title' => 'موشن پریمیوم', 'type' => Module::TYPE_PAID,
            'is_active' => true, 'license_required' => true,
        ]);
    }

    private function run_stage(string $class, CustomerInstallation $installation, FakeRemoteDriver $driver): StageResult
    {
        $installation->refresh();
        $ctx = new InstallationContext($installation, CustomerInstallRequest::fromArray($installation->request_payload), $driver);

        return app($class)->execute($ctx);
    }

    private function installation(array $payload): CustomerInstallation
    {
        $server = $this->server();
        $payload = array_merge([
            'company' => 'سولار', 'owner_name' => 'صفحه نخست', 'email' => '', 'phone' => '09927530317',
            'domain' => 'solar.' . self::ROOT, 'license_type' => 'professional', 'theme_slug' => '', 'modules' => ['forms'],
            'backup_policy' => 'daily', 'smtp' => [], 'ssl_enabled' => true, 'remote_server_id' => $server->id,
            'install_mode' => 'MODE_FRESH',
        ], $payload);

        return CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => $this->admin()->id, 'install_mode' => 'MODE_FRESH',
            'status' => 'running', 'customer_company' => $payload['company'], 'customer_email' => '',
            'customer_domain' => $payload['domain'], 'request_payload' => $payload,
        ]);
    }

    public function test_the_wizard_offers_only_kits_a_site_can_be_licensed_for(): void
    {
        $core = $this->coreLicenseRequired();
        $paid = $this->paidKit();
        $solar = $this->solarKit();

        $kits = app(SiteOnboardingService::class)->kits();

        $this->assertContains($solar->id, $kits->pluck('id')->all());
        $this->assertContains($paid->id, $kits->pluck('id')->all());
        $this->assertNotContains($core->id, $kits->pluck('id')->all(), 'a core module is never a site kit');
        $this->assertTrue($kits->every(fn (Module $m) => $m->type === Module::TYPE_PAID && $m->isLicenseOnly()));
        $this->assertTrue($core->isLicenseOnly() && ! $core->isSiteLicensableKit());
        $this->assertSame(
            $kits->pluck('id')->all(),
            array_column(app(SiteOnboardingService::class)->wizardOptions()['kits'], 'id'),
        );
    }

    public function test_submitting_a_core_module_as_a_kit_is_rejected_before_anything_is_created(): void
    {
        $core = $this->coreLicenseRequired();

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), [
            'business_vertical' => 'solar_energy', 'kit_module_ids' => [$this->solarKit()->id, $core->id],
        ]))->assertSessionHasErrors('kit_module_ids');

        $this->assertSame(0, CustomerInstallation::query()->count());
        Queue::assertNotPushed(CustomerInstallationPipelineJob::class);
    }

    /**
     * A stored payload like installation 53's (real kits first, then a core
     * module) fails closed, as ADR-088 intends, but before writing anything:
     * it used to license the leading kits and only then fail on the core one.
     */
    public function test_a_payload_with_a_core_module_fails_closed_before_any_license_is_written(): void
    {
        $core = $this->coreLicenseRequired();
        $paid = $this->paidKit();
        $solar = $this->solarKit();
        $installation = $this->installation([
            'business_vertical' => 'solar_energy',
            'kit_module_ids' => [$solar->id, $paid->id, $core->id],
        ]);
        $driver = new FakeRemoteDriver();
        $this->assertTrue($this->run_stage(ProvisionLicenseStage::class, $installation, $driver)->success);

        $result = $this->run_stage(ApplySiteEntitlementsStage::class, $installation, $driver);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('«marketing_automation»', $result->error);
        $this->assertStringContainsString('کیت قابل‌لایسنس برای سایت نیستند', $result->error);
        $this->assertStringNotContainsString('solar_industry_kit»', $result->error, 'only the offending modules are named');
        $site = Site::query()->findOrFail($installation->fresh()->site_id);
        $this->assertSame(0, ModuleLicense::query()->where('site_id', $site->id)->count(), 'nothing licensed');
        $this->assertNull($site->fresh()->business_vertical, 'nothing written');
    }

    public function test_several_real_kits_are_all_licensed_and_activated(): void
    {
        $paid = $this->paidKit();
        $solar = $this->solarKit();
        $installation = $this->installation(['business_vertical' => 'solar_energy', 'kit_module_ids' => [$solar->id, $paid->id]]);
        $driver = new FakeRemoteDriver();
        $this->run_stage(ProvisionLicenseStage::class, $installation, $driver);

        $result = $this->run_stage(ApplySiteEntitlementsStage::class, $installation, $driver);

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame(['solar_industry_kit' => 'active', 'motion-graphics-premium' => 'active'], $result->output['kits']);
        $site = Site::query()->findOrFail($installation->fresh()->site_id);
        $this->assertSame('solar_energy', $site->business_vertical);
        $this->assertSame(2, ModuleLicense::query()->where(['site_id' => $site->id, 'status' => ModuleLicense::STATUS_ACTIVE])->count());
    }

    public function test_an_unknown_module_id_still_fails_the_stage(): void
    {
        $installation = $this->installation(['business_vertical' => 'solar_energy', 'kit_module_ids' => [999_999]]);
        $driver = new FakeRemoteDriver();
        $this->run_stage(ProvisionLicenseStage::class, $installation, $driver);

        $result = $this->run_stage(ApplySiteEntitlementsStage::class, $installation, $driver);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('#999999 does not exist', $result->error);
    }

    /**
     * The full solar path as the wizard now submits it: solar profile, the
     * solar-industry theme with all 24 pages, the solar kit. PROVISION_LICENSE
     * then APPLY_SITE_ENTITLEMENTS succeed and the solar templates unlock.
     */
    public function test_a_solar_profile_onboarding_with_the_24_page_theme_passes_entitlements(): void
    {
        $this->artisan('theme-catalog:rebuild', ['--slug' => SolarIndustryThemeSeeder::SLUG])->assertSuccessful();
        $this->artisan('theme-release:backfill', ['--slug' => SolarIndustryThemeSeeder::SLUG])->assertSuccessful();
        $theme = Theme::whereNull('site_id')->where('slug', SolarIndustryThemeSeeder::SLUG)->firstOrFail();
        $this->assertCount(24, $theme->currentRelease->snapshot['pages']);
        $this->coreLicenseRequired(); // present in the catalog, as on production

        $options = app(SiteOnboardingService::class)->wizardOptions();
        $profile = $options['profiles']['solar_energy'];
        $this->assertSame($theme->id, $profile['theme_id']);
        $this->assertSame([$this->solarKit()->id], $profile['kit_ids']);
        $this->assertSame(24, collect($options['themes'])->firstWhere('id', $theme->id)['pages']);

        $this->actingAs($this->admin())->post(route('admin.onboarding.store'), $this->submission($this->server(), [
            'domain' => 'solar', 'business_vertical' => 'solar_energy',
            'kit_module_ids' => $profile['kit_ids'], 'theme_id' => $theme->id,
        ]))->assertSessionHasNoErrors();
        $installation = CustomerInstallation::query()->sole();
        $this->assertSame($profile['kit_ids'], $installation->request_payload['kit_module_ids']);

        $driver = new FakeRemoteDriver();
        $this->assertTrue($this->run_stage(ProvisionLicenseStage::class, $installation, $driver)->success);
        $result = $this->run_stage(ApplySiteEntitlementsStage::class, $installation, $driver);

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame(['solar_industry_kit' => 'active'], $result->output['kits']);
        $site = Site::query()->findOrFail($installation->fresh()->site_id);
        $entitlements = app(SiteAssetEntitlementService::class)->forSite($site->fresh());
        foreach (['lib:solar-page-home', 'lib:solar-page-projects', 'lib:solar-page-solar_pumping', 'lib:auth-solar-industrial', 'lib:solar-header'] as $ref) {
            $this->assertTrue(AssetAccessGate::decideTemplate($ref, $entitlements)->allowed(), "{$ref} unlocked");
        }
    }
}
