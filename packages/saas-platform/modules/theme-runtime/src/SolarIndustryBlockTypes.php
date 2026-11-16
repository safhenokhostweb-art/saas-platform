<?php

namespace SaaSPlatform\ThemeRuntime;

use SaaSPlatform\ThemeRuntime\Widget\WidgetConfig;

/**
 * Solar Industry Theme-Scoped Widget Definitions.
 *
 * Exclusively scoped to the `solar-industry` template/theme.
 * Declares the `solar-energy-cycle` section: an animated energy-conversion
 * showcase (SVG/CSS particles, or a site-supplied Lottie/WebM file) with
 * counters, steps and a call to action, included with the theme.
 *
 * It is deliberately NOT a motion-library product. The licensed motion
 * `solar-motion-graphic` (saas-platform ADR-086: premium, private, delivered
 * only to granted sites through the Motion Graphics runtime manifest) is
 * placed with the `module:motion` widget, never through this block, and this
 * block never renders library motion assets. The distinct type id, name and
 * wording keep the two from being confused.
 */
final class SolarIndustryBlockTypes
{
    public const THEME_SCOPE = 'solar-industry';
    public const TYPE_ENERGY_CYCLE = 'solar-energy-cycle';

    public const CATEGORY_SOLAR = 'solar-industry';

    public const ANIMATION_MODES = [
        'svg_particle' => 'انیمیشن ذرات کوانتومی و فوتوالکتریک SVG/CSS (فوق‌العاده سبک و بهینه‌سازی‌شده)',
        'lottie' => 'انیمیشن برداری لوتی (Lottie JSON)',
        'webm_video' => 'ویدیوی شفاف و باکیفیت WebM',
    ];

    public const SPEED_OPTIONS = [
        '0.5x' => 'کند (۰.۵ برابر)',
        '1x' => 'استاندارد (۱ برابر)',
        '1.5x' => 'سریع (۱.۵ برابر)',
        '2x' => 'شتاب‌یافته (۲ برابر)',
    ];

    public const LOOP_OPTIONS = [
        'infinite' => 'حلقه نامحدود (پیوسته)',
        'once' => 'یک‌بار اجرا',
        'alternate' => 'رفت و برگشتی (پینگ‌پنگ)',
    ];

    public const COLOR_THEMES = [
        'dark-industrial' => 'صنعتی تیره (هماهنگ با تم تاریک صنعت برق)',
        'solar-gold' => 'طلایی خورشیدی (انرژی پرتوهای نور)',
        'eco-emerald' => 'زمردی پاک (توسعه پایدار و فناوری سبز)',
        'deep-cyan' => 'فیروزه‌ای الکتریکی (مدرن و های‌تک)',
        'custom' => 'پالت سفارشی مدیر',
    ];

    public const HEIGHT_OPTIONS = [
        'compact' => 'فشرده (۴۴۰ پیکسل)',
        'regular' => 'استاندارد (۵۶۰ پیکسل)',
        'tall' => 'بلند (۶۸۰ پیکسل)',
        'auto' => 'خودکار متناسب با نسبت تصویر',
    ];

    public const WIDTH_OPTIONS = [
        'contained' => 'کانتینر استاندارد (۱۲۰۰px)',
        'narrow' => 'جمع‌وجور (۹۶۰px)',
        'full' => 'تمام‌عرض صفحه (۱۰۰٪)',
    ];

    public const SCALE_OPTIONS = [
        '80%' => '۸۰٪',
        '90%' => '۹۰٪',
        '100%' => '۱۰۰٪ (عادی)',
        '110%' => '۱۱۰٪ (بزرگ)',
    ];

    public const ASPECT_RATIOS = [
        '16/9' => '۱۶:۹ (عریض استاندارد)',
        '21/9' => '۲۱:۹ (سینمایی اولتراواید)',
        '4/3' => '۴:۳ (کلاسیک)',
        'auto' => 'متناسب با محتوا',
    ];

