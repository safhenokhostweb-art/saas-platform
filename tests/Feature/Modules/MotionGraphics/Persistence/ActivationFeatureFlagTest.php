<?php

namespace Tests\Feature\Modules\MotionGraphics\Persistence;

use App\Models\User;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService;
use App\Modules\MotionGraphics\Services\TemplateAssetPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

/**
 * Task scope item 8: the `motion_graphics_integration` flag gates the
 * entire persistence/activation workflow — when disabled, no activation
 * workflow is available at all, resolved through the real container.
 */
class ActivationFeatureFlagTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    private function admin(): User
    {
        $admin = new User();
        $admin->id = 1;
        $admin->is_admin = true;
        $admin->site_id = null;

        return $admin;
    }

    public function test_the_flag_defaults_to_disabled(): void
    {
        // Phase 6: global default is now `null` (per-site override,
        // docs/motion-integration-phase6-audit.md §6), still effectively
        // disabled with no override present.
        $this->assertFalse((bool) config('feature-flags.motion_graphics_integration'));
    }

    public function test_template_package_creation_is_unavailable_while_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);
        $this->actingAs($this->admin());

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(TemplateAssetPackageService::class)->createTemplateAssetPackage(
            'template-1', [], [], [], new MotionAdapterContext('user:1', 'platform', 'corr-1')
        );
    }

    public function test_site_package_activation_is_unavailable_while_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);
        $site = $this->createSite();
        $this->actingAs($this->admin());

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(SiteAssetPackageActivationService::class)->activate(
            new TemplateAssetPackage(),
            null,
            new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
        );
    }

    public function test_rollback_is_unavailable_while_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);
        $this->actingAs($this->admin());

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(SiteAssetPackageActivationService::class)->rollback(
            new SiteAssetPackageItem(), 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1')
        );
    }
}
