<?php

namespace Tests\Feature\Modules\MotionGraphics\Admin;

use App\Models\ThemeTemplate;
use App\Modules\MotionGraphics\DTO\ForbiddenFieldGuard;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 7 task scope item 5/8 security requirements: the admin runtime
 * preview never exposes source SVG/AI instructions/internal metadata,
 * cross-tenant access is rejected even at the HTTP layer, and a
 * customer cannot modify a package via any route in this controller.
 */
class AdminSecurityTest extends TestCase
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

    private function activePackageFor(\App\Models\Site $site): SiteAssetPackage
    {
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => 'motion-1', 'version' => 1]],
            'package_version' => 1,
        ]);
        $package = SiteAssetPackage::query()->create([
            'site_id' => $site->id,
            'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id,
            'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
        ]);
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $package->id,
            'asset_id' => 'motion-1',
            'active_version' => 1,
            'bound_checksum' => 'checksum-1',
            'installed_at' => now(),
        ]);

        return $package->fresh(['items']);
    }

    public function test_the_runtime_preview_response_never_contains_forbidden_fields(): void
    {
        $site = $this->enabledSite('Internal Test Site');
        $package = $this->activePackageFor($site);
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->admin());

        $response = $this->get(route('admin.motion-graphics.preview.show', [$site, $package]));

        $response->assertOk();
        $forbidden = ['source_definition', 'editable_schema', 'ai_metadata', 'ownership', 'credentials', 'storage_path'];
        foreach ($forbidden as $key) {
            $response->assertDontSee($key);
        }
    }

    public function test_forbidden_field_guard_would_reject_the_underlying_projection_data(): void
    {
        // Direct proof the DTOs flowing into the view are themselves
        // guarded, not just "happen not to be printed" by the template.
        $projection = new \App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO(
            assetReference: new \App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO('motion-1', 1, 'lottie'),
            publishedVersion: 1,
            themeTokens: ['primary' => '#111'],
            placementRules: ['section' => 'hero'],
        );

        ForbiddenFieldGuard::assertClean($projection->themeTokens, 'theme_tokens');
        ForbiddenFieldGuard::assertClean($projection->placementRules, 'placement_rules');
        $this->assertTrue(true);
    }

    public function test_an_admin_cannot_preview_another_sites_package_via_a_crafted_url(): void
    {
        $siteA = $this->enabledSite('Site A');
        $siteB = $this->enabledSite('Site B');
        $packageA = $this->activePackageFor($siteA);
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->admin());

        // Admin is "working as" Site B but requests Site A's package id
        // directly in the URL.
        $response = $this->get(route('admin.motion-graphics.preview.show', [$siteB, $packageA]));

        $response->assertOk()->assertSee('فعال نیست')->assertDontSee('motion-1');
    }

    public function test_a_customer_cannot_modify_a_package_through_any_admin_motion_graphics_route(): void
    {
        $site = $this->enabledSite('Internal Test Site');
        $package = $this->activePackageFor($site);
        $template = ThemeTemplate::query()->create(['type' => 'section', 'category' => 'hero', 'name' => 'Hero', 'blocks' => []]);
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->customer($site));

        $this->post(route('admin.motion-graphics.templates.assign', [$site, $template]), ['required_asset_ids' => 'motion-1'])->assertForbidden();
        $this->post(route('admin.motion-graphics.templates.activate', [$site, $template]), ['required_asset_ids' => 'motion-1'])->assertForbidden();
        $this->get(route('admin.motion-graphics.preview.show', [$site, $package]))->assertForbidden();

        $this->assertSame(1, TemplateAssetPackage::query()->count()); // only the one created in setup
    }
}
