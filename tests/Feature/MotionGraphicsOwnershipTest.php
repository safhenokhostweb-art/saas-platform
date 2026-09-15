<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicDeployment;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use Tests\TestCase;

final class MotionGraphicsOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // MotionGraphicsServiceProvider was never registered on the host
        // app (config/app.php) until this baseline pass — PlatformAdminResolverAdapter
        // fails closed on the 'motion_graphics_integration' feature flag,
        // and these master-library actions run as a Platform Admin with no
        // site context to attach a per-site features flag to. The global
        // config override is the documented priority-1 mechanism for
        // exactly this case (packages/saas-platform/core-utils/config/feature-flags.php).
        config(['feature-flags.motion_graphics_integration' => true]);

        // publishing_enabled defaults to false (config/motion-graphics.php)
        // — the module's own test harness (packages/.../tests/TestCase.php)
        // always enables it for its Feature suite; without it publish()
        // throws "Publishing is disabled." before ever reaching the
        // approved-version check this file actually means to exercise.
        config(['motion-graphics.publishing_enabled' => true]);
    }

    public function test_tenant_user_cannot_access_master_library(): void
    {
        $tenant = User::factory()->create(['is_admin' => false]);
        $this->actingAs($tenant)->get('/admin/motion-graphics')->assertForbidden();
    }

    public function test_unapproved_version_cannot_be_published(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'site_id' => null]);
        $motion = $this->createMotion($admin, 'a');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MotionLibraryService::class)->publish($motion, 1, $admin);
    }

    public function test_runtime_manifest_never_exposes_authoring_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'site_id' => null]);
        // Site has no factory in this codebase (App\Models\Site never uses
        // HasFactory) — every other suite creates it manually the same way.
        $customer = \App\Models\Customer::create([
            'name' => 'motion test customer', 'email' => 'motion-test@example.com', 'mobile' => '09123456789',
        ]);
        $site = Site::create([
            'customer_id' => $customer->id, 'title' => 'motion test site', 'domain' => 'motion-test.example.com', 'is_active' => true,
        ]);
        $service = app(MotionLibraryService::class);
        $motion = $this->createMotion($admin, 'b');
        $service->approve($motion, 1, $admin);
        $service->publish($motion, 1, $admin);
        // MotionGraphicDeployment has no site_id/version columns — it is
        // deliberately decoupled from the host's `sites` table (opaque
        // external_site_identifier, per the module's own persistence
        // design) and pins a runtime placement via page/placement
        // identifiers rather than a bare version number.
        $deployment = MotionGraphicDeployment::create([
            'motion_graphic_id' => $motion->id,
            'external_site_identifier' => (string) $site->id,
            'page_identifier' => 'home',
            'placement_identifier' => 'hero',
            'is_enabled' => true,
            'follow_latest_published' => true,
        ]);
        $manifest = $service->runtimeManifest($deployment);

        // Current runtimeManifest() contract (MotionLibraryService::runtimeManifest):
        // slug/version/engine/assets/dimensions/configuration/accessibility/cache —
        // published_at now lives nested under cache, not top-level, and
        // there is no top-level 'name'. The assertion this test actually
        // cares about is that authoring-only fields never leak.
        $this->assertSame(
            ['slug', 'version', 'engine', 'assets', 'dimensions', 'configuration', 'accessibility', 'cache'],
            array_keys($manifest)
        );
        $this->assertArrayNotHasKey('source_definition_json', $manifest);
        $this->assertArrayNotHasKey('editable_schema_json', $manifest);
        $this->assertArrayNotHasKey('ai_metadata_json', $manifest);
    }

    private function createMotion(User $admin, string $hash): \SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic
    {
        $slug = 'iran-network-'.$hash;
        $group = \SaaSPlatform\Modules\MotionGraphics\Models\MotionGroup::query()->create([
            'name' => 'Business', 'slug' => 'business-'.$hash,
            'ownership_type' => \SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic::OWNERSHIP_GLOBAL,
            'owner_site_identifier' => null, 'sort_order' => 0, 'is_active' => true,
        ]);

        return app(MotionLibraryService::class)->create([
            'name' => 'Iran Network', 'slug' => $slug,
            'ownership_type' => \SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic::OWNERSHIP_GLOBAL,
            'group_id' => $group->id,
            'engine' => 'svg-js', 'asset_type' => 'svg',
            'source_definition_json' => ['secret' => 'source'],
            'editable_schema_json' => ['properties' => []], 'ai_metadata_json' => ['prompt' => 'secret'],
            'compiled_assets' => [[
                'url' => '/motion/'.$slug.'/v1/animation.js',
                'sha256' => str_repeat($hash, 64),
                'mime' => 'application/javascript',
                'size' => 1024,
            ]],
        ], $admin);
    }
}
