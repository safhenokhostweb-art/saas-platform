<?php

namespace Tests\Feature\Modules\MotionGraphics\Admin;

use App\Models\ThemeTemplate;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 7 access control: guests are redirected to login, customers get
 * 403 (task scope item 2 — "Denied: Customer: create/modify/activate
 * package, access Master management"), and every route fails closed for
 * a site that has never been enabled for the feature flag.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $site = $this->createSite();

        $this->get(route('admin.motion-graphics.dashboard', $site))->assertRedirect(route('login'));
    }

    public function test_a_customer_is_denied_every_admin_motion_graphics_route(): void
    {
        $site = $this->createSite();
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
        $template = ThemeTemplate::query()->create(['type' => 'section', 'category' => 'hero', 'name' => 'Hero', 'blocks' => []]);
        $this->actingAs($this->customer($site));

        $this->get(route('admin.motion-graphics.dashboard', $site))->assertForbidden();
        $this->get(route('admin.motion-graphics.templates', $site))->assertForbidden();
        $this->get(route('admin.motion-graphics.preview', $site))->assertForbidden();
        $this->post(route('admin.motion-graphics.templates.assign', [$site, $template]), [
            'required_asset_ids' => 'motion-1',
        ])->assertForbidden();
    }

    public function test_a_customer_cannot_create_a_package_even_by_guessing_the_route(): void
    {
        $site = $this->createSite();
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
        $template = ThemeTemplate::query()->create(['type' => 'section', 'category' => 'hero', 'name' => 'Hero', 'blocks' => []]);
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->customer($site));

        $this->post(route('admin.motion-graphics.templates.assign', [$site, $template]), [
            'required_asset_ids' => 'motion-1',
        ])->assertForbidden();

        $this->assertSame(0, TemplateAssetPackage::query()->count());
    }

    public function test_every_action_fails_closed_for_a_site_that_was_never_enabled(): void
    {
        $site = $this->createSite(); // no `features` override — flag disabled
        $template = ThemeTemplate::query()->create(['type' => 'section', 'category' => 'hero', 'name' => 'Hero', 'blocks' => []]);
        $this->bindPublishedAsset('motion-1', 1);
        $this->actingAs($this->admin());

        $this->get(route('admin.motion-graphics.dashboard', $site))
            ->assertOk()
            ->assertSee('فعال نیست');

        $this->post(route('admin.motion-graphics.templates.assign', [$site, $template]), [
            'required_asset_ids' => 'motion-1',
        ])->assertRedirect(route('admin.motion-graphics.templates', $site));

        $this->assertSame(0, TemplateAssetPackage::query()->count());
    }
}
