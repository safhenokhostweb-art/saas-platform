<?php

namespace Tests\Feature\Modules\MotionGraphics\Library;

use App\Models\Module;
use App\Models\User;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use App\Modules\MotionGraphics\Support\MotionAssetLicensePolicy;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use App\Services\LicenseServerService;
use App\Services\ModuleLicenseService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * ADR-086 guards. Each test pins one rule that uncommitted shared-tree work
 * on 2026-11-14 had silently broken, so a reintroduction fails loudly:
 *
 * - the paid motion modules stay paid: neither is free-assignable, so an
 *   admin ticking a box never replaces a purchase or licence;
 * - an active site_modules row alone (no licence) never unlocks premium
 *   motions;
 * - building a manifest only reads; it never creates a template or site
 *   package for a site an admin has not activated.
 */
final class MotionLicensingGuardsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
    }

    public function test_the_paid_motion_modules_are_never_free_assignable(): void
    {
        ModuleSeeder::seedMotionModules();

        foreach ([RealMotionLicenseGateway::MODULE_SLUG, MotionAssetLicensePolicy::PREMIUM_MODULE_SLUG] as $slug) {
            $module = Module::query()->where('slug', $slug)->firstOrFail();
            $this->assertFalse((bool) $module->free_assignable, "[{$slug}] must not be free-assignable");
            $this->assertFalse($module->isFreeAssignable(), "[{$slug}] must not resolve as free-assignable");
            $this->assertTrue($module->isPaidMarketplace() || $module->isLicenseOnly(), "[{$slug}] must require a purchase or licence");
        }
    }

    public function test_an_active_module_row_without_a_licence_never_unlocks_premium_motions(): void
    {
        ModuleSeeder::seedMotionModules();
        $site = $this->createSite('A');
        $premium = Module::query()->where('slug', MotionAssetLicensePolicy::PREMIUM_MODULE_SLUG)->firstOrFail();
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $premium->id, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->app->make(ModuleLicenseServiceInterface::class)->checkEntitlement($site, $premium->slug);
        $this->assertFalse($result['allowed']);

        $decision = $this->app->make(MotionAssetLicensePolicy::class)->decide($site, ['license_tier' => 'premium', 'visibility' => 'public']);
        $this->assertFalse($decision->allowed);
        $this->assertSame('premium_license_required', $decision->code);
    }

    public function test_building_a_manifest_never_creates_a_package(): void
    {
        $this->app->make(MotionLibraryCatalogService::class)->sync();
        ModuleSeeder::seedMotionModules();
        $site = $this->createSite('A');
        $admin = User::factory()->create(['is_admin' => true, 'site_id' => null]);
        $this->app->make(ModuleLicenseService::class)->grantByAdmin(
            $site, Module::query()->where('slug', RealMotionLicenseGateway::MODULE_SLUG)->value('id'), $admin
        );
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
        $templates = TemplateAssetPackage::query()->count();

        $key = $this->app->make(LicenseServerService::class)->issue($site, 'guard.test');
        $this->withToken($key)->getJson('/api/motion-runtime/manifest?domain=guard.test')
            ->assertOk()->assertJson(['available' => false, 'reason' => 'no_active_package']);

        $this->assertSame(0, SiteAssetPackage::query()->where('site_id', $site->id)->count());
        $this->assertSame($templates, TemplateAssetPackage::query()->count());
    }

    /**
     * The 2026-11-14 auto-provisioning ran only when NOT under unit tests,
     * so no behavioural test could see it. The motion runtime must behave
     * the same in tests and production, and the manifest path must not
     * reach into module assignment.
     */
    public function test_the_motion_runtime_has_no_test_only_branches_or_provisioning(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules/MotionGraphics')));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $this->assertStringNotContainsString('runningUnitTests', (string) file_get_contents($file->getPathname()), $file->getPathname());
        }

        $runtime = (string) file_get_contents(app_path('Modules/MotionGraphics/Services/MotionCustomerRuntimeService.php'));
        $this->assertStringNotContainsString('ModuleAssignmentService', $runtime);
        $this->assertStringNotContainsString('provision', strtolower($runtime));
    }
}
