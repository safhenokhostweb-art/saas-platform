<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Feature;

use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicDeployment;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionDeploymentService;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class DeploymentRuntimeTest extends TestCase
{
    public function test_global_motion_supports_multiple_sites_pages_placements_and_ordering(): void
    {
        $first = $this->published('first');
        $second = $this->published('second');
        $service = app(MotionDeploymentService::class);
        $service->assign($first, 'site-a', 'home', 'hero', $this->admin, 'top', 20);
        $service->assign($second, 'site-a', 'home', 'hero', $this->admin, 'top', 10);
        $service->assign($first, 'site-b', 'about', 'content', $this->admin);
        $this->assertSame(['second', 'first'], $service->manifestsFor('site-a', 'home')->pluck('slug')->all());
        $this->assertCount(3, MotionGraphicDeployment::all());
    }

    public function test_customer_specific_motion_is_isolated_to_owner_site(): void
    {
        $motion = $this->published('customer-only', MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'site-a');
        $this->expectException(ValidationException::class);
        app(MotionDeploymentService::class)->assign($motion, 'site-b', 'home', 'hero', $this->admin);
    }

    public function test_disabled_deployment_is_excluded(): void
    {
        $motion = $this->published('disabled');
        app(MotionDeploymentService::class)->assign($motion, 'site-a', 'home', 'hero', $this->admin, null, 0, false);
        $this->assertCount(0, app(MotionDeploymentService::class)->manifestsFor('site-a', 'home'));
    }

    public function test_runtime_manifest_is_strict_allowlist_and_uses_only_published_version(): void
    {
        $motion = $this->published('manifest');
        $deployment = app(MotionDeploymentService::class)->assign($motion, 'site-a', 'home', 'hero', $this->admin);
        app(MotionLibraryService::class)->createVersionFromPipeline($motion, $this->svgInput('Draft'), 'draft', $this->admin);
        $manifest = app(MotionLibraryService::class)->runtimeManifest($deployment);
        $this->assertSame(['slug', 'version', 'engine', 'assets', 'dimensions', 'configuration', 'accessibility', 'cache'], array_keys($manifest));
        $this->assertSame(1, $manifest['version']);
        $this->assertEqualsCanonicalizing(['url', 'sha256', 'mime', 'size'], array_keys($manifest['assets'][0]));
        foreach (['source_definition_json', 'editable_schema_json', 'ai_metadata_json', 'original_prompt', 'path'] as $private) {
            $this->assertArrayNotHasKey($private, $manifest);
            $this->assertArrayNotHasKey($private, $manifest['assets'][0]);
        }
    }

    private function published(string $slug, string $ownership = MotionGraphic::OWNERSHIP_GLOBAL, ?string $site = null): MotionGraphic
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData($slug, null, $ownership, $site), $this->svgInput(), $this->admin);
        $library->approve($motion, 1, $this->admin);
        $library->publish($motion, 1, $this->admin);
        return $motion->fresh();
    }
}
