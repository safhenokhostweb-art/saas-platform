<?php

namespace SaaSPlatform\ThemeRuntime\Header;

use SaaSPlatform\ThemeRuntime\Support\SocialNetworks;

/**
 * Unified Header Contract for Canvas inspectors.
 *
 * Provides a canonical, host-neutral schema covering 100% of header capabilities
 * across all visual skins (nav_header, header_glass_nimbus, header_modern_v1).
 */
final class SharedHeaderSchema
{
    /**
     * Complete categorized schema for the Canvas Inspector.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function schema(): array
    {
        return [
            // ─── Brand & Logo ────────────────────────────────────────────────────────
            'logo_image'         => ['type' => 'image', 'label' => 'تصویر لوگو', 'default' => '', 'group' => 'لوگو و برند'],
            'mobile_logo'        => ['type' => 'image', 'label' => 'لوگوی اختصاصی موبایل', 'default' => '', 'group' => 'لوگو و برند'],
            'logo_alt'           => ['type' => 'string', 'label' => 'متن جایگزین لوگو', 'default' => '', 'group' => 'لوگو و برند'],
            'logo_height'        => ['type' => 'range', 'unit' => 'px', 'label' => 'ارتفاع لوگو در دسکتاپ', 'default' => 40, 'min' => 20, 'max' => 120, 'step' => 1, 'group' => 'لوگو و برند'],
            'logo_height_mobile' => ['type' => 'range', 'unit' => 'px', 'label' => 'ارتفاع لوگو در موبایل', 'default' => 32, 'min' => 16, 'max' => 96, 'step' => 1, 'group' => 'لوگو و برند'],
            'logo_text'          => ['type' => 'string', 'label' => 'متن / نام برند', 'default' => '', 'group' => 'لوگو و برند'],
            'brand_name'         => ['type' => 'string', 'label' => 'نام برند (جایگزین متن لوگو)', 'default' => '', 'group' => 'لوگو و برند'],
            'logo_url'           => ['type' => 'link', 'label' => 'لینک لوگو', 'default' => '/', 'group' => 'لوگو و برند'],
            'logo_badge_symbol'  => ['type' => 'string', 'label' => 'نشان متنی / نماد برند (مانند ◆)', 'default' => '◆', 'group' => 'لوگو و برند'],
            'logo_icon'          => ['type' => 'icon', 'label' => 'آیکون کنار نام برند (اختیاری)', 'default' => '', 'group' => 'لوگو و برند'],
            'logo_text_accent'   => ['type' => 'string', 'label' => 'بخش رنگی نام برند', 'default' => '', 'group' => 'لوگو و برند'],
            'logo_tagline'       => ['type' => 'string', 'label' => 'شعار زیر نام برند', 'default' => '', 'group' => 'لوگو و برند'],
            'logo_animation'     => ['type' => 'select', 'label' => 'انیمیشن لوگو / آیکون برند', 'options' => ['none' => 'بدون انیمیشن', 'rotate_pop' => 'چرخش و جهش (Rotate & Pop 360°)', 'pulse' => 'پالس (Pulse)', 'float' => 'شناور (Float)', 'bounce' => 'پرش (Bounce)', 'spin' => 'چرخش مداوم (Spin)'], 'default' => 'none', 'group' => 'لوگو و برند'],
            'logo_animation_duration' => ['type' => 'number', 'label' => 'مدت چرخه انیمیشن (ثانیه)', 'default' => 5, 'min' => 1, 'max' => 20, 'step' => 1, 'group' => 'لوگو و برند', 'condition' => ['logo_animation!' => 'none']],

            // ─── Top Bar ─────────────────────────────────────────────────────────────
            'topbar_enable'      => ['type' => 'boolean', 'label' => 'فعال‌سازی نوار بالایی (Top Bar)', 'default' => false, 'group' => 'نوار بالایی (Top Bar)'],
            'topbar_hide_mobile' => ['type' => 'boolean', 'label' => 'مخفی‌سازی نوار بالایی در موبایل', 'default' => false, 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],
            'topbar_text'        => ['type' => 'string', 'label' => 'متن یا شعار نوار بالایی', 'default' => '', 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],
            'topbar_phone'       => ['type' => 'string', 'label' => 'شماره تماس نوار بالایی', 'default' => '', 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],
            'topbar_email'       => ['type' => 'string', 'label' => 'آدرس ایمیل نوار بالایی', 'default' => '', 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],
            'topbar_address'     => ['type' => 'string', 'label' => 'آدرس یا موقعیت مکانی', 'default' => '', 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],
            'topbar_bg'          => ['type' => 'color', 'label' => 'رنگ پس‌زمینه نوار بالایی', 'default' => '', 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],
            'topbar_color'       => ['type' => 'color', 'label' => 'رنگ متن نوار بالایی', 'default' => '', 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],
            'topbar_social_links'=> ['type' => 'boolean', 'label' => 'نمایش شبکه‌های اجتماعی در نوار بالا', 'default' => true, 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],
            'topbar_links'       => ['type' => 'repeater', 'label' => 'لینک‌های سفارشی نوار بالایی', 'item_label' => 'لینک', 'fields' => ['label' => ['type' => 'string', 'label' => 'عنوان لینک', 'default' => ''], 'url' => ['type' => 'link', 'label' => 'آدرس لینک', 'default' => '#'], 'icon' => ['type' => 'icon', 'label' => 'آیکون (اختیاری)', 'default' => '']], 'default' => [], 'group' => 'نوار بالایی (Top Bar)', 'condition' => ['topbar_enable' => true]],

            // ─── Navigation & Menu Source (3-way) ────────────────────────────────────
            'menu_align'         => ['type' => 'select', 'ui' => 'segmented', 'label' => 'چیدمان منو', 'options' => ['start' => 'راست (کنار لوگو)', 'center' => 'وسط', 'end' => 'چپ (کنار اکشن‌ها)'], 'default' => 'center', 'group' => 'ناوبری و منو'],
            'menu_source'        => [
                'type' => 'select',
                'label' => 'منبع منوی ناوبری',
                'options' => [
                    'custom_links' => 'لینک‌های سفارشی دستی (اینسپکتور)',
                    'menu_module'  => 'منوی تعریف‌شده در ماژول «منو و ناوبری» (/admin/menus)',
                    'pages_module' => 'صفحات منتشرشده در ماژول «صفحات» (/admin/pages)',
                ],
                'default' => 'custom_links',
                'group' => 'ناوبری و منو',
            ],
            'menu_items'         => [
                'type' => 'repeater',
                'label' => 'آیتم‌های منو (معادل لینک‌های ناوبری)',
                'item_label' => 'لینک منو',
                'storage' => 'array',
                'fields' => [
                    'label' => ['type' => 'string', 'label' => 'عنوان لینک', 'default' => ''],
                    'url' => ['type' => 'link', 'label' => 'آدرس لینک', 'default' => '#'],
                ],
                'default' => [],
                'group' => 'ناوبری و منو',
                'condition' => ['menu_source' => 'custom_links'],
            ],
            'selected_menu_id'   => [
                'type' => 'select',
                'label' => 'انتخاب منو از ماژول منو',
                'options' => [
                    'primary' => 'منوی اصلی',
                    'header' => 'منوی هدر',
                    'footer' => 'منوی پاورقی',
                ],
                'options_source' => 'customer_menus',
                'default' => 'primary',
                'group' => 'ناوبری و منو',
                'condition' => ['menu_source' => 'menu_module'],
            ],
            'selected_page_ids'  => [
                'type' => 'array',
                'label' => 'انتخاب صفحات از ماژول صفحات',
                'options' => [], // Populated dynamically in editor
                'options_source' => 'customer_pages',
                'default' => [],
                'group' => 'ناوبری و منو',
                'condition' => ['menu_source' => 'pages_module'],
            ],
            'nav_links'          => [
                'type' => 'repeater',
                'label' => 'لینک‌های دستی منو و مگامنو',
                'item_label' => 'لینک',
                'storage' => 'array',
                'fields' => [
                    'label' => ['type' => 'string', 'label' => 'متن لینک', 'default' => ''],
                    'url' => ['type' => 'link', 'label' => 'آدرس لینک', 'default' => '#'],
                    'icon' => ['type' => 'icon', 'label' => 'آیکون کنار عنوان (اختیاری)', 'default' => ''],
                    'mega_columns' => [
                        'type' => 'repeater',
                        'label' => 'ستون‌های Mega Menu (اختیاری)',
                        'item_label' => 'ستون',
                        'fields' => [
                            'title' => ['type' => 'string', 'label' => 'عنوان ستون', 'default' => ''],
                            'items' => [
                                'type' => 'repeater',
                                'label' => 'آیتم‌های این ستون',
                                'item_label' => 'آیتم',
                                'fields' => [
                                    'label' => ['type' => 'string', 'label' => 'متن لینک', 'default' => ''],
                                    'url' => ['type' => 'link', 'label' => 'آدرس لینک', 'default' => '#'],
                                    'icon' => ['type' => 'icon', 'label' => 'آیکون (اختیاری)', 'default' => ''],
                                    'image' => ['type' => 'image', 'label' => 'تصویر آیتم (اختیاری)', 'default' => ''],
                                    'badge' => ['type' => 'string', 'label' => 'نشان کنار عنوان', 'default' => ''],
                                    'badge_variant' => ['type' => 'select', 'label' => 'رنگ نشان', 'options' => ['primary' => 'رنگ اصلی', 'accent' => 'رنگ تأکیدی', 'success' => 'سبز', 'warning' => 'هشدار', 'neutral' => 'خنثی'], 'default' => 'primary'],
                                ],
                            ],
                        ],
                    ],
                    'mega_banner_image' => ['type' => 'image', 'label' => 'تصویر بنر Mega Menu', 'default' => ''],
                    'mega_banner_url' => ['type' => 'link', 'label' => 'لینک بنر', 'default' => '#'],
                    'mega_banner_text' => ['type' => 'string', 'label' => 'متن روی بنر', 'default' => ''],
                    'mega_banner_cta' => ['type' => 'string', 'label' => 'متن دکمه بنر', 'default' => ''],
                    'mega_promo_cards' => [
                        'type' => 'repeater',
                        'label' => 'کارت‌های پیشنهادی پایین مگامنو',
                        'item_label' => 'کارت',
                        'fields' => [
                            'eyebrow' => ['type' => 'string', 'label' => 'برچسب کوچک', 'default' => ''],
                            'title' => ['type' => 'string', 'label' => 'عنوان کارت', 'default' => ''],
                            'url' => ['type' => 'link', 'label' => 'لینک کارت', 'default' => '#'],
                            'icon' => ['type' => 'icon', 'label' => 'آیکون', 'default' => 'arrow-left'],
                        ],
                    ],
                    'mega_show_desktop' => ['type' => 'boolean', 'label' => 'نمایش Mega Menu در دسکتاپ', 'default' => true],
                    'mega_mobile_behavior' => ['type' => 'select', 'label' => 'رفتار در موبایل', 'options' => ['accordion' => 'آکاردئونی', 'flat' => 'فهرست تخت', 'hidden' => 'مخفی در موبایل'], 'default' => 'accordion'],
                ],
                'default' => [],
                'group' => 'ناوبری و منو',
                'condition' => ['menu_source' => 'custom_links'],
            ],

            // ─── Actions & Quick Access ──────────────────────────────────────────────
            'phone'              => ['type' => 'string', 'label' => 'شماره تماس', 'default' => '', 'group' => 'دسترسی سریع و اکشن‌ها'],
            'cta_text'           => ['type' => 'string', 'label' => 'متن دکمه اصلی (CTA)', 'default' => 'شروع کنید', 'group' => 'دسترسی سریع و اکشن‌ها'],
            'cta_label'          => ['type' => 'string', 'label' => 'متن دکمه اصلی (جایگزین متن CTA)', 'default' => '', 'group' => 'دسترسی سریع و اکشن‌ها'],
            'cta_url'            => ['type' => 'link', 'label' => 'لینک دکمه اصلی', 'default' => '/contact', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['cta_text!' => '']],
            'cta_icon'           => ['type' => 'icon', 'label' => 'آیکون دکمه اصلی (اختیاری)', 'default' => '', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['cta_text!' => '']],
            'cta_style'          => ['type' => 'select', 'ui' => 'segmented', 'label' => 'ظاهر دکمه اصلی', 'options' => ['primary' => 'توپر', 'outline' => 'خطی', 'gradient' => 'گرادیانی'], 'default' => 'primary', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['cta_text!' => '']],
            'cta_color_start'    => ['type' => 'color', 'label' => 'رنگ شروع گرادیان / رنگ اصلی دکمه', 'default' => '#e20c35', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['cta_text!' => '']],
            'cta_color_end'      => ['type' => 'color', 'label' => 'رنگ پایان گرادیان دکمه', 'default' => '#c4008d', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['cta_text!' => '']],

            'cta2_text'          => ['type' => 'string', 'label' => 'متن دکمه ثانویه (مانند ورود)', 'default' => '', 'group' => 'دسترسی سریع و اکشن‌ها'],
            'secondary_label'    => ['type' => 'string', 'label' => 'متن لینک ثانویه (جایگزین دکمه ثانویه)', 'default' => '', 'group' => 'دسترسی سریع و اکشن‌ها'],
            'cta2_url'           => ['type' => 'link', 'label' => 'لینک دکمه ثانویه', 'default' => '/login', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['cta2_text!' => '']],
            'secondary_url'      => ['type' => 'link', 'label' => 'آدرس لینک ثانویه', 'default' => '/login', 'group' => 'دسترسی سریع و اکشن‌ها'],
            'cta2_icon'          => ['type' => 'icon', 'label' => 'آیکون دکمه ثانویه (اختیاری)', 'default' => '', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['cta2_text!' => '']],
            'cta2_style'         => ['type' => 'select', 'ui' => 'segmented', 'label' => 'ظاهر دکمه ثانویه', 'options' => ['primary' => 'توپر', 'outline' => 'خطی', 'subtle' => 'ساده'], 'default' => 'outline', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['cta2_text!' => '']],

            'show_login_btn'     => ['type' => 'boolean', 'label' => 'نمایش دکمه ورود', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
            'show_register_btn'  => ['type' => 'boolean', 'label' => 'نمایش دکمه ثبت‌نام', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
            'show_search'        => ['type' => 'boolean', 'label' => 'نمایش جستجو', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
            'search_mode'        => ['type' => 'select', 'label' => 'حالت جستجو', 'options' => ['input' => 'فیلد جستجوی ثابت', 'icon' => 'آیکون با پنجره بازشو'], 'default' => 'icon', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['show_search' => true]],
            'search_placeholder' => ['type' => 'string', 'label' => 'متن جای‌گزین جستجو', 'default' => 'جستجو...', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['show_search' => true]],
            'search_scope'       => ['type' => 'select', 'label' => 'محدوده جستجو', 'options' => ['all' => 'همه (محصولات و مطالب)', 'products' => 'فقط محصولات', 'posts' => 'فقط مطالب'], 'default' => 'all', 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['show_search' => true]],
            'show_cart'          => ['type' => 'boolean', 'label' => 'نمایش سبد خرید (با شمارنده زنده)', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
            'show_account'       => ['type' => 'boolean', 'label' => 'نمایش حساب کاربری', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
            'show_dark_toggle'   => ['type' => 'boolean', 'label' => 'نمایش تغییر حالت روشن/تاریک', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
            'social_links'       => [
                'type' => 'repeater',
                'label' => 'شبکه‌های اجتماعی و پیام‌رسان‌ها',
                'item_label' => 'شبکه',
                'storage' => 'array',
                'fields' => [
                    'url' => ['type' => 'link', 'label' => 'آدرس', 'default' => '#'],
                    'label' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
                    'network' => ['type' => 'select', 'label' => 'شبکه (آیکون)', 'options' => SocialNetworks::options(), 'default' => 'auto'],
                    'custom_color' => ['type' => 'color', 'label' => 'رنگ سفارشی آیکون', 'default' => ''],
                ],
                'default' => [],
                'group' => 'دسترسی سریع و اکشن‌ها',
            ],
            'social_style'       => [
                'type' => 'select',
                'ui' => 'segmented',
                'label' => 'سبک رنگ آیکون‌ها',
                'options' => ['mono' => 'هم‌رنگ متن', 'brand' => 'رنگ برند هر شبکه', 'custom' => 'سفارشی'],
                'default' => 'mono',
                'group' => 'دسترسی سریع و اکشن‌ها',
                'condition' => ['social_links!' => ''],
            ],
            'social_show_mobile' => ['type' => 'boolean', 'label' => 'نمایش شبکه‌های اجتماعی در موبایل', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها', 'condition' => ['social_links!' => '']],
            'header_icons_animation' => ['type' => 'select', 'label' => 'انیمیشن آیکون‌های اکشن هدر', 'options' => ['none' => 'بدون انیمیشن', 'rotate_pop' => 'چرخش و جهش (Rotate & Pop 360°)', 'pulse' => 'پالس (Pulse)', 'float' => 'شناور (Float)', 'bounce' => 'پرش (Bounce)', 'spin' => 'چرخش مداوم (Spin)'], 'default' => 'none', 'group' => 'دسترسی سریع و اکشن‌ها'],

            // ─── Style: Colors, Spacing & Container ──────────────────────────────────
            'bg_color'           => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => '#ffffff', 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'text_color'         => ['type' => 'color', 'label' => 'رنگ متن و لینک‌ها', 'default' => '#111827', 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'ink_color'          => ['type' => 'color', 'label' => 'رنگ قلم و المان‌های شناور (Ink)', 'default' => '#211c48', 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'min_height'          => ['type' => 'range', 'unit' => 'px', 'label' => 'حداقل ارتفاع هدر', 'default' => 64, 'mobile_default' => 56, 'min' => 40, 'max' => 200, 'step' => 1, 'responsive' => true, 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'padding'             => ['type' => 'spacing', 'label' => 'فاصله داخلی هدر', 'units' => ['px', 'rem'], 'responsive' => true, 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'items_gap'           => ['type' => 'range', 'unit' => 'px', 'label' => 'فاصله بین اجزای اصلی', 'default' => '', 'min' => 0, 'max' => 80, 'step' => 1, 'responsive' => true, 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'content_width'       => ['type' => 'select', 'ui' => 'segmented', 'label' => 'عرض محتوای هدر', 'options' => ['boxed' => 'محدود (کادر)', 'full' => 'تمام‌عرض'], 'default' => 'boxed', 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'content_max_width'   => ['type' => 'range', 'unit' => 'px', 'label' => 'حداکثر عرض محتوا', 'default' => 1280, 'min' => 600, 'max' => 2400, 'step' => 10, 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر', 'condition' => ['content_width' => 'boxed']],
            'border_bottom_width' => ['type' => 'range', 'unit' => 'px', 'label' => 'ضخامت خط حاشیه پایین', 'default' => 0, 'min' => 0, 'max' => 10, 'step' => 1, 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'border_bottom_color' => ['type' => 'color', 'label' => 'رنگ خط حاشیه پایین', 'default' => '', 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر', 'condition' => ['border_bottom_width!' => [0, '']]],
            'header_shadow'       => ['type' => 'shadow', 'label' => 'سایه هدر', 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],
            'backdrop_blur'       => ['type' => 'range', 'unit' => 'px', 'label' => 'محوشدگی شیشه‌ای (Backdrop blur)', 'default' => 16, 'min' => 0, 'max' => 30, 'step' => 1, 'panel' => 'style', 'group' => 'رنگ‌ها و کانتینر'],

            // Style: Menu Navigation Typography & Interaction
            'menu_typography'     => ['type' => 'typography', 'label' => 'تایپوگرافی منو', 'responsive' => true, 'panel' => 'style', 'group' => 'استایل منوی ناوبری'],
            'menu_gap'            => ['type' => 'range', 'unit' => 'px', 'label' => 'فاصله بین آیتم‌ها', 'default' => '', 'min' => 0, 'max' => 80, 'step' => 1, 'responsive' => true, 'panel' => 'style', 'group' => 'استایل منوی ناوبری'],
            'menu_item_padding_x' => ['type' => 'range', 'unit' => 'px', 'label' => 'پدینگ افقی آیتم', 'default' => '', 'min' => 0, 'max' => 40, 'step' => 1, 'responsive' => true, 'panel' => 'style', 'group' => 'استایل منوی ناوبری'],
            'menu_item_padding_y' => ['type' => 'range', 'unit' => 'px', 'label' => 'پدینگ عمودی آیتم', 'default' => '', 'min' => 0, 'max' => 40, 'step' => 1, 'responsive' => true, 'panel' => 'style', 'group' => 'استایل منوی ناوبری'],
            'menu_color'          => ['type' => 'color', 'label' => 'رنگ متن آیتم', 'default' => '', 'panel' => 'style', 'group' => 'استایل منوی ناوبری', 'state_group' => 'menu_text', 'state' => 'normal'],
            'menu_color_hover'    => ['type' => 'color', 'label' => 'رنگ متن آیتم (هاور)', 'default' => '', 'panel' => 'style', 'group' => 'استایل منوی ناوبری', 'state_group' => 'menu_text', 'state' => 'hover'],
            'menu_color_active'   => ['type' => 'color', 'label' => 'رنگ متن آیتم فعال', 'default' => '', 'panel' => 'style', 'group' => 'استایل منوی ناوبری', 'state_group' => 'menu_text', 'state' => 'active'],
            'pointer'             => ['type' => 'select', 'label' => 'افکت اشاره‌گر (Pointer)', 'options' => ['underline' => 'زیرخط', 'overline' => 'روخط', 'double' => 'دوخط', 'framed' => 'قاب', 'background' => 'پس‌زمینه', 'pill' => 'کپسول شیشه‌ای (Pill)', 'text' => 'فقط رنگ متن', 'none' => 'بدون افکت'], 'default' => 'pill', 'panel' => 'style', 'group' => 'استایل منوی ناوبری'],
            'menu_item_radius'    => ['type' => 'range', 'unit' => 'px', 'label' => 'گردی گوشه کپسول', 'default' => 12, 'min' => 0, 'max' => 40, 'step' => 1, 'panel' => 'style', 'group' => 'استایل منوی ناوبری'],
            'menu_icon_size'      => ['type' => 'range', 'unit' => 'px', 'label' => 'اندازه آیکون منو', 'default' => 18, 'min' => 10, 'max' => 32, 'step' => 1, 'panel' => 'style', 'group' => 'استایل منوی ناوبری'],
            'menu_icon_color'     => ['type' => 'color', 'label' => 'رنگ آیکون منو', 'default' => '', 'panel' => 'style', 'group' => 'استایل منوی ناوبری'],

            // Style: Action Buttons & Icons
            'action_icon_bg'      => ['type' => 'color', 'label' => 'پس‌زمینه دکمه‌های آیکونی', 'default' => '', 'panel' => 'style', 'group' => 'دکمه‌های اکشن و آیکونی'],
            'action_icon_border'  => ['type' => 'color', 'label' => 'رنگ قاب دکمه‌های آیکونی', 'default' => '', 'panel' => 'style', 'group' => 'دکمه‌های اکشن و آیکونی'],
            'action_icon_color'   => ['type' => 'color', 'label' => 'رنگ آیکون‌های اکشن', 'default' => '', 'panel' => 'style', 'group' => 'دکمه‌های اکشن و آیکونی'],
            'action_icon_radius'  => ['type' => 'range', 'unit' => 'px', 'label' => 'گردی گوشه دکمه‌های آیکونی', 'default' => 8, 'min' => 0, 'max' => 40, 'step' => 1, 'panel' => 'style', 'group' => 'دکمه‌های اکشن و آیکونی'],
            'cta_glow_enabled'    => ['type' => 'boolean', 'label' => 'افکت نورانی CTA و تماس', 'default' => false, 'panel' => 'style', 'group' => 'دکمه‌های اکشن و آیکونی'],
            'cta_glow_color'      => ['type' => 'color', 'label' => 'رنگ افکت نورانی', 'default' => '', 'panel' => 'style', 'group' => 'دکمه‌های اکشن و آیکونی', 'condition' => ['cta_glow_enabled' => true]],
            'cta_radius'          => ['type' => 'range', 'unit' => 'px', 'label' => 'گردی گوشه دکمه CTA', 'default' => 12, 'min' => 0, 'max' => 40, 'step' => 1, 'panel' => 'style', 'group' => 'دکمه‌های اکشن و آیکونی'],

            // ─── Advanced: Sticky, Responsive & Mobile ───────────────────────────────
            'sticky'                => ['type' => 'boolean', 'label' => 'چسبان به بالا (Sticky)', 'default' => true, 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول'],
            'sticky_hide_on_scroll' => ['type' => 'boolean', 'label' => 'هوشمند: پنهان در اسکرول پایین، آشکار در اسکرول بالا', 'default' => false, 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول', 'condition' => ['sticky' => true]],
            'sticky_devices'        => ['type' => 'select', 'label' => 'چسبان در دستگاه‌های', 'options' => ['all' => 'همه دستگاه‌ها', 'desktop_tablet' => 'دسکتاپ و تبلت', 'desktop' => 'فقط دسکتاپ'], 'default' => 'all', 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول', 'condition' => ['sticky' => true]],
            'sticky_offset'         => ['type' => 'range', 'unit' => 'px', 'label' => 'فاصله از بالای صفحه هنگام چسبیدن', 'default' => 12, 'min' => 0, 'max' => 300, 'step' => 1, 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول', 'condition' => ['sticky' => true]],
            'scroll_threshold'      => ['type' => 'range', 'unit' => 'px', 'label' => 'میزان اسکرول تا تغییر حالت', 'default' => 8, 'min' => 0, 'max' => 600, 'step' => 1, 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول', 'condition' => ['sticky' => true]],
            'sticky_shrink'         => ['type' => 'boolean', 'label' => 'کوچک‌شدن هدر هنگام اسکرول', 'default' => false, 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول', 'condition' => ['sticky' => true]],
            'scrolled_bg_color'     => ['type' => 'color', 'label' => 'رنگ پس‌زمینه پس از اسکرول', 'default' => '', 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول', 'condition' => ['sticky' => true]],
            'scrolled_text_color'   => ['type' => 'color', 'label' => 'رنگ متن پس از اسکرول', 'default' => '', 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول', 'condition' => ['sticky' => true]],
            'z_index'               => ['type' => 'number', 'label' => 'ترتیب لایه (z-index)', 'default' => 900, 'min' => 1, 'max' => 9999, 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول', 'condition' => ['sticky' => true]],
            'transparent_over_hero' => ['type' => 'boolean', 'label' => 'شفاف روی بنر بالای صفحه', 'default' => false, 'panel' => 'advanced', 'group' => 'هدر چسبان و اسکرول'],

            // Advanced: Mobile Menu (Drawer / Popover)
            'burger_breakpoint'     => ['type' => 'select', 'label' => 'نمایش منوی همبرگری', 'options' => ['mobile' => 'موبایل (کمتر از ۷۶۸ پیکسل)', 'tablet' => 'تبلت و موبایل (کمتر از ۱۰۲۴ پیکسل)', 'always' => 'همیشه', 'never' => 'هرگز'], 'default' => 'mobile', 'panel' => 'advanced', 'group' => 'منوی موبایل'],
            'drawer_style'          => ['type' => 'select', 'ui' => 'segmented', 'label' => 'سبک منوی موبایل', 'options' => ['popover' => 'پنجره شیشه‌ای (Popover)', 'side' => 'کشوی کناری', 'full' => 'تمام‌صفحه', 'dropdown' => 'زیر هدر'], 'default' => 'popover', 'panel' => 'advanced', 'group' => 'منوی موبایل', 'condition' => ['burger_breakpoint!' => 'never']],
            'drawer_side'           => ['type' => 'select', 'ui' => 'segmented', 'label' => 'جهت بازشدن کشو', 'options' => ['start' => 'راست', 'end' => 'چپ'], 'default' => 'start', 'panel' => 'advanced', 'group' => 'منوی موبایل', 'condition' => ['burger_breakpoint!' => 'never', 'drawer_style' => 'side']],
            'drawer_show_logo'      => ['type' => 'boolean', 'label' => 'نمایش لوگو در منوی موبایل', 'default' => true, 'panel' => 'advanced', 'group' => 'منوی موبایل', 'condition' => ['burger_breakpoint!' => 'never']],
            'drawer_show_search'    => ['type' => 'boolean', 'label' => 'نمایش جستجو در منوی موبایل', 'default' => true, 'panel' => 'advanced', 'group' => 'منوی موبایل', 'condition' => ['burger_breakpoint!' => 'never', 'show_search' => true]],
            'drawer_show_ctas'      => ['type' => 'boolean', 'label' => 'نمایش دکمه‌ها در منوی موبایل', 'default' => true, 'panel' => 'advanced', 'group' => 'منوی موبایل', 'condition' => ['burger_breakpoint!' => 'never']],
            'compact_below'         => ['type' => 'select', 'label' => 'حالت فشردهٔ دسکتاپ (برای منوهای پرآیتم)', 'options' => ['off' => 'خاموش', '1280' => 'از ۱۰۲۴ تا ۱۲۷۹ پیکسل'], 'default' => 'off', 'panel' => 'advanced', 'group' => 'حالت فشردهٔ دسکتاپ'],

            // Mega menu style
            'mega_layout'           => ['type' => 'select', 'label' => 'چیدمان پنل مگامنو', 'options' => ['full' => 'نوار تمام‌عرض', 'card' => 'کارت شناور'], 'default' => 'full', 'panel' => 'style', 'group' => 'مگامنو'],
            'mega_icon_style'       => ['type' => 'select', 'label' => 'سبک آیکون آیتم‌های مگامنو', 'options' => ['bare' => 'ساده (بدون کادر)', 'boxed' => 'کادر رنگی (Boxed)'], 'default' => 'bare', 'panel' => 'style', 'group' => 'مگامنو'],

            // Advanced: Order & A11y
            'header_order'          => ['type' => 'array', 'label' => 'ترتیب اجزای هدر', 'default' => ['logo', 'menu', 'search', 'social', 'phone', 'cta', 'cart', 'account', 'dark_toggle', 'burger'], 'group' => 'چیدمان'],
            'menu_aria_label'       => ['type' => 'string', 'label' => 'نام منو برای صفحه‌خوان', 'default' => 'منوی اصلی', 'panel' => 'advanced', 'group' => 'دسترس‌پذیری'],
            'asset_version'         => ['type' => 'string', 'label' => 'نسخه هدر', 'default' => '1.2.0', 'group' => 'پیشرفته'],

            // Legacy camelCase Aliases for 100% Backwards Compatibility
            'bgColor'               => ['type' => 'color', 'label' => 'رنگ پس‌زمینه (قدیمی)', 'default' => '#ffffff'],
            'textColor'             => ['type' => 'color', 'label' => 'رنگ متن (قدیمی)', 'default' => '#111827'],
            'ctaColor'              => ['type' => 'color', 'label' => 'رنگ دکمه (قدیمی)', 'default' => '#e20c35'],
            'brandName'             => ['type' => 'string', 'label' => 'نام برند (قدیمی)', 'default' => ''],
            'logoUrl'               => ['type' => 'link', 'label' => 'لوگو / خانه (قدیمی)', 'default' => '/'],
            'ctaText'               => ['type' => 'string', 'label' => 'متن دکمه (قدیمی)', 'default' => ''],
            'ctaUrl'                => ['type' => 'link', 'label' => 'لینک دکمه (قدیمی)', 'default' => '/contact'],
            'menuItems'             => ['type' => 'repeater', 'label' => 'آیتم‌های منو (قدیمی)', 'fields' => ['label' => ['type' => 'string', 'label' => 'عنوان'], 'url' => ['type' => 'link', 'label' => 'آدرس']], 'default' => []],
        ];
    }

    /**
     * Default properties tailored to each visual skin.
     *
     * @param string $skin
     * @return array<string, mixed>
     */
    public static function defaultProps(string $skin = 'nav_header'): array
    {
        $base = [
            'asset_version' => '1.2.0',
            'logo_text' => 'برند شما',
            'logo_url' => '/',
            'logo_badge_symbol' => '◆',
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
            'sticky' => true,
            'burger_breakpoint' => 'mobile',
            'cta_text' => 'شروع کنید',
            'cta_url' => '/contact',
            'cta2_text' => 'ورود',
            'cta2_url' => '/login',
            'show_search' => false,
            'show_cart' => false,
            'show_account' => false,
            'header_order' => ['logo', 'menu', 'search', 'social', 'phone', 'cta', 'cart', 'account', 'dark_toggle', 'burger'],
        ];

        if ($skin === 'header_glass_nimbus') {
            return array_merge($base, [
                'bg_color' => 'rgba(255, 255, 255, 0.5)',
                'text_color' => '#211c48',
                'ink_color' => '#211c48',
                'cta_style' => 'gradient',
                'cta_color_start' => '#e20c35',
                'cta_color_end' => '#c4008d',
                'cta2_style' => 'subtle',
                'pointer' => 'pill',
                'backdrop_blur' => 16,
                'drawer_style' => 'popover',
                'sticky_offset' => 12,
            ]);
        }

        if ($skin === 'header_modern_v1') {
            return array_merge($base, [
                'bg_color' => '#ffffff',
                'text_color' => '#172033',
                'cta_style' => 'primary',
                'cta_color_start' => '#4f46e5',
                'cta_color_end' => '#4f46e5',
                'pointer' => 'underline',
                'backdrop_blur' => 0,
                'drawer_style' => 'side',
                'sticky_offset' => 0,
            ]);
        }

        // nav_header default
        return array_merge($base, [
            'bg_color' => '#ffffff',
            'text_color' => '#111827',
            'cta_style' => 'primary',
            'cta2_style' => 'outline',
            'pointer' => 'underline',
            'drawer_style' => 'side',
            'drawer_side' => 'start',
            'sticky_offset' => 0,
            'search_mode' => 'input',
        ]);
    }
}
