<?php

namespace Tests\Feature\Modules\MotionGraphics\Library;

use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Models\User;
use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\LicenseServerService;
use App\Services\ModuleLicenseService;
use Database\Seeders\MotionGraphicsDemoSiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * ADR-078 end to end, with no fakes anywhere in the motion chain: real
 * catalog rows, real module licenses, real template/site package
 * activation, and the real `tenant.api` manifest endpoint customer-site
 * calls (MotionRuntimeManifestReader).
 */
final class MotionLibraryEndToEndTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    private const HERO = 'موشن قهرمان سازمانی';
    private const GROWTH = 'موشن رشد کسب‌وکار';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'site_id' => null]);
    }

    private function provision(Site $site, User $admin, bool $premium)
    {
        return $this->app->make(MotionGraphicsDemoSiteSeeder::class)->provision($site->fresh(), $admin, $premium);
    }

    private function fetchManifest(Site $site): TestResponse
    {
        $key = $this->app->make(LicenseServerService::class)->issue($site, 'customer.test');

        return $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=customer.test');
    }

    private function revoke(Site $site, string $moduleSlug): void
    {
        $moduleId = Module::query()->where('slug', $moduleSlug)->value('id');
        foreach (ModuleLicense::query()->where('site_id', $site->id)->where('module_id', $moduleId)->get() as $license) {
            $this->app->make(ModuleLicenseService::class)->revoke($license);
        }
    }

    public function test_a_site_with_only_the_base_license_receives_only_the_free_hero_motion(): void
    {
        $site = $this->createSite();
        $manifest = $this->provision($site, $this->admin(), premium: false);

        $this->assertSame([self::HERO], array_map(fn ($a) => $a->name, $manifest->assets));
        $this->assertSame(1, SiteAssetPackageItem::query()->count(), 'the unlicensed premium asset must never be installed into the site package');

        $response = $this->fetchManifest($site)->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('schema_version', '1.0')
            ->assertJsonPath('template_reference', MotionLibraryCatalogService::TEMPLATE_REFERENCE)
            ->assertJsonCount(1, 'assets')
            ->assertJsonPath('assets.0.asset_type', 'svg')
            ->assertJsonPath('assets.0.name', self::HERO)
            ->assertJsonPath('assets.0.optimized_reference', '/motion/hero-motion/v3/animation.svg');

        // Exactly the shape customer-site's MotionRuntimeManifestReader accepts.
        $asset = $response->json('assets.0');
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $asset['asset_id']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $asset['checksum']);
        $this->assertIsInt($asset['published_version']);
        foreach (['source_definition', 'ai_metadata', 'editable_schema', 'ownership', 'storage_path', 'prompt'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $asset);
        }

        // ADR-079: the public runtime-parameter contract rides along.
        $keys = array_column($asset['parameters'], 'key');
        $this->assertSame('window_title', $keys[0]);
        $this->assertContains('primary', $keys);
        $this->assertContains('speed', $keys);
        $this->assertSame('/motion/hero-motion/v3/animation.svg', $asset['optimized_reference']);
    }

    public function test_a_premium_license_adds_business_growth_and_revoking_it_removes_it_without_reactivation(): void
    {
        $site = $this->createSite();
        $admin = $this->admin();
        $this->provision($site, $admin, premium: false);

        $manifest = $this->provision($site, $admin, premium: true);
        $this->assertEqualsCanonicalizing([self::HERO, self::GROWTH], array_map(fn ($a) => $a->name, $manifest->assets));
        $this->fetchManifest($site)->assertOk()->assertJsonCount(2, 'assets');

        $this->revoke($site, MotionAssetLicensePolicy::PREMIUM_MODULE_SLUG);

        $this->fetchManifest($site)->assertOk()
            ->assertJsonCount(1, 'assets')
            ->assertJsonPath('assets.0.name', self::HERO);
    }

    public function test_provisioning_twice_changes_nothing(): void
    {
        $site = $this->createSite();
        $admin = $this->admin();

        $first = $this->provision($site, $admin, premium: true);
        $second = $this->provision($site, $admin, premium: true);

        $this->assertSame($first->manifestChecksum, $second->manifestChecksum);
        $this->assertSame(1, TemplateAssetPackage::query()->count());
        $this->assertSame(2, SiteAssetPackageItem::query()->count());
        $this->assertSame(2, AssetActivation::query()->count());
        $this->assertSame(1, ModuleLicense::query()->where('site_id', $site->id)
            ->where('module_id', Module::query()->where('slug', MotionAssetLicensePolicy::PREMIUM_MODULE_SLUG)->value('id'))->count());
    }

    public function test_without_the_base_module_license_the_manifest_is_unavailable(): void
    {
        $site = $this->createSite();
        $this->provision($site, $this->admin(), premium: true);

        $this->revoke($site, RealMotionLicenseGateway::MODULE_SLUG);

        $this->fetchManifest($site)->assertOk()
            ->assertJson(['available' => false, 'reason' => 'license_denied']);
    }

    public function test_another_site_gets_nothing_from_this_sites_provisioning(): void
    {
        $site = $this->createSite('A');
        $other = $this->createSite('B');
        $this->provision($site, $this->admin(), premium: true);

        $this->fetchManifest($other)->assertOk()->assertJsonPath('available', false);
    }
}
