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
    public const CATEGORY_MODULE = 'module';

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
            'flex_container' => [
                'name'     => 'فلکس کانتینر',
                'category' => self::CATEGORY_LAYOUT,
                'icon'     => 'flex-container',
                'schema'   => [
                    'label'          => ['type' => 'string',  'label' => 'نام',               'default' => 'کانتینر'],
                    'direction'      => ['type' => 'select',  'label' => 'جهت',               'default' => 'column',
                                         'options' => ['column' => 'عمودی', 'row' => 'افقی']],
                    'gap'            => ['type' => 'select',  'label' => 'فاصله بین بلوک‌ها', 'default' => 'md',
                                         'options' => ['none' => 'هیچ', 'xs' => 'خیلی کم', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد', 'xl' => 'خیلی زیاد']],
                    'alignItems'     => ['type' => 'select',  'label' => 'تراز (align)',       'default' => 'stretch',
                                         'options' => ['stretch' => 'کشیده', 'flex-start' => 'ابتدا', 'center' => 'وسط', 'flex-end' => 'انتها']],
                    'justifyContent' => ['type' => 'select',  'label' => 'توزیع (justify)',    'default' => 'flex-start',
                                         'options' => ['flex-start' => 'ابتدا', 'center' => 'وسط', 'flex-end' => 'انتها', 'space-between' => 'فاصله برابر', 'space-around' => 'فاصله دور']],
                    'wrap'           => ['type' => 'boolean', 'label' => 'شکست خط',            'default' => false],
                    'minHeight'      => ['type' => 'number',  'label' => 'ارتفاع حداقل (px)',  'default' => 0],
                ],
                'defaultProps' => [
                    'label' => 'کانتینر', 'direction' => 'column', 'gap' => 'md',
                    'alignItems' => 'stretch', 'justifyContent' => 'flex-start',
                    'wrap' => false, 'minHeight' => 0,
                ],
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
                    'url'     => ['type' => 'image',  'label' => 'تصویر',   'default' => ''],
                    'alt'     => ['type' => 'string', 'label' => 'متن جایگزین', 'default' => ''],
                    'caption' => ['type' => 'string', 'label' => 'کپشن',    'default' => ''],
                    'align'   => ['type' => 'string', 'label' => 'تراز',    'default' => 'center'],
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

            // ─── MODULE: موشن گرافیک ─────────────────────────────────────────
            // Authoring-time only: this entry exists so the Theme Builder
            // canvas preview shows a proper "موشن گرافیک" label instead of
            // the raw block type. Actual resolution/rendering only ever
            // happens on customer-site's ThemeCanvasRenderer, against a
            // specific site's own authorized Motion assignment — see
            // docs/theme-builder-motion/TEMPLATE_MOTION_CONTRACT.md.
            'module:motion' => [
                'name' => 'موشن گرافیک',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'sparkles',
                'schema' => [
                    'motion_asset_id' => ['type' => 'string', 'label' => 'شناسه دارایی موشن (Motion Asset ID)', 'default' => ''],
                    'placement' => ['type' => 'select', 'label' => 'جایگاه', 'options' => ['hero' => 'هیرو', 'section' => 'بخش', 'cta' => 'فراخوان به اقدام', 'background' => 'پس‌زمینه'], 'default' => 'section'],
                    'enabled' => ['type' => 'boolean', 'label' => 'فعال', 'default' => true],
                    'fallback_image' => ['type' => 'string', 'label' => 'تصویر جایگزین', 'default' => ''],
                ],
                'defaultProps' => ['motion_asset_id' => '', 'placement' => 'section', 'enabled' => true, 'fallback_image' => ''],
            ],

            // ─── MODULE: وبلاگ ───────────────────────────────────────────────
            'module:blog_posts' => [
                'name' => 'پست‌های وبلاگ',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'blog',
                'requires' => 'blog',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'آخرین مطالب'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد پست', 'default' => 6],
                    'layout' => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['grid' => 'گرید', 'list' => 'لیست', 'slider' => 'اسلایدر'], 'default' => 'grid'],
                    'show_excerpt' => ['type' => 'boolean', 'label' => 'نمایش خلاصه', 'default' => true],
                    'show_date' => ['type' => 'boolean', 'label' => 'نمایش تاریخ', 'default' => true],
                    'category_slug' => ['type' => 'string', 'label' => 'فیلتر دسته‌بندی (اختیاری)', 'default' => ''],
                ],
                'defaultProps' => ['title' => 'آخرین مطالب', 'limit' => 6, 'layout' => 'grid', 'show_excerpt' => true, 'show_date' => true, 'category_slug' => ''],
            ],
            'module:blog_featured' => [
                'name' => 'پست ویژه وبلاگ',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'star',
                'requires' => 'blog',
                'schema' => [
                    'post_slug' => ['type' => 'string', 'label' => 'اسلاگ پست', 'default' => ''],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['full' => 'کامل', 'compact' => 'فشرده'], 'default' => 'full'],
                ],
                'defaultProps' => ['post_slug' => '', 'style' => 'full'],
            ],
            'module:blog_categories' => [
                'name' => 'دسته‌بندی‌های وبلاگ',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'tags',
                'requires' => 'blog',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'دسته‌بندی‌ها'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['cards' => 'کارت', 'list' => 'لیست', 'badges' => 'نشان'], 'default' => 'cards'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 8],
                ],
                'defaultProps' => ['title' => 'دسته‌بندی‌ها', 'style' => 'cards', 'limit' => 8],
            ],

            // ─── MODULE: فروشگاه ─────────────────────────────────────────────
            'module:shop_product_grid' => [
                'name' => 'گرید محصولات فروشگاه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'grid',
                'requires' => 'shop',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'محصولات'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد محصول', 'default' => 8],
                    'columns' => ['type' => 'select', 'label' => 'تعداد ستون', 'options' => ['2' => '۲ ستون', '3' => '۳ ستون', '4' => '۴ ستون'], 'default' => '4'],
                    'source' => ['type' => 'select', 'label' => 'منبع', 'options' => ['recent' => 'جدیدترین', 'featured' => 'ویژه', 'bestselling' => 'پرفروش'], 'default' => 'recent'],
                    'category_slug' => ['type' => 'string', 'label' => 'دسته‌بندی (اختیاری)', 'default' => ''],
                ],
                'defaultProps' => ['title' => 'محصولات', 'limit' => 8, 'columns' => '4', 'source' => 'recent', 'category_slug' => ''],
            ],
            'module:shop_product_slider' => [
                'name' => 'اسلایدر محصولات',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'slider',
                'requires' => 'shop',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'محصولات پیشنهادی'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'source' => ['type' => 'select', 'label' => 'منبع', 'options' => ['recent' => 'جدیدترین', 'featured' => 'ویژه'], 'default' => 'featured'],
                    'autoplay' => ['type' => 'boolean', 'label' => 'پخش خودکار', 'default' => true],
                ],
                'defaultProps' => ['title' => 'محصولات پیشنهادی', 'limit' => 6, 'source' => 'featured', 'autoplay' => true],
            ],
            'module:shop_categories' => [
                'name' => 'دسته‌بندی‌های فروشگاه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'category',
                'requires' => 'shop',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'دسته‌بندی‌ها'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['grid' => 'گرید', 'list' => 'لیست', 'horizontal' => 'افقی'], 'default' => 'grid'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'show_count' => ['type' => 'boolean', 'label' => 'نمایش تعداد محصول', 'default' => true],
                ],
                'defaultProps' => ['title' => 'دسته‌بندی‌ها', 'style' => 'grid', 'limit' => 6, 'show_count' => true],
            ],
            'module:shop_cart_button' => [
                'name' => 'دکمه سبد خرید',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'cart',
                'requires' => 'shop',
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['icon' => 'آیکون', 'button' => 'دکمه', 'full' => 'کامل با قیمت'], 'default' => 'button'],
                    'label' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'سبد خرید'],
                ],
                'defaultProps' => ['style' => 'button', 'label' => 'سبد خرید'],
            ],
            'module:shop_countdown' => [
                'name' => 'شمارش معکوس تخفیف',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'clock',
                'requires' => 'shop',
                'schema' => [
                    'promo_text' => ['type' => 'string', 'label' => 'متن تبلیغ', 'default' => 'پیشنهاد ویژه — فرصت محدود!'],
                    'end_date' => ['type' => 'string', 'label' => 'تاریخ پایان (Y-m-d)', 'default' => ''],
                    'bg_color' => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => '#ef4444'],
                ],
                'defaultProps' => ['promo_text' => 'پیشنهاد ویژه — فرصت محدود!', 'end_date' => '', 'bg_color' => '#ef4444'],
            ],
            'module:shop_search_box' => [
                'name' => 'جستجوی فروشگاه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'search',
                'requires' => 'shop',
                'schema' => [
                    'placeholder' => ['type' => 'string', 'label' => 'متن placeholder', 'default' => 'جستجو در محصولات...'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['bar' => 'نوار', 'box' => 'باکس'], 'default' => 'bar'],
                ],
                'defaultProps' => ['placeholder' => 'جستجو در محصولات...', 'style' => 'bar'],
            ],

            // ─── MODULE: دوره‌ها ─────────────────────────────────────────────
            'module:courses_grid' => [
                'name' => 'گرید دوره‌ها',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'courses',
                'requires' => 'courses',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'دوره‌های آموزشی'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد دوره', 'default' => 6],
                    'columns' => ['type' => 'select', 'label' => 'تعداد ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'layout' => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['grid' => 'گرید', 'list' => 'لیست'], 'default' => 'grid'],
                    'show_price' => ['type' => 'boolean', 'label' => 'نمایش قیمت', 'default' => true],
                    'show_instructor' => ['type' => 'boolean', 'label' => 'نمایش مدرس', 'default' => true],
                ],
                'defaultProps' => ['title' => 'دوره‌های آموزشی', 'limit' => 6, 'columns' => '3', 'layout' => 'grid', 'show_price' => true, 'show_instructor' => true],
            ],
            'module:course_enroll_cta' => [
                'name' => 'دعوت به ثبت‌نام دوره',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'graduation',
                'requires' => 'courses',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'همین الان شروع کن!'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => 'به هزاران دانشجو بپیوند'],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'مشاهده دوره‌ها'],
                    'bg_color' => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => '#4f46e5'],
                ],
                'defaultProps' => ['title' => 'همین الان شروع کن!', 'subtitle' => 'به هزاران دانشجو بپیوند', 'button_text' => 'مشاهده دوره‌ها', 'bg_color' => '#4f46e5'],
            ],
            'module:my_learning_link' => [
                'name' => 'لینک یادگیری من',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'bookmark',
                'requires' => 'courses',
                'schema' => [
                    'label' => ['type' => 'string', 'label' => 'متن لینک', 'default' => 'یادگیری من'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['button' => 'دکمه', 'link' => 'لینک ساده', 'card' => 'کارت'], 'default' => 'button'],
                ],
                'defaultProps' => ['label' => 'یادگیری من', 'style' => 'button'],
            ],

            // ─── MODULE: فرم‌ها ──────────────────────────────────────────────
            'module:form_embed' => [
                'name' => 'درج فرم',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'form',
                'requires' => 'forms',
                'schema' => [
                    'form_id' => ['type' => 'string', 'label' => 'شناسه فرم (ID)', 'default' => ''],
                    'title' => ['type' => 'string', 'label' => 'عنوان نمایشی', 'default' => ''],
                    'show_title' => ['type' => 'boolean', 'label' => 'نمایش عنوان', 'default' => true],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['card' => 'کارت', 'minimal' => 'مینیمال', 'full' => 'تمام عرض'], 'default' => 'card'],
                ],
                'defaultProps' => ['form_id' => '', 'title' => '', 'show_title' => true, 'style' => 'card'],
            ],
            'module:contact_quick' => [
                'name' => 'فرم تماس سریع',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'phone',
                'requires' => null,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'تماس با ما'],
                    'show_phone' => ['type' => 'boolean', 'label' => 'نمایش شماره تلفن', 'default' => true],
                    'show_address' => ['type' => 'boolean', 'label' => 'نمایش آدرس', 'default' => true],
                    'show_map' => ['type' => 'boolean', 'label' => 'نمایش نقشه', 'default' => false],
                ],
                'defaultProps' => ['title' => 'تماس با ما', 'show_phone' => true, 'show_address' => true, 'show_map' => false],
            ],

            // ─── MODULE: اشتراک ──────────────────────────────────────────────
            'module:pricing_table' => [
                'name' => 'جدول اشتراک / تعرفه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'price',
                'requires' => 'subscription',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'پلن‌های اشتراک'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => 'پلنی که مناسب شماست را انتخاب کنید'],
                    'highlight_plan' => ['type' => 'string', 'label' => 'پلن برجسته (slug)', 'default' => ''],
                    'show_annual_toggle' => ['type' => 'boolean', 'label' => 'کلید ماهانه/سالانه', 'default' => false],
                ],
                'defaultProps' => ['title' => 'پلن‌های اشتراک', 'subtitle' => 'پلنی که مناسب شماست را انتخاب کنید', 'highlight_plan' => '', 'show_annual_toggle' => false],
            ],
            'module:plan_cta' => [
                'name' => 'دعوت به اشتراک',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'crown',
                'requires' => 'subscription',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'به کاربران ویژه بپیوند'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'مشاهده پلن‌ها'],
                    'bg_color' => ['type' => 'color', 'label' => 'رنگ', 'default' => '#7c3aed'],
                ],
                'defaultProps' => ['title' => 'به کاربران ویژه بپیوند', 'subtitle' => '', 'button_text' => 'مشاهده پلن‌ها', 'bg_color' => '#7c3aed'],
            ],
            'module:subscriber_stats' => [
                'name' => 'آمار مشترکین',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'users',
                'requires' => 'subscription',
                'schema' => [
                    'label' => ['type' => 'string', 'label' => 'برچسب', 'default' => 'کاربر فعال'],
                    'show_count' => ['type' => 'boolean', 'label' => 'نمایش تعداد', 'default' => true],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['badge' => 'نشان', 'card' => 'کارت'], 'default' => 'badge'],
                ],
                'defaultProps' => ['label' => 'کاربر فعال', 'show_count' => true, 'style' => 'badge'],
            ],

            // ─── MODULE: پشتیبانی و تعامل ───────────────────────────────────
            'module:support_button' => [
                'name' => 'دکمه پشتیبانی / تیکت',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'support',
                'requires' => null,
                'schema' => [
                    'label' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'پشتیبانی آنلاین'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['button' => 'دکمه ثابت', 'floating' => 'شناور'], 'default' => 'floating'],
                    'position' => ['type' => 'select', 'label' => 'موقعیت', 'options' => ['bottom-right' => 'پایین راست', 'bottom-left' => 'پایین چپ'], 'default' => 'bottom-right'],
                    'bg_color' => ['type' => 'color', 'label' => 'رنگ', 'default' => '#4f46e5'],
                ],
                'defaultProps' => ['label' => 'پشتیبانی آنلاین', 'style' => 'floating', 'position' => 'bottom-right', 'bg_color' => '#4f46e5'],
            ],
            'module:whatsapp_widget' => [
                'name' => 'ویجت واتساپ',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'whatsapp',
                'requires' => 'whatsapp',
                'schema' => [
                    'label' => ['type' => 'string', 'label' => 'متن', 'default' => 'پشتیبانی واتساپ'],
                    'phone' => ['type' => 'string', 'label' => 'شماره واتساپ', 'default' => ''],
                    'position' => ['type' => 'select', 'label' => 'موقعیت', 'options' => ['bottom-right' => 'پایین راست', 'bottom-left' => 'پایین چپ'], 'default' => 'bottom-right'],
                ],
                'defaultProps' => ['label' => 'پشتیبانی واتساپ', 'phone' => '', 'position' => 'bottom-right'],
            ],
            'module:reviews_grid' => [
                'name' => 'نمایش نظرات کاربران',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'star',
                'requires' => 'reviews',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'نظرات مشتریان'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['cards' => 'کارت', 'minimal' => 'مینیمال', 'carousel' => 'چرخشی'], 'default' => 'cards'],
                    'show_avatar' => ['type' => 'boolean', 'label' => 'نمایش آواتار', 'default' => true],
                ],
                'defaultProps' => ['title' => 'نظرات مشتریان', 'limit' => 6, 'style' => 'cards', 'show_avatar' => true],
            ],
            'module:referral_widget' => [
                'name' => 'ویجت معرفی به دوستان',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'share',
                'requires' => 'referral',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'دوستانت را دعوت کن'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => 'با معرفی دوستان جایزه بگیر'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['banner' => 'بنر', 'card' => 'کارت', 'minimal' => 'مینیمال'], 'default' => 'banner'],
                    'show_link' => ['type' => 'boolean', 'label' => 'نمایش لینک معرفی', 'default' => true],
                ],
                'defaultProps' => ['title' => 'دوستانت را دعوت کن', 'subtitle' => 'با معرفی دوستان جایزه بگیر', 'style' => 'banner', 'show_link' => true],
            ],

            // ─── MODULE: محتوا و نمایش ──────────────────────────────────────
            'module:content_cards' => [
                'name' => 'کارت‌های محتوا (دستیار AI)',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'ai',
                'requires' => null,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'مطالب'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 3],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['cards' => 'کارت', 'grid' => 'گرید', 'list' => 'لیست'], 'default' => 'cards'],
                    'show_excerpt' => ['type' => 'boolean', 'label' => 'نمایش خلاصه', 'default' => true],
                ],
                'defaultProps' => ['title' => 'مطالب', 'limit' => 3, 'style' => 'cards', 'show_excerpt' => true],
            ],
            'module:gallery_widget' => [
                'name' => 'گالری تصاویر',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'gallery',
                'requires' => 'gallery',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'گالری'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['masonry' => 'ماسونری', 'grid' => 'گرید', 'carousel' => 'چرخشی'], 'default' => 'grid'],
                    'columns' => ['type' => 'select', 'label' => 'ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                ],
                'defaultProps' => ['title' => 'گالری', 'style' => 'grid', 'columns' => '3'],
            ],
            'module:portfolio_grid' => [
                'name' => 'گرید نمونه‌کار (پورتفولیو)',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'portfolio',
                'requires' => 'portfolio',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'نمونه کارها'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'columns' => ['type' => 'select', 'label' => 'ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'show_filter' => ['type' => 'boolean', 'label' => 'نمایش فیلتر', 'default' => true],
                ],
                'defaultProps' => ['title' => 'نمونه کارها', 'limit' => 6, 'columns' => '3', 'show_filter' => true],
            ],
            'module:services_section' => [
                'name' => 'بخش خدمات',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'services',
                'requires' => 'services',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'خدمات ما'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['cards' => 'کارت', 'list' => 'لیست', 'icons' => 'آیکون‌دار'], 'default' => 'cards'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'columns' => ['type' => 'select', 'label' => 'ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                ],
                'defaultProps' => ['title' => 'خدمات ما', 'style' => 'cards', 'limit' => 6, 'columns' => '3'],
            ],
            'module:advertisement_zone' => [
                'name' => 'ناحیه تبلیغات',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'megaphone',
                'requires' => 'advertisements',
                'schema' => [
                    'zone_name' => ['type' => 'string', 'label' => 'نام ناحیه', 'default' => 'banner-main'],
                    'position' => ['type' => 'select', 'label' => 'موقعیت', 'options' => ['banner' => 'بنر', 'sidebar' => 'کنار', 'popup' => 'پاپ‌آپ'], 'default' => 'banner'],
                ],
                'defaultProps' => ['zone_name' => 'banner-main', 'position' => 'banner'],
            ],
            'module:faq_dynamic' => [
                'name' => 'سوالات متداول پویا',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'faq',
                'requires' => null,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'سوالات متداول'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 10],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['accordion' => 'آکوردئون', 'list' => 'لیست'], 'default' => 'accordion'],
                ],
                'defaultProps' => ['title' => 'سوالات متداول', 'limit' => 10, 'style' => 'accordion'],
            ],
            'module:notifications_bar' => [
                'name' => 'نوار اعلان سایت',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'bell',
                'requires' => null,
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['top-bar' => 'نوار بالا', 'floating' => 'شناور'], 'default' => 'top-bar'],
                    'show_dismiss' => ['type' => 'boolean', 'label' => 'دکمه بستن', 'default' => true],
                ],
                'defaultProps' => ['style' => 'top-bar', 'show_dismiss' => true],
            ],
            'module:account_quick_links' => [
                'name' => 'لینک‌های سریع حساب کاربری',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'user-circle',
                'requires' => null,
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['buttons' => 'دکمه', 'list' => 'لیست', 'dropdown' => 'منو کشویی'], 'default' => 'buttons'],
                    'show_avatar' => ['type' => 'boolean', 'label' => 'نمایش آواتار', 'default' => true],
                    'show_notifications' => ['type' => 'boolean', 'label' => 'نمایش اعلان‌ها', 'default' => true],
                ],
                'defaultProps' => ['style' => 'buttons', 'show_avatar' => true, 'show_notifications' => true],
            ],
            'module:social_links_bar' => [
                'name' => 'نوار شبکه‌های اجتماعی',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'share',
                'requires' => 'social_links',
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['horizontal' => 'افقی', 'vertical' => 'عمودی', 'icons-only' => 'فقط آیکون'], 'default' => 'horizontal'],
                    'color' => ['type' => 'select', 'label' => 'رنگ', 'options' => ['brand' => 'رنگ برند', 'white' => 'سفید', 'dark' => 'تیره'], 'default' => 'brand'],
                    'size' => ['type' => 'select', 'label' => 'اندازه', 'options' => ['sm' => 'کوچک', 'md' => 'متوسط', 'lg' => 'بزرگ'], 'default' => 'md'],
                ],
                'defaultProps' => ['style' => 'horizontal', 'color' => 'brand', 'size' => 'md'],
            ],
            'module:site_search' => [
                'name' => 'جستجوی پیشرفته سایت',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'search',
                'requires' => 'advanced_search',
                'schema' => [
                    'placeholder' => ['type' => 'string', 'label' => 'متن راهنما', 'default' => 'جستجو در سایت...'],
                    'show_suggestions' => ['type' => 'boolean', 'label' => 'پیشنهاد جستجو', 'default' => true],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['bar' => 'نوار', 'card' => 'کارت', 'hero' => 'قهرمان'], 'default' => 'bar'],
                ],
                'defaultProps' => ['placeholder' => 'جستجو در سایت...', 'show_suggestions' => true, 'style' => 'bar'],
            ],
            'module:business_card_embed' => [
                'name' => 'کارت ویزیت دیجیتال',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'card',
                'requires' => 'business_card',
                'schema' => [
                    'card_slug' => ['type' => 'string', 'label' => 'اسلاگ کارت', 'default' => ''],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['full' => 'کامل', 'compact' => 'فشرده'], 'default' => 'full'],
                ],
                'defaultProps' => ['card_slug' => '', 'style' => 'full'],
            ],
            'module:edu_article_grid' => [
                'name' => 'مقالات آموزشی',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'book',
                'requires' => null,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'مقالات آموزشی'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'layout' => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['grid' => 'گرید', 'list' => 'لیست'], 'default' => 'grid'],
                ],
                'defaultProps' => ['title' => 'مقالات آموزشی', 'limit' => 6, 'layout' => 'grid'],
            ],
            'module:invoice_quick_pay' => [
                'name' => 'پرداخت سریع فاکتور',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'invoice',
                'requires' => 'invoice',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'پرداخت فاکتور'],
                    'placeholder' => ['type' => 'string', 'label' => 'راهنمای ورودی', 'default' => 'شماره فاکتور را وارد کنید'],
                ],
                'defaultProps' => ['title' => 'پرداخت فاکتور', 'placeholder' => 'شماره فاکتور را وارد کنید'],
            ],

            // ─── NEXUS PRO PREMIUM BLOCKS ────────────────────────────────────
            'hero_split' => [
                'name' => 'Hero دو ستونه (Split Hero)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'hero-split',
                'schema' => [
                    'badge'          => ['type' => 'string',  'label' => 'نشان بالای عنوان',  'default' => ''],
                    'title'          => ['type' => 'string',  'label' => 'عنوان اصلی',         'default' => 'عنوان اصلی شما اینجاست'],
                    'subtitle'       => ['type' => 'textarea','label' => 'زیرعنوان',            'default' => 'توضیح کوتاهی درباره محصول یا خدمات شما'],
                    'button_text'    => ['type' => 'string',  'label' => 'متن دکمه اول',        'default' => 'شروع کنید'],
                    'button_url'     => ['type' => 'string',  'label' => 'لینک دکمه اول',       'default' => '#'],
                    'button2_text'   => ['type' => 'string',  'label' => 'متن دکمه دوم',        'default' => 'بیشتر بدانید'],
                    'button2_url'    => ['type' => 'string',  'label' => 'لینک دکمه دوم',       'default' => '#'],
                    'image_url'      => ['type' => 'image',   'label' => 'تصویر',                'default' => ''],
                    'image_side'     => ['type' => 'select',  'label' => 'موقعیت تصویر',        'options' => ['right' => 'راست', 'left' => 'چپ'], 'default' => 'left'],
                    'background'     => ['type' => 'color',   'label' => 'رنگ پس‌زمینه',        'default' => '#ffffff'],
                ],
                'defaultProps' => ['badge' => '', 'title' => 'عنوان اصلی شما اینجاست', 'subtitle' => 'توضیح کوتاهی درباره محصول یا خدمات شما', 'button_text' => 'شروع کنید', 'button_url' => '#', 'button2_text' => 'بیشتر بدانید', 'button2_url' => '#', 'image_url' => '', 'image_side' => 'left', 'background' => '#ffffff'],
            ],
            'hero_gradient' => [
                'name' => 'Hero گرادیان بزرگ',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'hero-gradient',
                'schema' => [
                    'badge'          => ['type' => 'string',  'label' => 'نشان بالای عنوان',  'default' => 'معرفی می‌کنیم'],
                    'title'          => ['type' => 'string',  'label' => 'عنوان اصلی',         'default' => 'قدرت آینده در دستان شما'],
                    'subtitle'       => ['type' => 'textarea','label' => 'زیرعنوان',            'default' => 'پلتفرم چندمنظوره‌ای که برای موفقیت کسب‌وکار شما طراحی شده است'],
                    'button_text'    => ['type' => 'string',  'label' => 'متن دکمه اول',        'default' => 'رایگان شروع کنید'],
                    'button_url'     => ['type' => 'string',  'label' => 'لینک دکمه اول',       'default' => '#'],
                    'button2_text'   => ['type' => 'string',  'label' => 'متن دکمه دوم',        'default' => 'مشاهده دمو'],
                    'button2_url'    => ['type' => 'string',  'label' => 'لینک دکمه دوم',       'default' => '#'],
                    'gradient_from'  => ['type' => 'color',   'label' => 'رنگ گرادیان ابتدا',   'default' => '#4f46e5'],
                    'gradient_to'    => ['type' => 'color',   'label' => 'رنگ گرادیان انتها',   'default' => '#7c3aed'],
                    'text_color'     => ['type' => 'color',   'label' => 'رنگ متن',             'default' => '#ffffff'],
                ],
                'defaultProps' => ['badge' => 'معرفی می‌کنیم', 'title' => 'قدرت آینده در دستان شما', 'subtitle' => 'پلتفرم چندمنظوره‌ای که برای موفقیت کسب‌وکار شما طراحی شده است', 'button_text' => 'رایگان شروع کنید', 'button_url' => '#', 'button2_text' => 'مشاهده دمو', 'button2_url' => '#', 'gradient_from' => '#4f46e5', 'gradient_to' => '#7c3aed', 'text_color' => '#ffffff'],
            ],
            'features_grid' => [
                'name' => 'گرید ویژگی‌ها (Features)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'features',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش',    'default' => 'چرا ما را انتخاب کنید؟'],
                    'subtitle' => ['type' => 'string',  'label' => 'زیرعنوان',      'default' => 'ما بهترین راه‌حل را برای شما آماده کرده‌ایم'],
                    'columns'  => ['type' => 'select',  'label' => 'تعداد ستون',   'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'style'    => ['type' => 'select',  'label' => 'استایل کارت',  'options' => ['cards' => 'کارت سایه‌دار', 'bordered' => 'کارت خطی', 'minimal' => 'مینیمال'], 'default' => 'cards'],
                    'features' => ['type' => 'textarea','label' => 'ویژگی‌ها (JSON)', 'default' => '[{"icon":"⚡","title":"سریع و قدرتمند","text":"بهینه‌سازی شده برای بالاترین کارایی"},{"icon":"🔒","title":"امن و مطمئن","text":"رمزنگاری پیشرفته برای حفاظت داده‌ها"},{"icon":"🎯","title":"دقیق و هوشمند","text":"هوش مصنوعی برای تصمیم‌های بهتر"},{"icon":"🚀","title":"مقیاس‌پذیر","text":"از یک کاربر تا میلیون‌ها کاربر"},{"icon":"💡","title":"نوآورانه","text":"جدیدترین فناوری‌ها در خدمت شما"},{"icon":"🌐","title":"چندزبانه","text":"پشتیبانی از فارسی و زبان‌های بین‌المللی"}]'],
                ],
                'defaultProps' => ['title' => 'چرا ما را انتخاب کنید؟', 'subtitle' => 'ما بهترین راه‌حل را برای شما آماده کرده‌ایم', 'columns' => '3', 'style' => 'cards', 'features' => '[{"icon":"⚡","title":"سریع و قدرتمند","text":"بهینه‌سازی شده برای بالاترین کارایی"},{"icon":"🔒","title":"امن و مطمئن","text":"رمزنگاری پیشرفته برای حفاظت داده‌ها"},{"icon":"🎯","title":"دقیق و هوشمند","text":"هوش مصنوعی برای تصمیم‌های بهتر"}]'],
            ],
            'stats_row' => [
                'name' => 'آمار و شمارنده (Stats Row)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'stats',
                'schema' => [
                    'title'      => ['type' => 'string',  'label' => 'عنوان (اختیاری)',  'default' => ''],
                    'stats'      => ['type' => 'textarea','label' => 'آمارها (JSON)',     'default' => '[{"value":5000,"suffix":"+","label":"کاربر فعال"},{"value":99,"suffix":"%","label":"رضایت مشتری"},{"value":150,"suffix":"+","label":"پروژه موفق"},{"value":7,"suffix":"سال","label":"تجربه"}]'],
                    'background' => ['type' => 'color',   'label' => 'رنگ پس‌زمینه',    'default' => '#f8fafc'],
                    'dividers'   => ['type' => 'boolean', 'label' => 'خط جداکننده',     'default' => true],
                ],
                'defaultProps' => ['title' => '', 'stats' => '[{"value":5000,"suffix":"+","label":"کاربر فعال"},{"value":99,"suffix":"%","label":"رضایت مشتری"},{"value":150,"suffix":"+","label":"پروژه موفق"},{"value":7,"suffix":"سال","label":"تجربه"}]', 'background' => '#f8fafc', 'dividers' => true],
            ],
            'client_logos' => [
                'name' => 'نوار لوگو مشتریان (Marquee)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'logos',
                'schema' => [
                    'title'  => ['type' => 'string',  'label' => 'عنوان',       'default' => 'مورد اعتماد برندهای بزرگ'],
                    'logos'  => ['type' => 'textarea','label' => 'لوگوها (JSON)','default' => '[{"name":"برند اول"},{"name":"برند دوم"},{"name":"برند سوم"},{"name":"برند چهارم"},{"name":"برند پنجم"},{"name":"برند ششم"}]'],
                    'scroll' => ['type' => 'boolean', 'label' => 'حرکت خودکار', 'default' => true],
                ],
                'defaultProps' => ['title' => 'مورد اعتماد برندهای بزرگ', 'logos' => '[{"name":"برند اول"},{"name":"برند دوم"},{"name":"برند سوم"},{"name":"برند چهارم"},{"name":"برند پنجم"},{"name":"برند ششم"}]', 'scroll' => true],
            ],
            'process_steps' => [
                'name' => 'مراحل انجام کار (Process Steps)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'steps',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش', 'default' => 'چطور شروع کنیم؟'],
                    'subtitle' => ['type' => 'string',  'label' => 'زیرعنوان',  'default' => ''],
                    'steps'    => ['type' => 'textarea','label' => 'مراحل (JSON)','default' => '[{"number":"۱","title":"ثبت‌نام رایگان","text":"در چند ثانیه حساب کاربری بسازید"},{"number":"۲","title":"تنظیم سایت","text":"قالب دلخواه را انتخاب کنید"},{"number":"۳","title":"راه‌اندازی","text":"سایت خود را به جهان معرفی کنید"}]'],
                ],
                'defaultProps' => ['title' => 'چطور شروع کنیم؟', 'subtitle' => '', 'steps' => '[{"number":"۱","title":"ثبت‌نام رایگان","text":"در چند ثانیه حساب کاربری بسازید"},{"number":"۲","title":"تنظیم سایت","text":"قالب دلخواه را انتخاب کنید"},{"number":"۳","title":"راه‌اندازی","text":"سایت خود را به جهان معرفی کنید"}]'],
            ],
            'team_cards' => [
                'name' => 'کارت اعضای تیم',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'team',
                'schema' => [
                    'title'   => ['type' => 'string',  'label' => 'عنوان بخش',   'default' => 'تیم ما'],
                    'columns' => ['type' => 'select',  'label' => 'تعداد ستون',  'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'members' => ['type' => 'textarea','label' => 'اعضا (JSON)',  'default' => '[{"name":"نام کامل","role":"مدیرعامل","image":"","bio":"توضیح کوتاه"},{"name":"نام کامل","role":"مدیر فنی","image":"","bio":"توضیح کوتاه"},{"name":"نام کامل","role":"طراح ارشد","image":"","bio":"توضیح کوتاه"}]'],
                ],
                'defaultProps' => ['title' => 'تیم ما', 'columns' => '3', 'members' => '[{"name":"نام کامل","role":"مدیرعامل","image":"","bio":"توضیح کوتاه"},{"name":"نام کامل","role":"مدیر فنی","image":"","bio":"توضیح کوتاه"},{"name":"نام کامل","role":"طراح ارشد","image":"","bio":"توضیح کوتاه"}]'],
            ],
            'skills_bars' => [
                'name' => 'نوارهای مهارت (Skills)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'skills',
                'schema' => [
                    'title'  => ['type' => 'string',  'label' => 'عنوان',       'default' => 'مهارت‌های من'],
                    'skills' => ['type' => 'textarea','label' => 'مهارت‌ها (JSON)','default' => '[{"name":"طراحی رابط کاربری","percent":92},{"name":"برنامه‌نویسی فرانت‌اند","percent":88},{"name":"توسعه بک‌اند","percent":80},{"name":"مدیریت پروژه","percent":75}]'],
                ],
                'defaultProps' => ['title' => 'مهارت‌های من', 'skills' => '[{"name":"طراحی رابط کاربری","percent":92},{"name":"برنامه‌نویسی فرانت‌اند","percent":88},{"name":"توسعه بک‌اند","percent":80}]'],
            ],
            'testimonials_slider' => [
                'name' => 'اسلایدر نظرات مشتریان',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'testimonials',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش',  'default' => 'مشتریان ما چه می‌گویند'],
                    'columns'  => ['type' => 'select',  'label' => 'تعداد ستون', 'options' => ['1' => '۱', '2' => '۲', '3' => '۳'], 'default' => '3'],
                    'items'    => ['type' => 'textarea','label' => 'نظرات (JSON)','default' => '[{"name":"رضا احمدی","role":"مدیر شرکت","text":"این پلتفرم کسب‌وکار ما را متحول کرد. واقعاً عالی است!","rating":5},{"name":"مریم نوری","role":"کارآفرین","text":"طراحی فوق‌العاده و پشتیبانی سریع. پیشنهاد می‌دهم!","rating":5},{"name":"علی حسینی","role":"توسعه‌دهنده","text":"بهترین انتخاب برای ساخت سایت حرفه‌ای بود.","rating":5}]'],
                ],
                'defaultProps' => ['title' => 'مشتریان ما چه می‌گویند', 'columns' => '3', 'items' => '[{"name":"رضا احمدی","role":"مدیر شرکت","text":"این پلتفرم کسب‌وکار ما را متحول کرد!","rating":5},{"name":"مریم نوری","role":"کارآفرین","text":"طراحی فوق‌العاده و پشتیبانی سریع.","rating":5},{"name":"علی حسینی","role":"توسعه‌دهنده","text":"بهترین انتخاب برای سایت حرفه‌ای.","rating":5}]'],
            ],
            'trust_badges' => [
                'name' => 'نشان‌های اعتماد (Trust Badges)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'trust',
                'schema' => [
                    'badges'     => ['type' => 'textarea','label' => 'نشان‌ها (JSON)', 'default' => '[{"icon":"🚚","title":"ارسال رایگان","text":"برای سفارش‌های بالای ۲۰۰ هزار تومان"},{"icon":"🔒","title":"پرداخت امن","text":"درگاه‌های پرداخت معتبر ایران"},{"icon":"↩️","title":"بازگشت آسان","text":"ضمانت بازگشت تا ۷ روز"},{"icon":"☎️","title":"پشتیبانی ۲۴/۷","text":"همیشه در کنار شما هستیم"}]'],
                    'background' => ['type' => 'color',   'label' => 'رنگ پس‌زمینه','default' => '#f8fafc'],
                ],
                'defaultProps' => ['badges' => '[{"icon":"🚚","title":"ارسال رایگان","text":"بالای ۲۰۰ هزار تومان"},{"icon":"🔒","title":"پرداخت امن","text":"درگاه‌های معتبر"},{"icon":"↩️","title":"بازگشت آسان","text":"تا ۷ روز"},{"icon":"☎️","title":"پشتیبانی ۲۴/۷","text":"همیشه در دسترس"}]', 'background' => '#f8fafc'],
            ],
            'pricing_cards' => [
                'name' => 'کارت‌های تعرفه (Pricing Cards)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'pricing',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش',  'default' => 'پلن‌های ما'],
                    'subtitle' => ['type' => 'string',  'label' => 'زیرعنوان',    'default' => 'مناسب‌ترین پلن را انتخاب کنید'],
                    'plans'    => ['type' => 'textarea','label' => 'پلن‌ها (JSON)','default' => '[{"name":"رایگان","price":"رایگان","period":"","badge":"","highlight":false,"features":["ویژگی اول","ویژگی دوم","ویژگی سوم"],"cta_text":"شروع رایگان","cta_url":"#"},{"name":"حرفه‌ای","price":"۱۵۰ هزار","period":"/ ماه","badge":"محبوب‌ترین","highlight":true,"features":["همه ویژگی‌های رایگان","ویژگی اضافه اول","ویژگی اضافه دوم","پشتیبانی اولویت‌دار"],"cta_text":"انتخاب این پلن","cta_url":"#"},{"name":"سازمانی","price":"۳۵۰ هزار","period":"/ ماه","badge":"","highlight":false,"features":["همه ویژگی‌های حرفه‌ای","حساب نامحدود","API اختصاصی","مدیر حساب"],"cta_text":"تماس بگیرید","cta_url":"#"}]'],
                ],
                'defaultProps' => ['title' => 'پلن‌های ما', 'subtitle' => 'مناسب‌ترین پلن را انتخاب کنید', 'plans' => '[{"name":"رایگان","price":"رایگان","period":"","badge":"","highlight":false,"features":["ویژگی اول","ویژگی دوم"],"cta_text":"شروع رایگان","cta_url":"#"},{"name":"حرفه‌ای","price":"۱۵۰ هزار","period":"/ ماه","badge":"محبوب‌ترین","highlight":true,"features":["همه ویژگی‌های رایگان","ویژگی اضافه"],"cta_text":"انتخاب این پلن","cta_url":"#"}]'],
            ],
            'newsletter_form' => [
                'name' => 'فرم خبرنامه (Newsletter)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'newsletter',
                'schema' => [
                    'title'       => ['type' => 'string', 'label' => 'عنوان',        'default' => 'در خبرنامه ما عضو شوید'],
                    'subtitle'    => ['type' => 'string', 'label' => 'زیرعنوان',     'default' => 'جدیدترین اخبار و پیشنهادات ویژه را دریافت کنید'],
                    'placeholder' => ['type' => 'string', 'label' => 'placeholder',  'default' => 'ایمیل یا شماره موبایل شما'],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه',     'default' => 'عضویت رایگان'],
                    'background'  => ['type' => 'color',  'label' => 'رنگ پس‌زمینه','default' => '#4f46e5'],
                    'text_color'  => ['type' => 'color',  'label' => 'رنگ متن',      'default' => '#ffffff'],
                ],
                'defaultProps' => ['title' => 'در خبرنامه ما عضو شوید', 'subtitle' => 'جدیدترین اخبار و پیشنهادات ویژه را دریافت کنید', 'placeholder' => 'ایمیل یا شماره موبایل شما', 'button_text' => 'عضویت رایگان', 'background' => '#4f46e5', 'text_color' => '#ffffff'],
            ],
            'bento_grid' => [
                'name' => 'گرید بنتو (Bento Grid)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'bento',
                'schema' => [
                    'title' => ['type' => 'string',  'label' => 'عنوان بخش',   'default' => 'همه چیز در یک پلتفرم'],
                    'items' => ['type' => 'textarea','label' => 'آیتم‌ها (JSON)','default' => '[{"title":"مدیریت هوشمند","text":"داشبورد جامع با تحلیل داده","icon":"📊","size":"large","bg_color":"#4f46e5","text_color":"#ffffff"},{"title":"امنیت پیشرفته","text":"رمزنگاری end-to-end","icon":"🔒","size":"small","bg_color":"#f0fdf4","text_color":"#14532d"},{"title":"سرعت بالا","text":"CDN جهانی","icon":"⚡","size":"small","bg_color":"#fef9c3","text_color":"#713f12"},{"title":"ادغام آسان","text":"بیش از ۵۰ ادغام","icon":"🔗","size":"small","bg_color":"#f0f9ff","text_color":"#0c4a6e"},{"title":"پشتیبانی ۲۴ ساعته","text":"تیم متخصص همیشه آماده","icon":"💬","size":"small","bg_color":"#fdf4ff","text_color":"#6b21a8"}]'],
                ],
                'defaultProps' => ['title' => 'همه چیز در یک پلتفرم', 'items' => '[{"title":"مدیریت هوشمند","text":"داشبورد جامع","icon":"📊","size":"large","bg_color":"#4f46e5","text_color":"#ffffff"},{"title":"امنیت پیشرفته","text":"رمزنگاری","icon":"🔒","size":"small","bg_color":"#f0fdf4","text_color":"#14532d"},{"title":"سرعت بالا","text":"CDN جهانی","icon":"⚡","size":"small","bg_color":"#fef9c3","text_color":"#713f12"}]'],
            ],
            'services_cards' => [
                'name' => 'کارت‌های خدمات (Services)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'services-cards',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش',    'default' => 'خدمات ما'],
                    'subtitle' => ['type' => 'string',  'label' => 'زیرعنوان',      'default' => ''],
                    'columns'  => ['type' => 'select',  'label' => 'تعداد ستون',   'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'style'    => ['type' => 'select',  'label' => 'استایل',        'options' => ['bordered' => 'خطی', 'filled' => 'پر', 'minimal' => 'مینیمال'], 'default' => 'bordered'],
                    'services' => ['type' => 'textarea','label' => 'خدمات (JSON)',  'default' => '[{"icon":"🎨","title":"طراحی رابط کاربری","text":"طراحی تجربه کاربری زیبا و کاربردی","link":"#","link_text":"بیشتر بدانید"},{"icon":"⚙️","title":"توسعه فنی","text":"پیاده‌سازی با جدیدترین فناوری‌ها","link":"#","link_text":"بیشتر بدانید"},{"icon":"📈","title":"بازاریابی دیجیتال","text":"افزایش حضور آنلاین کسب‌وکار شما","link":"#","link_text":"بیشتر بدانید"}]'],
                ],
                'defaultProps' => ['title' => 'خدمات ما', 'subtitle' => '', 'columns' => '3', 'style' => 'bordered', 'services' => '[{"icon":"🎨","title":"طراحی","text":"رابط کاربری زیبا","link":"#","link_text":"بیشتر"},{"icon":"⚙️","title":"توسعه","text":"فناوری‌های جدید","link":"#","link_text":"بیشتر"},{"icon":"📈","title":"بازاریابی","text":"حضور آنلاین","link":"#","link_text":"بیشتر"}]'],
            ],
            'contact_split' => [
                'name' => 'تماس دو ستونه (Contact Split)',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'contact',
                'schema' => [
                    'title'      => ['type' => 'string',  'label' => 'عنوان',       'default' => 'با ما در ارتباط باشید'],
                    'subtitle'   => ['type' => 'string',  'label' => 'زیرعنوان',    'default' => 'هر سوالی دارید، ما پاسخگو هستیم'],
                    'phone'      => ['type' => 'string',  'label' => 'شماره تلفن',  'default' => ''],
                    'email'      => ['type' => 'string',  'label' => 'ایمیل',        'default' => ''],
                    'address'    => ['type' => 'string',  'label' => 'آدرس',         'default' => ''],
                    'hours'      => ['type' => 'string',  'label' => 'ساعات کاری',  'default' => 'شنبه تا پنجشنبه، ۹ تا ۱۷'],
                    'show_form'  => ['type' => 'boolean', 'label' => 'نمایش فرم',   'default' => true],
                ],
                'defaultProps' => ['title' => 'با ما در ارتباط باشید', 'subtitle' => 'هر سوالی دارید، ما پاسخگو هستیم', 'phone' => '', 'email' => '', 'address' => '', 'hours' => 'شنبه تا پنجشنبه، ۹ تا ۱۷', 'show_form' => true],
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
            self::CATEGORY_LAYOUT    => 'چیدمان',
            self::CATEGORY_CONTENT   => 'محتوای ثابت',
            self::CATEGORY_DYNAMIC   => 'محتوای پویا',
            self::CATEGORY_ECOMMERCE => 'فروشگاه',
            self::CATEGORY_MARKETING => 'بازاریابی',
            self::CATEGORY_UTILITY   => 'ابزار',
            self::CATEGORY_MODULE    => 'ماژول‌ها',
        ];
    }

    /** Category icons for canvas sidebar. */
    public static function categoryIcons(): array
    {
        return [
            self::CATEGORY_LAYOUT    => '📐',
            self::CATEGORY_CONTENT   => '📝',
            self::CATEGORY_DYNAMIC   => '🔄',
            self::CATEGORY_ECOMMERCE => '🛒',
            self::CATEGORY_MARKETING => '📣',
            self::CATEGORY_UTILITY   => '🔧',
            self::CATEGORY_MODULE    => '🧩',
        ];
    }

    /** Get all module blocks grouped by their required module. */
    public static function moduleBlocksByModule(): array
    {
        $grouped = [];
        foreach (self::all() as $type => $def) {
            if ($def['category'] !== self::CATEGORY_MODULE) continue;
            $mod = $def['requires'] ?? '_core';
            $grouped[$mod][$type] = $def;
        }
        return $grouped;
    }
}
