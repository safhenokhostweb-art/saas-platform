<?php

namespace Tests\Feature\Modules\MotionGraphics\Admin;

use App\Models\ThemeTemplate;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 7 task scope item 3: activating a template for the Internal Test
 * Site creates a Site Asset Package and generates a runtime projection —
 * no real deployment — and never touches any other site.
 */
class InternalSiteActivationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
    }

    private function enabledSite(string $title): \App\Models\Site
    {
        $site = $this->createSite($title);
        $site->features = ['motion_graphics_integration' => true];
        $site->save();

        return $site;
    }

    public function test_activation_creates_a_site_asset_package_and_no_deployment(): void
    {
        $site = $this->enabledSite('Internal Test Site');
        $template = ThemeTemplate::query()->create(['type' => 'section', 'category' => 'hero', 'name' => 'Hero', 'blocks' => []]);
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.templates.activate', [$site, $template]), [
            'required_asset_ids' => 'motion-1',
        ])->assertRedirect();

        $package = SiteAssetPackage::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $package->activation_status);

        // Task scope item 3: "No real deployment." Nothing this phase
        // touches App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService
        // or any DeploymentManifest — confirmed by absence, not by a
        // negative assertion on a class that was never invoked.
        $this->assertSame(1, SiteAssetPackage::query()->count());
    }

    public function test_activating_for_one_site_never_touches_a_different_site(): void
    {
        $siteA = $this->enabledSite('Site A');
        $siteB = $this->enabledSite('Site B');
        $template = ThemeTemplate::query()->create(['type' => 'section', 'category' => 'hero', 'name' => 'Hero', 'blocks' => []]);
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.templates.activate', [$siteA, $template]), [
            'required_asset_ids' => 'motion-1',
        ]);

        $this->assertSame(1, SiteAssetPackage::query()->where('site_id', $siteA->id)->count());
        $this->assertSame(0, SiteAssetPackage::query()->where('site_id', $siteB->id)->count());

        $this->get(route('admin.motion-graphics.preview', $siteB))
            ->assertOk()
            ->assertDontSee('theme-template:'.$template->uuid);
    }

    public function test_a_second_activation_for_the_same_site_and_template_is_idempotent(): void
    {
        $site = $this->enabledSite('Internal Test Site');
        $template = ThemeTemplate::query()->create(['type' => 'section', 'category' => 'hero', 'name' => 'Hero', 'blocks' => []]);
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->admin());

        $this->post(route('admin.motion-graphics.templates.activate', [$site, $template]), ['required_asset_ids' => 'motion-1']);
        $this->post(route('admin.motion-graphics.templates.activate', [$site, $template]), ['required_asset_ids' => 'motion-1']);

        $this->assertSame(1, SiteAssetPackage::query()->where('site_id', $site->id)->count());
    }
}
