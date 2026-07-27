<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Feature\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\TemplateAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class TemplateAssetReadContractTest extends TestCase
{
    public function test_it_resolves_known_published_references(): void
    {
        $a = $this->published('template-a');
        $b = $this->published('template-b');

        $result = app(TemplateAssetReadContract::class)->resolveReferences([
            ['asset_id' => $a->uuid, 'version' => 1],
            ['asset_id' => $b->uuid, 'version' => 1],
        ]);

        $this->assertCount(2, $result->resolved);
        $this->assertSame([], $result->unresolved);
    }

    public function test_it_marks_unpublished_references_as_unresolved(): void
    {
        $library = app(MotionLibraryService::class);
        $draft = $library->createFromPipeline($this->motionData('template-draft'), $this->svgInput(), $this->admin);

        $result = app(TemplateAssetReadContract::class)->resolveReferences([
            ['asset_id' => $draft->uuid, 'version' => 1],
        ]);

        $this->assertSame([], $result->resolved);
        $this->assertSame([$draft->uuid], $result->unresolved);
    }

    public function test_it_marks_unknown_references_as_unresolved(): void
    {
        $result = app(TemplateAssetReadContract::class)->resolveReferences([
            ['asset_id' => 'does-not-exist', 'version' => 1],
        ]);

        $this->assertSame([], $result->resolved);
        $this->assertSame(['does-not-exist'], $result->unresolved);
    }

    public function test_it_marks_malformed_references_as_unresolved_rather_than_throwing(): void
    {
        $result = app(TemplateAssetReadContract::class)->resolveReferences([
            ['asset_id' => 'no-version-field'],
            ['version' => 1],
        ]);

        $this->assertSame([], $result->resolved);
        $this->assertCount(2, $result->unresolved);
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
