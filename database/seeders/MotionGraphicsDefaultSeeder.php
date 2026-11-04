<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionCategory;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGroup;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionTag;

final class MotionGraphicsDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $group = MotionGroup::firstOrCreate(
            ['slug' => 'hero'],
            [
                'name' => 'هیرو',
                'ownership_type' => MotionGroup::OWNERSHIP_GLOBAL,
                'owner_site_identifier' => null,
                'search_metadata' => [],
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $category = MotionCategory::firstOrCreate(
            ['motion_group_id' => $group->id, 'slug' => 'hero-motion'],
            [
                'name' => 'موشن هیرو',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $tag = MotionTag::firstOrCreate(
            ['slug' => 'vector'],
            ['name' => 'وکتور']
        );

        $motion = MotionGraphic::updateOrCreate(
            ['slug' => 'hero_motion'],
            [
                'name' => 'موشن وکتوری هیرو ۴ فاز',
                'description' => 'موشن گرافیک وکتورین ۱۰ ثانیه‌ای با ۴ فاز: شبکه دیجیتالی، مرورگر ایزومتریک، مورفینگ تم‌ها، Shield Badge و نشان برند.',
                'ownership_type' => MotionGraphic::OWNERSHIP_GLOBAL,
                'owner_site_identifier' => null,
                'group_id' => $group->id,
                'category_id' => $category->id,
                'engine' => 'svg-css',
                'asset_type' => 'svg',
                'source_definition_json' => [],
                'editable_schema_json' => [],
                'runtime_configuration' => ['loop' => true, 'duration' => 10, 'phases' => 4],
                'dimensions' => ['width' => 1600, 'height' => 900, 'viewBox' => '0 0 1600 900'],
                'accessibility' => ['reduced_motion' => true, 'rtl' => true],
                'latest_working_version' => 1,
                'approved_version' => 1,
                'published_version' => 1,
                'status' => 'published',
                'created_by_actor_identifier' => 'system',
                'published_at' => now(),
            ]
        );

        $motion->tags()->sync([$tag->id]);

        MotionGraphicVersion::updateOrCreate(
            ['motion_graphic_id' => $motion->id, 'version' => 1],
            [
                'parent_version' => null,
                'source_snapshot' => [],
                'editable_schema_snapshot' => [],
                'ai_metadata_json' => null,
                'compiled_assets' => [],
                'preview_metadata' => null,
                'source_checksum' => hash('sha256', json_encode([], JSON_THROW_ON_ERROR)),
                'status' => 'published',
                'change_description' => 'Initial published version — hero_motion default vector motion',
                'created_by_actor_identifier' => 'system',
                'published_by_actor_identifier' => 'system',
                'published_at' => now(),
            ]
        );
    }
}
