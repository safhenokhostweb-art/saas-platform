<?php
namespace App\Services\ThemeBuilder;

/**
 * Central registry of all Theme Builder block types.
 * Used by editor (schema + defaults) and by renderer (type → render).
 * RTL and Persian optimized.
 */
final class BlockRegistry
{
    private static ?array $blocks = null;

    public const CATEGORY_LAYOUT = 'layout';
    public const CATEGORY_CONTENT = 'content';
    public const CATEGORY_DYNAMIC = 'dynamic';
    public const CATEGORY_ECOMMERCE = 'ecommerce';
    public const CATEGORY_MARKETING = 'marketing';
    public const CATEGORY_UTILITY = 'utility';

    /**
     * All block definitions: type => [ name, category, icon, schema, defaultProps ]
     */
    public static function all(): array
    {
        if (self::$blocks !== null) {
            return self::$blocks;
        }

        self::$blocks = [
            // ─── LAYOUT ─────────────────────────────────────────────────────
            'section' => [
                'name' => 'بخش (Section)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'layout-section',
                'schema' => [
                    'padding' => ['type' => 'spacing', 'default' => 'md'],
                    'background' => ['type' => 'string', 'default' => 'transparent'],
                    'background_image' => ['type' => 'string', 'default' => ''],
                    'container' => ['type' => 'boolean', 'default' => true],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['padding' => 'md', 'background' => 'transparent', 'container' => true, 'blocks' => []],
            ],
            'container' => [
                'name' => 'کانتینر',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'container',
                'schema' => [
                    'maxWidth' => ['type' => 'string', 'default' => '7xl'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['maxWidth' => '7xl', 'blocks' => []],
            ],
            'grid' => [
                'name' => 'گرید',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'grid',
                'schema' => [
                    'columns' => ['type' => 'number', 'default' => 3],
                    'gap' => ['type' => 'spacing', 'default' => 'md'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['columns' => 3, 'gap' => 'md', 'blocks' => []],
            ],
            'columns' => [
                'name' => 'ستون‌ها',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'columns',
                'schema' => [
                    'ratios' => ['type' => 'string', 'default' => '1/2,1/2'],
                    'gap' => ['type' => 'spacing', 'default' => 'md'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['ratios' => '1/2,1/2', 'gap' => 'md', 'blocks' => []],
            ],
            'tabs' => [
                'name' => 'تب‌ها',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'tabs',
                'schema' => [
                    'tabs' => ['type' => 'array', 'default' => [['title' => 'تب ۱', 'blocks' => []], ['title' => 'تب ۲', 'blocks' => []]]],
                ],
                'defaultProps' => ['tabs' => [['title' => 'تب ۱', 'blocks' => []], ['title' => 'تب ۲', 'blocks' => []]]],
            ],
            'accordion' => [
                'name' => 'آکوردئون',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'accordion',
                'schema' => [
                    'items' => ['type' => 'array', 'default' => [['title' => 'عنوان', 'blocks' => []]]],
                ],
                'defaultProps' => ['items' => [['title' => 'عنوان', 'blocks' => []]]],
            ],
            'slider' => [
                'name' => 'اسلایدر',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'slider',
                'schema' => [
                    'slides' => ['type' => 'blocks', 'default' => []],
                    'autoplay' => ['type' => 'boolean', 'default' => true],
                    'interval' => ['type' => 'number', 'default' => 5000],
                ],
                'defaultProps' => ['slides' => [], 'autoplay' => true, 'interval' => 5000],
            ],
            'modal' => [
                'name' => 'مودال',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'modal',
                'schema' => [
                    'trigger_text' => ['type' => 'string', 'default' => 'باز کردن'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['trigger_text' => 'باز کردن', 'blocks' => []],
            ],
            'hero' => [
                'name' => 'بخش قهرمان (Hero)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'hero',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => ''],
                    'subtitle' => ['type' => 'string', 'default' => ''],
                    'button_text' => ['type' => 'string', 'default' => ''],
                    'button_url' => ['type' => 'string', 'default' => ''],
                    'background' => ['type' => 'string', 'default' => 'primary'],
                    'background_image' => ['type' => 'string', 'default' => ''],
                    'align' => ['type' => 'string', 'default' => 'center'],
                ],
                'defaultProps' => [
                    'title' => '', 'subtitle' => '', 'button_text' => '', 'button_url' => '',
                    'background' => 'primary', 'background_image' => '', 'align' => 'center',
                ],
            ],

            // ─── CONTENT ─────────────────────────────────────────────────────
            'heading' => [
                'name' => 'عنوان',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'heading',
                'schema' => [
                    'level' => ['type' => 'number', 'default' => 2],
                    'text' => ['type' => 'string', 'default' => ''],
                ],
                'defaultProps' => ['level' => 2, 'text' => ''],
            ],
            'text' => [
                'name' => 'متن',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'text',
                'schema' => [
                    'content' => ['type' => 'string', 'default' => ''],
                ],
                'defaultProps' => ['content' => ''],
            ],
            'image' => [
                'name' => 'تصویر',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'image',
                'schema' => [
                    'url' => ['type' => 'string', 'default' => ''],
                    'alt' => ['type' => 'string', 'default' => ''],
                    'caption' => ['type' => 'string', 'default' => ''],
                    'align' => ['type' => 'string', 'default' => 'center'],
                ],
                'defaultProps' => ['url' => '', 'alt' => '', 'caption' => '', 'align' => 'center'],
            ],
            'gallery' => [
                'name' => 'گالری',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'gallery',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => ''],
                    'items' => ['type' => 'array', 'default' => []],
                ],
                'defaultProps' => ['title' => '', 'items' => []],
            ],
            'video' => [
                'name' => 'ویدیو',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'video',
                'schema' => [
                    'url' => ['type' => 'string', 'default' => ''],
                    'caption' => ['type' => 'string', 'default' => ''],
                ],
                'defaultProps' => ['url' => '', 'caption' => ''],
            ],
            'button' => [
                'name' => 'دکمه',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'button',
                'schema' => [
                    'text' => ['type' => 'string', 'default' => ''],
                    'url' => ['type' => 'string', 'default' => ''],
                    'style' => ['type' => 'string', 'default' => 'primary'],
                ],
                'defaultProps' => ['text' => '', 'url' => '#', 'style' => 'primary'],
            ],
            'divider' => [
                'name' => 'جداکننده',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'divider',
                'schema' => [],
                'defaultProps' => [],
            ],
            'icon' => [
                'name' => 'آیکون',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'icon',
                'schema' => [
                    'name' => ['type' => 'string', 'default' => ''],
                    'size' => ['type' => 'string', 'default' => 'md'],
                ],
                'defaultProps' => ['name' => '', 'size' => 'md'],
            ],
            'counter' => [
                'name' => 'شمارنده',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'counter',
                'schema' => [
                    'value' => ['type' => 'number', 'default' => 0],
                    'suffix' => ['type' => 'string', 'default' => ''],
                    'label' => ['type' => 'string', 'default' => ''],
                ],
                'defaultProps' => ['value' => 0, 'suffix' => '', 'label' => ''],
            ],
            'progress_bar' => [
                'name' => 'نوار پیشرفت',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'progress',
                'schema' => [
                    'value' => ['type' => 'number', 'default' => 70],
                    'label' => ['type' => 'string', 'default' => ''],
                ],
                'defaultProps' => ['value' => 70, 'label' => ''],
            ],

            // ─── DYNAMIC ─────────────────────────────────────────────────────
            'blog_posts' => [
                'name' => 'پست‌های وبلاگ',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'blog',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => 'آخرین مطالب'],
                    'limit' => ['type' => 'number', 'default' => 6],
                ],
                'defaultProps' => ['title' => 'آخرین مطالب', 'limit' => 6],
            ],
            'products' => [
                'name' => 'محصولات',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'products',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => 'محصولات'],
                    'limit' => ['type' => 'number', 'default' => 8],
                    'source' => ['type' => 'string', 'default' => 'recent'],
                ],
                'defaultProps' => ['title' => 'محصولات', 'limit' => 8, 'source' => 'recent'],
            ],
            'testimonials' => [
                'name' => 'نظرات مشتریان',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'quote',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => 'نظرات'],
                    'limit' => ['type' => 'number', 'default' => 5],
                ],
                'defaultProps' => ['title' => 'نظرات', 'limit' => 5],
            ],
            'faq' => [
                'name' => 'سوالات متداول',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'faq',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => 'سوالات متداول'],
                    'items' => ['type' => 'array', 'default' => []],
                ],
                'defaultProps' => ['title' => 'سوالات متداول', 'items' => []],
            ],
            'team' => [
                'name' => 'اعضای تیم',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'users',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => 'تیم ما'],
                    'limit' => ['type' => 'number', 'default' => 6],
                ],
                'defaultProps' => ['title' => 'تیم ما', 'limit' => 6],
            ],

            // ─── ECOMMERCE ───────────────────────────────────────────────────
            'product_grid' => [
                'name' => 'گرید محصولات',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'grid',
                'schema' => [
                    'columns' => ['type' => 'number', 'default' => 4],
                    'limit' => ['type' => 'number', 'default' => 8],
                ],
                'defaultProps' => ['columns' => 4, 'limit' => 8],
            ],
            'product_slider' => [
                'name' => 'اسلایدر محصولات',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'slider',
                'schema' => [
                    'limit' => ['type' => 'number', 'default' => 6],
                ],
                'defaultProps' => ['limit' => 6],
            ],
            'cart_icon' => [
                'name' => 'آیکون سبد',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'cart',
                'schema' => [],
                'defaultProps' => [],
            ],
            'add_to_cart' => [
                'name' => 'دکمه افزودن به سبد',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'cart',
                'schema' => [
                    'product_id' => ['type' => 'string', 'default' => ''],
                    'text' => ['type' => 'string', 'default' => 'افزودن به سبد'],
                ],
                'defaultProps' => ['product_id' => '', 'text' => 'افزودن به سبد'],
            ],
            'price_box' => [
                'name' => 'جعبه قیمت',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'price',
                'schema' => [
                    'product_id' => ['type' => 'string', 'default' => ''],
                ],
                'defaultProps' => ['product_id' => ''],
            ],

            // ─── MARKETING ───────────────────────────────────────────────────
            'cta' => [
                'name' => 'دعوت به اقدام (CTA)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'cta',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => ''],
                    'subtitle' => ['type' => 'string', 'default' => ''],
                    'button_text' => ['type' => 'string', 'default' => ''],
                    'button_url' => ['type' => 'string', 'default' => ''],
                    'background' => ['type' => 'string', 'default' => 'primary'],
                ],
                'defaultProps' => ['title' => '', 'subtitle' => '', 'button_text' => '', 'button_url' => '', 'background' => 'primary'],
            ],
            'countdown' => [
                'name' => 'شمارش معکوس',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'clock',
                'schema' => [
                    'end_date' => ['type' => 'string', 'default' => ''],
                    'label' => ['type' => 'string', 'default' => 'پایان پیشنهاد'],
                ],
                'defaultProps' => ['end_date' => '', 'label' => 'پایان پیشنهاد'],
            ],
            'email_capture' => [
                'name' => 'فرم ثبت ایمیل',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'mail',
                'schema' => [
                    'title' => ['type' => 'string', 'default' => 'در خبرنامه عضو شوید'],
                    'button_text' => ['type' => 'string', 'default' => 'ثبت'],
                ],
                'defaultProps' => ['title' => 'در خبرنامه عضو شوید', 'button_text' => 'ثبت'],
            ],
            'promo_banner' => [
                'name' => 'بنر تبلیغاتی',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'megaphone',
                'schema' => [
                    'text' => ['type' => 'string', 'default' => ''],
                    'link' => ['type' => 'string', 'default' => ''],
                    'style' => ['type' => 'string', 'default' => 'info'],
                ],
                'defaultProps' => ['text' => '', 'link' => '', 'style' => 'info'],
            ],

            // ─── UTILITY ────────────────────────────────────────────────────
            'breadcrumb' => [
                'name' => 'مسیر (Breadcrumb)',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'breadcrumb',
                'schema' => [],
                'defaultProps' => [],
            ],
            'search_bar' => [
                'name' => 'جستجو',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'search',
                'schema' => [
                    'placeholder' => ['type' => 'string', 'default' => 'جستجو...'],
                ],
                'defaultProps' => ['placeholder' => 'جستجو...'],
            ],
            'nav_menu' => [
                'name' => 'منوی ناوبری',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'menu',
                'schema' => [
                    'location' => ['type' => 'string', 'default' => 'header'],
                ],
                'defaultProps' => ['location' => 'header'],
            ],
            'social_icons' => [
                'name' => 'شبکه‌های اجتماعی',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'share',
                'schema' => [
                    'links' => ['type' => 'array', 'default' => []],
                ],
                'defaultProps' => ['links' => []],
            ],
            'language_switcher' => [
                'name' => 'تعویض زبان',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'globe',
                'schema' => [],
                'defaultProps' => [],
            ],
            'login_register' => [
                'name' => 'ورود / ثبت‌نام',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'user',
                'schema' => [],
                'defaultProps' => [],
            ],
        ];

        return self::$blocks;
    }

    public static function get(string $type): ?array
    {
        $all = self::all();
        return $all[$type] ?? null;
    }

    public static function defaultProps(string $type): array
    {
        $def = self::get($type);
        return $def['defaultProps'] ?? [];
    }

    /** For editor: list by category. */
    public static function byCategory(): array
    {
        $byCat = [];
        foreach (self::all() as $type => $def) {
            $cat = $def['category'] ?? 'content';
            if (!isset($byCat[$cat])) {
                $byCat[$cat] = [];
            }
            $byCat[$cat][$type] = $def;
        }
        return $byCat;
    }

    /** Category labels (Persian). */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_LAYOUT => 'چیدمان',
            self::CATEGORY_CONTENT => 'محتوای ثابت',
            self::CATEGORY_DYNAMIC => 'محتوای پویا',
            self::CATEGORY_ECOMMERCE => 'فروشگاه',
            self::CATEGORY_MARKETING => 'بازاریابی',
            self::CATEGORY_UTILITY => 'ابزار',
        ];
    }
}
