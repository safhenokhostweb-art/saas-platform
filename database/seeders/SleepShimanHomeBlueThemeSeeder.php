<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;
use SaaSPlatform\ThemeRuntime\SleepShimanBlockTypes;

/** Authoring seed only; it never publishes, assigns, installs, or migrates. */
final class SleepShimanHomeBlueThemeSeeder extends Seeder
{
    public const SLUG = 'sleepshiman-home-blue';
    public const VERSION = '1.0.0';

    public function run(): void
    {
        $theme = Theme::firstOrNew(['slug' => self::SLUG, 'site_id' => null]);
        $new = ! $theme->exists;
        $theme->fill([
            'name' => 'SleepShiman Home blue',
            'description' => 'قالب تجاری فارسی و RTL کالای خواب؛ Manual-first با اتصال مجاز محصولات، دسته‌ها، وبلاگ و دیدگاه‌ها.',
            'type' => Theme::TYPE_FULL,
            'category' => Theme::CATEGORY_STORE,
            'source' => Theme::SOURCE_SEEDER,
            'pages' => self::pages(),
            'header_blocks' => [
                self::block('sleepshiman-header', 'sleepshiman_header'),
                self::block('sleepshiman-navigation', 'sleepshiman_navigation'),
            ],
            'footer_blocks' => [self::block('sleepshiman-footer', 'sleepshiman_footer')],
            'global_styles' => [
                'direction' => 'rtl',
                'locale' => 'fa-IR',
                'chrome_owner' => 'theme',
                'presentation_css_owner' => 'theme',
                'font_family' => 'Vazirmatn',
                'primary_color' => '#0758e9',
            ],
            'settings' => array_merge(is_array($theme->settings ?? null) ? $theme->settings : [], [
                'theme_assets_manifest' => self::themeAssetsManifest(),
                'preset' => 'home-blue',
                'supported_locales' => ['fa-IR', 'ar', 'en'],
                'default_locale' => 'fa-IR',
                'locale_contract' => [
                    'directions' => ['fa-IR' => 'rtl', 'ar' => 'rtl', 'en' => 'ltr'],
                    'font_families' => ['fa-IR' => 'Vazirmatn', 'ar' => 'Vazirmatn', 'en' => 'Vazirmatn'],
                    'translation_keys' => ['weight', 'material', 'size', 'sales', 'satisfaction', 'view_product', 'view', 'add_to_cart', 'available', 'empty_products', 'helpful', 'not_helpful', 'carousel', 'previous', 'next'],
                ],
                'design_source' => ['figma_file_key' => '8XemokBtUnZug7xOKbgh1i', 'frame_id' => 'unverified:2014:755'],
            ]),
        ]);
        if ($new) $theme->status = Theme::STATUS_DRAFT;
        $theme->save();
    }

    /** @return array<string,array<string,mixed>> */
    public static function pages(): array
    {
        $types = [
            'sleepshiman_quick_categories', 'sleepshiman_featured_product', 'sleepshiman_services',
            'sleepshiman_amazing_offers', 'sleepshiman_mattresses', 'sleepshiman_promo_banners',
            'sleepshiman_bedding_products', 'sleepshiman_blanket_products', 'sleepshiman_sleep_banner',
            'sleepshiman_brands', 'sleepshiman_published_products', 'sleepshiman_topic_cards',
            'sleepshiman_articles', 'sleepshiman_editorial', 'sleepshiman_reviews',
            'sleepshiman_guide_intro', 'sleepshiman_guide_cards', 'sleepshiman_product_filters', 'sleepshiman_stats',
        ];
        $blocks = [];
        foreach ($types as $type) $blocks[] = self::block(str_replace('_', '-', $type), $type);
        return ['home' => ['title' => 'خانه', 'slug' => '', 'seo' => [
            'title' => 'SleepShiman | کالای خواب شیمانا',
            'description' => 'خرید و راهنمای انتخاب تشک، بالش، پتو و کالای خواب شیمانا.',
        ], 'blocks' => $blocks]];
    }

