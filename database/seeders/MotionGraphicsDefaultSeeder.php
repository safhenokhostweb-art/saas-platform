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
            ['slug' => 'hero-vector-showcase'],
            [
                'name' => 'موشن گرافیک وکتوری هیرو (پیشرفته)',
                'description' => 'موشن گرافیک وکتور لوکس و مدرن با ۴ فاز یکپارچه: شبکه دیجیتالی، ذرات نورانی، مرورگر ایزومتریک سه‌بعدی، مورفینگ صفحات، Shield Badge، باکس‌های خدمات و برند + CTA با لوپ بی‌نقص.',
                'ownership_type' => MotionGraphic::OWNERSHIP_GLOBAL,
                'owner_site_identifier' => null,
                'group_id' => $group->id,
                'category_id' => $category->id,
                'engine' => 'svg-css',
                'asset_type' => 'svg',
                'source_definition_json' => [
                    'type' => 'hero-vector-showcase',
                    'version' => 1,
                    'loop' => true,
                    'duration' => 10,
                    'phases' => 4,
                    'seamless' => true,
                ],
                'editable_schema_json' => [
                    'groups' => [
                        ['key' => 'typography', 'label' => 'تایپوگرافی و اندازه‌ها'],
                        ['key' => 'content', 'label' => 'محتوا و آیتم‌ها'],
                        ['key' => 'visual', 'label' => 'ظاهر و رنگ‌بندی'],
                        ['key' => 'animation', 'label' => 'کنترل انیمیشن'],
                        ['key' => 'accessibility', 'label' => 'دسترسی‌پذیری و واکنش‌گرایی'],
                    ],
                    'fields' => [
                        'title' => ['type' => 'text', 'label' => 'تیتر اصلی', 'group' => 'content', 'default' => 'طراحی سایت حرفه‌ای و اختصاصی در صفحه نخست'],
                        'subtitle' => ['type' => 'text', 'label' => 'زیرعنوان', 'group' => 'content', 'default' => 'طراحی سایت حرفه‌ای و اختصاصی در صفحه نخست'],
                        'menu_items' => ['type' => 'array', 'label' => 'عناوین منو', 'group' => 'content', 'default' => ['خانه', 'خدمات', 'نمونه‌کار', 'تماس']],
                        'floating_texts' => ['type' => 'array', 'label' => 'متون شناور', 'group' => 'content', 'default' => ['فروشگاهی', 'شرکتی', 'تجاری', 'شخصی']],
                        'cta_text' => ['type' => 'text', 'label' => 'متن دکمه اقدام', 'group' => 'content', 'default' => 'مشاهده پلن‌ها و شروع'],
                        'cta_url' => ['type' => 'link', 'label' => 'لینک دکمه اقدام', 'group' => 'content', 'default' => '#'],
                        'title_size' => ['type' => 'number', 'label' => 'سایز فونت تیتر (px)', 'group' => 'typography', 'min' => 24, 'max' => 72, 'default' => 46],
                        'subtitle_size' => ['type' => 'number', 'label' => 'سایز فونت زیرعنوان (px)', 'group' => 'typography', 'min' => 14, 'max' => 32, 'default' => 20],
                        'font_weight' => ['type' => 'select', 'label' => 'وزن فونت', 'group' => 'typography', 'options' => ['500' => 'متوسط', '600' => 'نیمه‌بولد', '700' => 'بولد', '800' => 'فوق‌بولد'], 'default' => '800'],
                        'line_height' => ['type' => 'number', 'label' => 'فاصله خطوط', 'group' => 'typography', 'min' => 1.0, 'max' => 2.0, 'step' => 0.1, 'default' => 1.6],
                        'bg_gradient_start' => ['type' => 'color', 'label' => 'گرادیان آغاز', 'group' => 'visual', 'default' => '#0f172a'],
                        'bg_gradient_end' => ['type' => 'color', 'label' => 'گرادیان پایان', 'group' => 'visual', 'default' => '#1e1b4b'],
                        'neon_cyan' => ['type' => 'color', 'label' => 'رنگ نئونی سایان', 'group' => 'visual', 'default' => '#38bdf8'],
                        'neon_purple' => ['type' => 'color', 'label' => 'رنگ نئونی بنفش', 'group' => 'visual', 'default' => '#818cf8'],
                        'cta_color' => ['type' => 'color', 'label' => 'رنگ دکمه اقدام', 'group' => 'visual', 'default' => '#2563eb'],
                        'particle_opacity' => ['type' => 'number', 'label' => 'شفافیت ذرات', 'group' => 'visual', 'min' => 0, 'max' => 1, 'step' => 0.1, 'default' => 0.7],
                        'glassmorphism' => ['type' => 'toggle', 'label' => 'گلاس‌مورفیسم', 'group' => 'visual', 'default' => true],
                        'loop_duration' => ['type' => 'number', 'label' => 'سرعت لوپ (ثانیه)', 'group' => 'animation', 'min' => 6, 'max' => 20, 'default' => 10],
                        'floating_particles' => ['type' => 'toggle', 'label' => 'ذرات نورانی معلق', 'group' => 'animation', 'default' => true],
                        'isometric_depth' => ['type' => 'toggle', 'label' => 'عمق سه‌بعدی ایزومتریک', 'group' => 'animation', 'default' => true],
                        'glow_paths' => ['type' => 'toggle', 'label' => 'خطوط نوری متحرک', 'group' => 'animation', 'default' => true],
                        'reduced_motion' => ['type' => 'toggle', 'label' => 'احترام به prefers-reduced-motion', 'group' => 'accessibility', 'default' => true],
                        'rtl' => ['type' => 'toggle', 'label' => 'چیدمان راست‌به‌چپ', 'group' => 'accessibility', 'default' => true],
                        'responsive' => ['type' => 'toggle', 'label' => 'واکنش‌گرایی SVG ViewBox', 'group' => 'accessibility', 'default' => true],
                    ],
                ],
                'runtime_configuration' => ['loop' => true, 'duration' => 10, 'phases' => 4, 'seamless' => true],
                'dimensions' => ['width' => 1600, 'height' => 900, 'viewBox' => '0 0 1600 900'],
                'accessibility' => ['reduced_motion' => true, 'rtl' => true, 'responsive' => true],
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
                'change_description' => 'Initial published version — hero-vector-showcase high-end vector motion',
                'created_by_actor_identifier' => 'system',
                'published_by_actor_identifier' => 'system',
                'published_at' => now(),
            ]
        );
    }
}
