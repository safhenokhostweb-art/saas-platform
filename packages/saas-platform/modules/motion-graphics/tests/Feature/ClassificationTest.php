<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Feature;

use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionCategory;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionTag;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class ClassificationTest extends TestCase
{
    public function test_group_category_tags_and_search_filtering_work(): void
    {
        $group = $this->group();
        $category = MotionCategory::create(['motion_group_id' => $group->id, 'name' => 'Networks', 'slug' => 'networks']);
        $tag = MotionTag::create(['name' => 'Iran', 'slug' => 'iran']);
        $data = [...$this->motionData('iran-network', $group), 'category_id' => $category->id, 'tag_ids' => [$tag->id]];
        $motion = app(MotionLibraryService::class)->createFromPipeline($data, $this->svgInput(), $this->admin);
        $this->assertTrue($motion->tags->contains($tag));
        $this->assertSame($category->id, $motion->category_id);
        $this->assertSame($motion->id, MotionGraphic::query()->search('Iran')->first()->id);
    }

    public function test_customer_group_cannot_be_used_by_another_site(): void
    {
        $group = $this->group(MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'site-a');
        $this->expectException(ValidationException::class);
        app(MotionLibraryService::class)->createFromPipeline(
            $this->motionData('wrong-group', $group, MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'site-b'),
            $this->svgInput(),
            $this->admin,
        );
    }
}
