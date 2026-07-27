<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Feature\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class VersionReadContractTest extends TestCase
{
    public function test_resolve_published_version_returns_the_active_published_row(): void
    {
        $motion = $this->published('version-active');

        $version = app(MotionVersionReadContract::class)->resolvePublishedVersion($motion->uuid);

        $this->assertNotNull($version);
        $this->assertSame(1, $version->version);
        $this->assertSame('published', $version->status);
        $this->assertFalse($version->isDeprecated);
    }

    public function test_resolve_published_version_is_null_before_publication(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('never-published'), $this->svgInput(), $this->admin);

        $this->assertNull(app(MotionVersionReadContract::class)->resolvePublishedVersion($motion->uuid));
    }

    public function test_lineage_excludes_draft_and_review_versions(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('lineage'), $this->svgInput('V1'), $this->admin);
        $library->approve($motion, 1, $this->admin);
        $library->publish($motion, 1, $this->admin);
        $library->createVersionFromPipeline($motion->fresh(), $this->svgInput('V2 draft'), 'v2', $this->admin);

        $lineage = app(MotionVersionReadContract::class)->lineage($motion->uuid);

        $this->assertCount(1, $lineage);
        $this->assertSame(1, $lineage[0]->version);
        $this->assertSame('published', $lineage[0]->status);
    }

    public function test_lineage_includes_retired_versions_only_when_policy_allows(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('lineage-retired'), $this->svgInput('V1'), $this->admin);
        $library->approve($motion, 1, $this->admin);
        $library->publish($motion, 1, $this->admin);
        $v2 = $library->createVersionFromPipeline($motion->fresh(), $this->svgInput('V2'), 'v2', $this->admin);
        $library->approve($motion->fresh(), $v2->version, $this->admin);
        $library->publish($motion->fresh(), $v2->version, $this->admin);

        $reader = app(MotionVersionReadContract::class);
        $this->assertCount(1, $reader->lineage($motion->uuid));

        config(['motion-graphics.deprecated_versions_readable' => true]);
        $this->assertCount(2, $reader->lineage($motion->uuid));
    }

    public function test_rollback_creates_a_new_version_rather_than_rewriting_history(): void
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData('rollback-lineage'), $this->svgInput('V1'), $this->admin);
        $library->approve($motion, 1, $this->admin);
        $library->publish($motion, 1, $this->admin);
        $library->createVersionFromPipeline($motion->fresh(), $this->svgInput('V2'), 'v2', $this->admin);
        $library->rollback($motion->fresh(), 1, 'restore', $this->admin);

        $reader = app(MotionVersionReadContract::class);
        $v1 = $reader->resolveVersion($motion->uuid, 1);

        $this->assertNotNull($v1);
        $this->assertSame('published', $v1->status);
        $this->assertSame(3, $motion->fresh()->latest_working_version);
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