    public const SPACING_OPTIONS = [
        'none' => 'بدون فاصله',
        'compact' => 'فشرده',
        'regular' => 'استاندارد',
        'large' => 'باز و وسیع',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        $text = fn (string $label, string $default = '', array $extra = []) => ['type' => 'string', 'label' => $label, 'default' => $default] + $extra;
        $area = fn (string $label, string $default = '', array $extra = []) => ['type' => 'textarea', 'label' => $label, 'default' => $default] + $extra;
        $link = fn (string $label, string $default = '#', array $extra = []) => ['type' => 'link', 'label' => $label, 'default' => $default] + $extra;
        $image = fn (string $label, string $default = '', array $extra = []) => ['type' => 'image', 'label' => $label, 'default' => $default] + $extra;
        $bool = fn (string $label, bool $default, array $extra = []) => ['type' => 'boolean', 'label' => $label, 'default' => $default] + $extra;
        $select = fn (string $label, array $options, string $default, array $extra = []) => ['type' => 'select', 'label' => $label, 'options' => $options, 'default' => $default] + $extra;
        $repeater = fn (string $label, string $itemLabel, array $fields, array $default, array $extra = []) => [
            'type' => 'repeater', 'label' => $label, 'item_label' => $itemLabel, 'storage' => 'array', 'fields' => $fields, 'default' => $default,
        ] + $extra;

        $stepFields = [
            'title' => $text('عنوان مرحله', 'تابش فوتون‌ها'),
            'step_num' => $text('شماره مرحله', '۱'),
<<<<<<< HEAD
            // The shared icon picker (catalog names); the renderer still draws
            // the legacy select values (battery, grid) saved before this change.
            'icon' => ['type' => 'icon', 'label' => 'آیکن مرحله', 'default' => 'sun'],
=======
            'icon' => $select('آیکن مرحله', [
                'sun' => 'خورشید و پرتو نور',
                'zap' => 'الکتریسیته و بار فوتوالکتریک',
                'cpu' => 'اینورتر و پردازش هوشمند',
                'battery' => 'باتری و ذخیره‌ساز توان',
                'grid' => 'شبکه انتقال سراسری',
                'shield' => 'حفاظت و راندمان',
            ], 'sun'),
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
            'desc' => $area('توضیح کوتاه مرحله', 'جذب فوتون‌های نور توسط لایه‌های سیلیکونی کریستالی پنل.'),
            'badge' => $text('برچسب نشان مرحله', 'جذب تابش ۹۸٪'),
            'tooltip' => $text('متن راهنمای جزئیات (تول‌تیپ)', 'طیف نور با طول موج ۴۰۰ تا ۷۰۰ نانومتر'),
        ];

        $defaultSteps = [
            [
                'title' => 'تابش فوتون‌های خورشیدی',
                'step_num' => '۱',
                'icon' => 'sun',
                'desc' => 'برخورد فوتون‌های پرتو خورشید با لایه‌های سیلیکون مونوکریستال با بیشترین زاویه جذب تابش.',
                'badge' => 'جذب ۹۸.۵٪ تابش',
                'tooltip' => 'جذب امواج نوری در طیف مرئی و تبدیل آنی به حامل‌های بار الکتریکی',
            ],
            [
                'title' => 'سیلان الکترون‌ها (جریان DC)',
                'step_num' => '۲',
                'icon' => 'zap',
                'desc' => 'برانگیختگی الکترون‌ها در پیوند P-N و انتقال ولتاژ و جریان پیوسته از طریق باس‌بارهای سلول.',
                'badge' => 'جریان مستقیم DC',
                'tooltip' => 'ولتاژ بهینه هر استرینگ در محدوده ۳۸ تا ۴۸ ولت مستقیم',
            ],
            [
                'title' => 'تبدیل و اینورتر MPPT',
                'step_num' => '۳',
                'icon' => 'cpu',
                'desc' => 'ردیابی خودکار نقطه حداکثر توان و تبدیل جریان DC به موج سینوسی خالص متناوب AC ۵۰ هرتز.',
                'badge' => 'راندمان اینورتر ۹۸.۸٪',
                'tooltip' => 'الگوریتم تطبیق امپدانس هوشمند با حداقل تلفات هارمونیک',
            ],
            [
                'title' => 'تغذیه بار و ذخیره در باتری',
                'step_num' => '۴',
<<<<<<< HEAD
                'icon' => 'battery-charging',
=======
                'icon' => 'battery',
>>>>>>> cf756c77 (feat(saas): finalize uncommitted local changes for solar widgets, scroll-to-top, and global assets)
                'desc' => 'تأمین مصرف تجهیزات خانگی و صنعتی، شارژ سریع باتری‌های لیتیومی و تزریق مازاد به شبکه.',
                'badge' => 'شبکه هوشمند + باتری',
                'tooltip' => 'مدیریت اولویت تغذیه بار بدون قطعی حتی در شرایط ابری',
            ],
        ];

        $definitions = [
            self::TYPE_ENERGY_CYCLE => [
                'name' => 'بخش چرخه انرژی خورشیدی',
                'category' => self::CATEGORY_SOLAR,
                'theme_scope' => self::THEME_SCOPE,
                'icon' => 'zap',
                'tags' => ['خورشیدی', 'چرخه انرژی', 'انیمیشن', 'انرژی', 'اینورتر', 'پنل', 'صنعت برق'],
                'capabilities' => [
                    'is_container' => false,
                    'lockedStructure' => false,
                    'allowCustomCss' => true,
                    'tokenizable' => true,
                    'resizable' => true,
                    'reorderable' => true,
                    'duplicatable' => true,
                ],
                'schema' => [
                    // ── Content Panel: Mode & Sources ──
                    'mode' => $select('حالت نمایش انیمیشن', self::ANIMATION_MODES, 'svg_particle', ['panel' => 'content', 'group' => 'موتور انیمیشن']),
                    'lottie_url' => $text('نشانی فایل انیمیشن Lottie (JSON)', '', ['panel' => 'content', 'group' => 'موتور انیمیشن', 'help' => 'فایل JSON انیمیشن لوتی بارگذاری‌شده در سیستم یا لینک مستقیم']),
                    'video_webm_url' => $text('نشانی ویدیوی ترنسپرنت WebM', '', ['panel' => 'content', 'group' => 'موتور انیمیشن', 'help' => 'فایل ویدیویی سبک با زمینه شفاف']),
                    'video_mp4_fallback_url' => $text('نشانی ویدیوی پشتیبان MP4', '', ['panel' => 'content', 'group' => 'موتور انیمیشن', 'help' => 'برای پشتیبانی از مرورگرهایی که WebM شفاف ندارند']),
                    'poster_image' => $image('تصویر پیش‌نمایش / پوستر', '/storage/solar-assets/solar-panel-grid.jpg', ['panel' => 'content', 'group' => 'موتور انیمیشن']),

                    // ── Content Panel: Persian Text Layers ──
                    'eyebrow' => $text('برچسب بالای عنوان (Eyebrow)', 'فناوری پیشرفته تبدیل فوتوالکتریک', ['panel' => 'content', 'group' => 'متن‌ها و لایه‌های فارسی']),
                    'title' => $text('عنوان اصلی بخش', 'چرخه هوشمند تبدیل و ذخیره‌سازی انرژی پاک', ['panel' => 'content', 'group' => 'متن‌ها و لایه‌های فارسی']),
                    'subtitle' => $area('توضیحات و پاراگراف معرفی', 'جریان پیوسته فوتون‌های نور، برانگیختگی الکترون‌ها در سلول‌های فتوولتائیک، بهینه‌سازی توان توسط اینورتر هوشمند و ذخیره‌سازی پایدار در باتری‌های مدرن.', ['panel' => 'content', 'group' => 'متن‌ها و لایه‌های فارسی']),
                    'badge_label' => $text('عنوان نشان وضعیت زنده', 'راندمان فعال ۲۳.۵٪', ['panel' => 'content', 'group' => 'متن‌ها و لایه‌های فارسی']),
                    'badge_icon' => $select('آیکن نشان وضعیت', ['zap' => 'برق (Zap)', 'sun' => 'خورشید (Sun)', 'shield' => 'سپر (Shield)', 'star' => 'ستاره (Star)'], 'zap', ['panel' => 'content', 'group' => 'متن‌ها و لایه‌های فارسی']),
                    'show_cta' => $bool('نمایش دکمه اقدام / محاسبه‌گر', true, ['panel' => 'content', 'group' => 'دکمه اقدام']),
                    'cta_label' => $text('متن دکمه اقدام', 'محاسبه تولید و ظرفیت خورشیدی', ['panel' => 'content', 'group' => 'دکمه اقدام']),
                    'cta_url' => $link('پیوند دکمه اقدام', '/page/solar_calculator', ['panel' => 'content', 'group' => 'دکمه اقدام']),

                    // ── Content Panel: Live Counters ──
                    'show_counters' => $bool('نمایش نوار آمارهای لحظه‌ای و کنتورها', true, ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'persian_digits' => $bool('تبدیل اعداد و ارقام به رسم‌الخط فارسی', true, ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_power_value' => $text('توان تولید لحظه‌ای (مقدار)', '۸.۴', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_power_unit' => $text('واحد توان خروجی', 'کیلووات', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_power_label' => $text('عنوان توان لحظه‌ای', 'تولید برق هم‌اکنون', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_efficiency_value' => $text('راندمان پنل‌ها (مقدار)', '۲۳.۵', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_efficiency_unit' => $text('واحد راندمان', 'درصد', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_efficiency_label' => $text('عنوان راندمان ماژول', 'بازدهی فتوولتائیک', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_co2_value' => $text('صرفه‌جویی کربن (مقدار)', '۴.۲', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_co2_unit' => $text('واحد کاهش کربن', 'تن در سال', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_co2_label' => $text('عنوان کاهش آلایندگی', 'جلوگیری از انتشار CO₂', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_daily_value' => $text('انرژی تولیدی امروز (مقدار)', '۳۸.۶', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_daily_unit' => $text('واحد انرژی روزانه', 'کیلووات‌ساعت', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),
                    'counter_daily_label' => $text('عنوان انرژی تجمعی روز', 'تولید تجمعی امروز', ['panel' => 'content', 'group' => 'کنتورها و داده‌های زنده']),

                    // ── Content Panel: Conversion Steps ──
                    'show_steps' => $bool('نمایش مراحل چهارگانه چرخه تبدیل انرژی', true, ['panel' => 'content', 'group' => 'مراحل و تول‌تیپ‌ها']),
                    'steps' => $repeater('مراحل چرخه انرژی', 'مرحله', $stepFields, $defaultSteps, ['panel' => 'content', 'group' => 'مراحل و تول‌تیپ‌ها']),

                    // ── Style Panel: Animation & Playback Timing ──
                    'animation_speed' => $select('سرعت پخش انیمیشن', self::SPEED_OPTIONS, '1x', ['panel' => 'style', 'group' => 'زمان‌بندی و رفتار انیمیشن', 'ui' => 'segmented']),
                    'animation_duration' => $text('مدت چرخه کامل به ثانیه', '4', ['panel' => 'style', 'group' => 'زمان‌بندی و رفتار انیمیشن']),
                    'loop_behavior' => $select('رفتار تکرار حلقه (Loop)', self::LOOP_OPTIONS, 'infinite', ['panel' => 'style', 'group' => 'زمان‌بندی و رفتار انیمیشن']),
                    'autoplay' => $bool('پخش خودکار بدون نیاز به کلیک', true, ['panel' => 'style', 'group' => 'زمان‌بندی و رفتار انیمیشن']),
                    'pause_on_hover' => $bool('توقف ملایم انیمیشن هنگام قرار گرفتن ماوس', true, ['panel' => 'style', 'group' => 'زمان‌بندی و رفتار انیمیشن']),

                    // ── Style Panel: Dimensions & Container Scale ──
                    'container_height' => $select('ارتفاع کانتینر بخش', self::HEIGHT_OPTIONS, 'regular', ['panel' => 'style', 'group' => 'ابعاد و مقیاس']),
                    'max_width' => $select('بیشینه عرض کادر', self::WIDTH_OPTIONS, 'contained', ['panel' => 'style', 'group' => 'ابعاد و مقیاس']),
                    'scale' => $select('مقیاس اندازه المان‌های گرافیکی', self::SCALE_OPTIONS, '100%', ['panel' => 'style', 'group' => 'ابعاد و مقیاس']),
                    'aspect_ratio' => $select('نسبت تصویر امن (جلوگیری از پرش چیدمان CLS)', self::ASPECT_RATIOS, '16/9', ['panel' => 'style', 'group' => 'ابعاد و مقیاس']),
                    'section_spacing' => $select('فاصله عمودی سکشن', self::SPACING_OPTIONS, 'regular', ['panel' => 'style', 'group' => 'ابعاد و مقیاس', 'ui' => 'segmented']),

                    // ── Style Panel: Color Themes & Glow ──
                    'color_theme' => $select('تم رنگی اختصاصی انیمیشن', self::COLOR_THEMES, 'dark-industrial', ['panel' => 'style', 'group' => 'رنگ‌بندی و درخشش نور']),
                    'glow_effect' => $bool('افکت درخشش نئونی و هاله تابش انرژی (Glow)', true, ['panel' => 'style', 'group' => 'رنگ‌بندی و درخشش نور']),
                    'custom_accent_color' => $text('رنگ اختصاصی نور و پرتوها (هگز یا توکن)', '#f59e0b', ['panel' => 'style', 'group' => 'پالت سفارشی مدیر']),
                    'custom_panel_color' => $text('رنگ زمینه ماژول پنل خورشیدی', '#0f172a', ['panel' => 'style', 'group' => 'پالت سفارشی مدیر']),
                    'custom_particle_color' => $text('رنگ ذرات جریان فوتوالکتریک', '#38bdf8', ['panel' => 'style', 'group' => 'پالت سفارشی مدیر']),
                ],
                'defaultProps' => [
                    'mode' => 'svg_particle',
                    'lottie_url' => '',
                    'video_webm_url' => '',
                    'video_mp4_fallback_url' => '',
                    'poster_image' => '/storage/solar-assets/solar-panel-grid.jpg',
                    'eyebrow' => 'فناوری پیشرفته تبدیل فوتوالکتریک',
                    'title' => 'چرخه هوشمند تبدیل و ذخیره‌سازی انرژی پاک',
                    'subtitle' => 'جریان پیوسته فوتون‌های نور، برانگیختگی الکترون‌ها در سلول‌های فتوولتائیک، بهینه‌سازی توان توسط اینورتر هوشمند و ذخیره‌سازی پایدار در باتری‌های مدرن.',
                    'badge_label' => 'راندمان فعال ۲۳.۵٪',
                    'badge_icon' => 'zap',
                    'show_cta' => true,
                    'cta_label' => 'محاسبه تولید و ظرفیت خورشیدی',
                    'cta_url' => '/page/solar_calculator',
                    'show_counters' => true,
                    'persian_digits' => true,
                    'counter_power_value' => '۸.۴',
                    'counter_power_unit' => 'کیلووات',
                    'counter_power_label' => 'تولید برق هم‌اکنون',
                    'counter_efficiency_value' => '۲۳.۵',
                    'counter_efficiency_unit' => 'درصد',
                    'counter_efficiency_label' => 'بازدهی فتوولتائیک',
                    'counter_co2_value' => '۴.۲',
                    'counter_co2_unit' => 'تن در سال',
                    'counter_co2_label' => 'جلوگیری از انتشار CO₂',
                    'counter_daily_value' => '۳۸.۶',
                    'counter_daily_unit' => 'کیلووات‌ساعت',
                    'counter_daily_label' => 'تولید تجمعی امروز',
                    'show_steps' => true,
                    'steps' => $defaultSteps,
                    'animation_speed' => '1x',
                    'animation_duration' => '4',
                    'loop_behavior' => 'infinite',
                    'autoplay' => true,
                    'pause_on_hover' => true,
                    'container_height' => 'regular',
                    'max_width' => 'contained',
                    'scale' => '100%',
                    'aspect_ratio' => '16/9',
                    'section_spacing' => 'regular',
                    'color_theme' => 'dark-industrial',
                    'glow_effect' => true,
                    'custom_accent_color' => '#f59e0b',
                    'custom_panel_color' => '#0f172a',
                    'custom_particle_color' => '#38bdf8',
                ],
            ],
        ];

        foreach ($definitions as $type => &$definition) {
            $definition = WidgetConfig::normalize($type, $definition);
        }

        return $definitions;
    }

    /**
     * Registers the solar-industry theme blocks into the BlockRegistry.
     * Idempotent: safe to run multiple times in persistent processes.
     */
    public static function register(): void
    {
        foreach (self::definitions() as $type => $definition) {
            BlockRegistry::registerHostType($type, $definition);
        }
    }
}
