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
        $version = '1.0.0';

        $blocks = [
            [
                'id' => 'hdr_glass_nimbus_01',
                'type' => 'header_glass_nimbus',
                'props' => [
                    'brand_name' => 'برند شما',
                    'logo_url' => '',
                    'logo_badge_symbol' => '◆',
                    'home_url' => '/',
                    'menu_items' => [
                        ['label' => 'صفحه اصلی', 'url' => '/'],
                        ['label' => 'محصولات', 'url' => '/products'],
                        ['label' => 'تعرفه‌ها', 'url' => '/pricing'],
                        ['label' => 'وبلاگ', 'url' => '/blog'],
                    ],
                    'secondary_label' => 'ورود',
                    'secondary_url' => '/login',
                    'cta_label' => 'شروع رایگان',
                    'cta_url' => '/register',
                    'cta_color_start' => '#e20c35',
                    'cta_color_end' => '#c4008d',
                    'ink_color' => '#211c48',
                    'asset_version' => $version,
                ],
            ],
        ];

        CanvasHeaderPreset::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'title' => 'هدر شناور شیشه‌ای مدرن',
                'description' => 'هدر شیشه‌ای شناور (Glassmorphic Sticky Header) مدرن با منوی واکنش‌گرا، نشان گرادیانی و دکمه فراخوان.',
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
                    'changelog' => 'انتشار اولیه هدر شناور شیشه‌ای مدرن با منوی واکنش‌گرا و دکمه گرادیانی',
                    'snapshot_summary' => [
                        'block_type' => 'header_glass_nimbus',
                        'access_type' => 'paid',
                        'price' => 490000,
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
            CanvasAssetVersion::query()->where('asset_kind', AssetRef::KIND_HEADER)->where('asset_slug', 'header-glass-nimbus')->delete();
        }
    }
};
