<?php

namespace Tests\Feature\Modules\MotionGraphics;

use App\Models\User;
use App\Modules\MotionGraphics\Contracts\MotionRuntimeProjectionInterface;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use Illuminate\Support\Facades\Config;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use Tests\TestCase;

/**
 * Runtime projection allowlist filtering (task scope item 8: "runtime
 * projection filtering"). The DTO itself rejects forbidden fields at
 * construction; validateProjection() re-checks independently, matching
 * docs/motion-saas-integration/AUTHORIZATION_MATRIX.md's
 * "rechecked at ... delivery boundaries" rule.
 */
class RuntimeProjectionFilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The test actor is never persisted; swap in a no-op audit logger
        // so a fail-closed path that still records an audit event never
        // touches the real activity_logs table.
        $this->app->instance(ActivityLoggerInterface::class, new class implements ActivityLoggerInterface {
            public function log(string $action, ?string $description = null, ?object $subject = null, array $properties = []): void {}
        });
    }

    public function test_a_clean_projection_passes_validation(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);

        $projection = new RuntimeProjectionDTO(
            assetReference: new MotionAssetReferenceDTO('motion-1', 3, 'lottie'),
            publishedVersion: 3,
            themeTokens: ['primary' => '#111111'],
            placementRules: ['section' => 'hero'],
        );

        $report = $this->app->make(MotionRuntimeProjectionInterface::class)->validateProjection($projection);

        $this->assertTrue($report->valid);
    }

    public function test_a_version_mismatch_between_asset_reference_and_published_version_is_rejected(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);

        $projection = new RuntimeProjectionDTO(
            assetReference: new MotionAssetReferenceDTO('motion-1', 3, 'lottie'),
            publishedVersion: 4,
        );

        $report = $this->app->make(MotionRuntimeProjectionInterface::class)->validateProjection($projection);

        $this->assertFalse($report->valid);
        $this->assertSame('version_mismatch', $report->errors[0]['code']);
    }

    public function test_validation_fails_closed_while_the_integration_flag_is_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);

        $projection = new RuntimeProjectionDTO(
            assetReference: new MotionAssetReferenceDTO('motion-1', 1, 'lottie'),
            publishedVersion: 1,
        );

        $report = $this->app->make(MotionRuntimeProjectionInterface::class)->validateProjection($projection);

        $this->assertFalse($report->valid);
        $this->assertSame('motion_adapter_disabled', $report->errors[0]['code']);
    }

    public function test_project_published_never_fabricates_output_in_this_phase(): void
    {
        Config::set('feature-flags.motion_graphics_integration', true);

        $admin = new User();
        $admin->id = 1;
        $admin->is_admin = true;
        $admin->site_id = null;
        $this->actingAs($admin);

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionRuntimeProjectionInterface::class)
            ->projectPublished('package-1', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
    }
}
