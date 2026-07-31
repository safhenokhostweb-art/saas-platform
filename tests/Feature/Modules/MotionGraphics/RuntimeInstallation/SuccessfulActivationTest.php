<?php

namespace Tests\Feature\Modules\MotionGraphics\RuntimeInstallation;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\PackageSnapshot;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * End-to-end through the real container: Template Selected -> Template
 * Asset Package Resolver -> Compatibility Validation -> Site Asset
 * Package Creation -> Version Snapshot Binding -> Runtime Projection
 * Generation.
 */
class SuccessfulActivationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('feature-flags.motion_graphics_integration', true);
        $this->fakeActivityLogger();
    }

    public function test_a_full_installation_creates_the_expected_persistence_trail(): void
    {
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        $plan = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            'template-1',
            [['asset_id' => 'motion-1']],
            [],
            [],
            'brand-a',
            new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
        );

        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $plan->sitePackage->activation_status);
        $this->assertSame('brand-a', $plan->sitePackage->brand_variant_reference);
        $this->assertCount(1, $plan->projections);
        $this->assertSame(1, $plan->projections[0]->publishedVersion);
        $this->assertSame(1, SiteAssetPackage::query()->count());
        $this->assertSame(1, AssetActivation::query()->count());
        $this->assertSame(1, PackageSnapshot::query()->count());
    }
}
