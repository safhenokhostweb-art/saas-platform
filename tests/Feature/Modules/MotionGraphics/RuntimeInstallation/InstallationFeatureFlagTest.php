<?php

namespace Tests\Feature\Modules\MotionGraphics\RuntimeInstallation;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Task scope item 6: the installation service is entirely unavailable
 * while `motion_graphics_integration` is off (the default) — no package
 * activation, no runtime projection generation.
 */
class InstallationFeatureFlagTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
    }

    public function test_the_flag_defaults_to_disabled(): void
    {
        // Phase 6: global default is now `null` (per-site override,
        // docs/motion-integration-phase6-audit.md §6), still effectively
        // disabled with no override present.
        $this->assertFalse((bool) config('feature-flags.motion_graphics_integration'));
    }

    public function test_installation_is_unavailable_while_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            'template-1', [], [], [], null, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );

        $this->assertSame(0, SiteAssetPackage::query()->count());
    }

    public function test_projection_generation_is_unavailable_while_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);
        $site = $this->createSite();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionRuntimeInstallationService::class)->projectSitePackage(
            new SiteAssetPackage(), new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );
    }
}
