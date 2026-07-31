<?php

namespace Tests\Feature\Modules\MotionGraphics\Admin;

use App\Models\ThemeTemplate;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 7 (docs/motion-admin-management/ADMIN_WORKFLOW.md): the full
 * HTTP-level admin journey — select site, view dashboard, assign a
 * package to a template, activate it for the internal test site, and
 * preview the resulting runtime projection — through the real routes,
 * real container, real TenantContext (not a test fake), exactly as a
 * Platform Admin would use it.
 */
class AdminWorkflowSuccessTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
    }

    private function enabledInternalTestSite(): \App\Models\Site
    {
        $site = $this->createSite('Internal Test Site');
        $site->features = ['motion_graphics_integration' => true];
        $site->save();

        return $site;
    }

    public function test_the_full_admin_journey_succeeds(): void
    {
        $site = $this->enabledInternalTestSite();
        $template = ThemeTemplate::query()->create(['type' => 'section', 'category' => 'hero', 'name' => 'Hero', 'blocks' => []]);
        $this->bindPublishedAsset('motion-1', 1);
        $this->bindAssetLibrarySummary(3);
        $this->actingAs($this->admin());

        $this->get(route('admin.motion-graphics.select-site'))->assertOk();

        $this->get(route('admin.motion-graphics.dashboard', $site))
            ->assertOk()
            ->assertSee('کل دارایی‌های موشن');

        $this->get(route('admin.motion-graphics.templates', $site))
            ->assertOk()
            ->assertSee($template->name);

        $this->post(route('admin.motion-graphics.templates.assign', [$site, $template]), [
            'required_asset_ids' => 'motion-1',
        ])->assertRedirect(route('admin.motion-graphics.templates', $site));

        $this->assertSame(1, \App\Modules\MotionGraphics\Models\TemplateAssetPackage::query()->count());

        $this->post(route('admin.motion-graphics.templates.activate', [$site, $template]), [
            'required_asset_ids' => 'motion-1',
        ])->assertRedirect(route('admin.motion-graphics.preview', $site));

        $sitePackage = SiteAssetPackage::query()->where('site_id', $site->id)->firstOrFail();
        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $sitePackage->activation_status);

        $this->get(route('admin.motion-graphics.preview', $site))
            ->assertOk()
            ->assertSee('theme-template:'.$template->uuid);

        $this->get(route('admin.motion-graphics.preview.show', [$site, $sitePackage]))
            ->assertOk()
            ->assertSee('motion-1');
    }

    public function test_the_compatibility_preview_reports_a_mismatch(): void
    {
        $site = $this->enabledInternalTestSite();
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->admin());

        $response = $this->post(route('admin.motion-graphics.templates.preview', $site), [
            'required_asset_ids' => 'motion-1',
            'compatibility_asset_type' => 'svg', // the fake asset is "lottie"
        ]);

        $response->assertOk()->assertSee('ناسازگار');
    }
}