    /** @return array<string,mixed> */
    private static function block(string $id, string $type): array
    {
        $definition = SleepShimanBlockTypes::definitions()[$type];
        return ['id' => $id, 'type' => $type, 'props' => $definition['defaultProps'] ?? []];
    }

    /** @return array{version:int,assets:list<array{kind:string,source:string,path:string,sha256:string,usage?:string,alt?:string}>} */
    public static function themeAssetsManifest(): array
    {
        $base = rtrim((string) config('app.url'), '/').'/theme-assets/'.self::SLUG.'/'.self::VERSION.'/';
        $assets = [];
        foreach ([['css','theme.css'],['js','theme.js']] as [$kind,$path]) {
            $assets[] = self::asset($base, $kind, $path);
        }
        $usage = [
            'fonts/vazirmatn-arabic.woff2' => ['فونت محلی رابط فارسی', ''],
            'images/logo-mark.png' => ['نشان هدر', 'نشان SleepShiman'],
            'images/logo-enamad.png' => ['نشان اعتماد فوتر', 'نشان اعتماد الکترونیکی'],
            'images/pillow-green.webp' => ['محصول ویژه و راهنمای بالش', 'بالش سبز'],
            'images/mattress-room.webp' => ['کارت‌های تشک', 'تشک در اتاق خواب'],
            'images/mattress-stack.webp' => ['بنر و راهنمای تشک', 'تشک چندلایه'],
            'images/blanket-stack.webp' => ['بنر و راهنمای پتو', 'پتوهای تاخورده'],
            'images/bedroom.webp' => ['محصولات روی تخت', 'اتاق خواب'],
            'images/comforter.webp' => ['کارت ویژه کالای خواب', 'روتختی'],
            'images/sleep-illustration.webp' => ['بنر پیشنهاد و خواب', 'تصویرسازی خواب آرام'],
            'images/pillow-sleep.webp' => ['کارت موضوعی خواب', 'خواب با بالش مناسب'],
            'images/blue-sleep.webp' => ['کارت موضوعی خواب', 'خواب آرام'],
            'images/man-sleep.webp' => ['کارت موضوعی گردن', 'حالت خواب'],
            'images/man-review.webp' => ['کارت موضوعی کمر', 'راهنمای خواب'],
            'images/article-night.webp' => ['مقاله ویژه و تحریریه', 'خواب شبانه'],
            'images/article-sleeper.webp' => ['کارت مقاله', 'مطالعه درباره خواب'],
            'images/team-man.webp' => ['تصویر پیش‌فرض دیدگاه', 'مشتری شیمانا'],
            'images/bed-cutout.webp' => ['راهنمای تخت', 'تخت خواب'],
            'images/duvet.webp' => ['راهنمای روتختی', 'روتختی'],
        ];
        foreach (range(1, 4) as $i) $usage['images/brand-'.$i.'.webp'] = ['نوار برندها', 'برند همکار '.$i];
        foreach ($usage as $path => [$purpose, $alt]) $assets[] = self::asset($base, 'file', $path) + ['usage' => $purpose, 'alt' => $alt];
        return ['version' => 1, 'assets' => $assets];
    }

    /** @return array{kind:string,source:string,path:string,sha256:string,width?:int,height?:int} */
    private static function asset(string $base, string $kind, string $path): array
    {
        $file = public_path('theme-assets/'.self::SLUG.'/'.self::VERSION.'/'.$path);
        if (!is_file($file) || ($hash = hash_file('sha256', $file)) === false) {
            throw new \RuntimeException('SleepShiman packaged asset is missing or unreadable: '.$path);
        }
        $asset = ['kind' => $kind, 'source' => $base.$path, 'path' => $path, 'sha256' => $hash];
        $dimensions = @getimagesize($file);
        if (is_array($dimensions)) {
            $asset['width'] = (int) $dimensions[0];
            $asset['height'] = (int) $dimensions[1];
        }
        return $asset;
    }
}
