<?php

use App\Models\CanvasAssetVersion;
use App\Models\CanvasHeaderPreset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('canvas_header_presets')) {
            return;
        }

        $slug = 'header-glass-nimbus';
        $version = '1.3.0';

        $blocks = [
            [
                'id' => 'hdr_glass_nimbus_01',
                'type' => 'nav_header',
                'props' => [
                    'header_skin' => 'glass_nimbus',
                    'brand_name' => 'برند شما',
                    'logo_text' => 'برند شما',
                    'logo_url' => '/',
                    'logo_badge_symbol' => '◆',
                    'home_url' => '/',
                    'menu_source' => 'custom_links',
                    'selected_menu_id' => 'primary',
                    'selected_page_ids' => [],
                    'nav_links' => [
                        ['label' => 'صفحه اصلی', 'url' => '/', 'icon' => 'home'],
                        ['label' => 'محصولات', 'url' => '/shop', 'icon' => 'shopping-bag'],
                        ['label' => 'تعرفه‌ها', 'url' => '/pricing', 'icon' => 'tag'],
                        ['label' => 'وبلاگ', 'url' => '/blog', 'icon' => 'book-open'],
                        ['label' => 'تماس با ما', 'url' => '/contact', 'icon' => 'phone'],
                    ],
                    'secondary_label' => 'ورود',
                    'secondary_url' => '/login',
                    'cta_text' => 'شروع کنید',
                    'cta_label' => 'شروع کنید',
                    'cta_url' => '/register',
                    'cta_style' => 'gradient',
                    'cta_color_start' => '#e20c35',
                    'cta_color_end' => '#c4008d',
                    'ink_color' => '#211c48',
                    'bg_color' => 'rgba(255, 255, 255, 0.52)',
                    'text_color' => '#211c48',
                    'backdrop_blur' => 16,
                    'glass_bg_opacity' => 52,
                    'glass_floating_top' => 12,
                    'sticky' => true,
                    'sticky_offset' => 12,
                    'header_radius' => 16,
                    'asset_version' => $version,
                ],
            ],
        ];

        CanvasHeaderPreset::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'title' => 'هدر شیشه‌ای مدرن',
                'description' => 'هدر شیشه‌ای شناور مدرن (Glassmorphic Sticky Header) بر پایه واحد nav_header با افکت بلور ۱۶ پیکسل، نشان گرادیانی و دکمه CTA گرادیانی.',
                'category_label' => 'ناوبری مدرن',
                'preview_image' => '/theme-assets/headers/header-glass-nimbus.png',
                'blocks' => $blocks,
                'access_type' => 'paid',
                'price' => 490000,
                'discount_price' => null,
                'current_version' => $version,
                'is_active' => true,
                'sort_order' => 10,
            ]
        );

        if (Schema::hasTable('canvas_asset_versions')) {
            CanvasAssetVersion::query()->updateOrCreate(
                [
                    'asset_kind' => AssetRef::KIND_HEADER,
                    'asset_slug' => $slug,
                    'version' => $version,
                ],
                [
                    'changelog' => 'قالب رسمی «هدر شیشه‌ای مدرن» بر پایه واحد nav_header با پوسته glass_nimbus و معماری ADR-091',
                    'snapshot_summary' => [
                        'name' => 'هدر شیشه‌ای مدرن',
                        'block_type' => 'nav_header',
                        'header_skin' => 'glass_nimbus',
                        'access_type' => 'paid',
                        'price' => 490000,
                        'version' => $version,
                    ],
                    'published_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('canvas_header_presets')) {
            CanvasHeaderPreset::query()->where('slug', 'header-glass-nimbus')->delete();
        }
        if (Schema::hasTable('canvas_asset_versions')) {
            CanvasAssetVersion::query()->where('asset_kind', AssetRef::KIND_HEADER)->where('asset_slug', 'header-glass-nimbus')->where('version', '1.3.0')->delete();
        }
    }
};
