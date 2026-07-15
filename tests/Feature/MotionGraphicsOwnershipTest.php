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
        app(MotionLibraryService::class)->publish($motion, 1);
    }

    public function test_runtime_manifest_never_exposes_authoring_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'site_id' => null]);
        $site = Site::factory()->create();
        $service = app(MotionLibraryService::class);
        $motion = $this->createMotion($admin, 'b');
        $service->approve($motion, 1, $admin->id);
        $service->publish($motion, 1);
        $deployment = MotionGraphicDeployment::create(['motion_graphic_id' => $motion->id, 'site_id' => $site->id, 'version' => 1]);
        $manifest = $service->runtimeManifest($deployment);

        $this->assertSame(['name', 'version', 'assets', 'published_at'], array_keys($manifest));
        $this->assertArrayNotHasKey('source_definition_json', $manifest);
        $this->assertArrayNotHasKey('editable_schema_json', $manifest);
        $this->assertArrayNotHasKey('ai_metadata_json', $manifest);
    }

    private function createMotion(User $admin, string $hash): \SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic
    {
        return app(MotionLibraryService::class)->create([
            'name' => 'Iran Network', 'slug' => 'iran-network',
            'source_definition_json' => ['secret' => 'source'],
            'editable_schema_json' => ['properties' => []], 'ai_metadata_json' => ['prompt' => 'secret'],
            'compiled_assets' => [['url' => '/assets/motion/iran-network/v1/animation.js', 'sha256' => str_repeat($hash, 64), 'mime' => 'application/javascript']],
        ], $admin->id);
    }
}
