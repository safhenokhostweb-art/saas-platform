<?php

namespace SaaSPlatform\ThemeRuntime;

use SaaSPlatform\ThemeRuntime\Widget\WidgetConfig;

/**
 * PLATFORM-THEME-SPEC-v1 (ADR-083): seven brand-neutral commercial widgets,
 * registered through the same host seam as DynamicBlockTypes so both hosts
 * get them from this package alone. They render in CommercialBlockRendererTrait.
 *
 * Neutral means: palette categories (never a theme-pack category), no colour
 * props (every colour is a `--tb-*` token in the shared sheet), and defaults
 * written for any business. Brand copy belongs in templates, not here.
 *
 * Spacing follows the zero-gap rule (2026-11-09/10): `section_spacing`
 * defaults to `none`; a template opts into `regular`.
 *
 * The four list widgets declare the Phase 3C `data_source` contract. Their
 * manual repeater is the static source, so a page never depends on a
 * resolver being wired to show its authored content.
 */
final class CommercialBlockTypes
{
    public const TYPES = [
        'hero_with_stats', 'zigzag_rows', 'target_audience_grid', 'event_calendar',
        'dual_sale_product_grid', 'course_video_filter', 'downloadable_assets',
    ];

    public const EVENT_STATUSES = [
        'registering' => 'در حال ثبت‌نام',
        'running' => 'در حال برگزاری',
        'finished' => 'خاتمه‌یافته',
    ];

    /** Week order of a Persian calendar, Saturday first. Named keys: 0–6 would collapse into a list. */
    public const WEEKDAYS = ['sat' => 'شنبه', 'sun' => 'یکشنبه', 'mon' => 'دوشنبه', 'tue' => 'سه‌شنبه', 'wed' => 'چهارشنبه', 'thu' => 'پنجشنبه', 'fri' => 'جمعه'];

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        $text = fn (string $label, string $default = '', array $extra = []) => ['type' => 'string', 'label' => $label, 'default' => $default] + $extra;
        $area = fn (string $label, string $default = '', array $extra = []) => ['type' => 'textarea', 'label' => $label, 'default' => $default] + $extra;
        $link = fn (string $label, string $default = '#', array $extra = []) => ['type' => 'link', 'label' => $label, 'default' => $default] + $extra;
        $image = fn (string $label) => ['type' => 'image', 'label' => $label, 'default' => ''];
        $icon = fn (string $label, string $default = '') => ['type' => 'icon', 'label' => $label, 'default' => $default];
        $bool = fn (string $label, bool $default) => ['type' => 'boolean', 'label' => $label, 'default' => $default];
        $select = fn (string $label, array $options, string $default, array $extra = []) => ['type' => 'select', 'label' => $label, 'options' => $options, 'default' => $default] + $extra;
        $repeater = fn (string $label, string $itemLabel, array $fields, array $default) => [
            'type' => 'repeater', 'label' => $label, 'item_label' => $itemLabel, 'storage' => 'array', 'fields' => $fields, 'default' => $default,
        ];

        $spacing = $select('فاصلهٔ عمودی بخش', ['none' => 'بدون فاصله', 'compact' => 'فشرده', 'regular' => 'استاندارد'], 'none', ['ui' => 'segmented', 'panel' => 'style', 'group' => 'فاصله‌گذاری']);
        $heading = [
            'eyebrow' => $text('برچسب بالای عنوان'),
            'title' => $text('عنوان بخش'),
            'subtitle' => $area('توضیح بخش'),
        ];
        $source = fn (array $modes) => [
            'source_type' => $select('منبع داده', array_intersect_key(['static' => 'آیتم‌های دستی همین بخش', 'internal' => 'داده‌های سایت'], array_flip($modes)), 'static', ['panel' => 'advanced', 'group' => 'منبع داده']),
            'endpoint_url' => $text('شناسهٔ منبع داخلی', '', ['panel' => 'advanced', 'group' => 'منبع داده', 'condition' => ['source_type' => 'internal']]),
            'query_params' => $area('پارامترهای JSON', '{}', ['panel' => 'advanced', 'group' => 'منبع داده', 'condition' => ['source_type' => 'internal']]),
        ];
        $sourceDefaults = ['source_type' => 'static', 'endpoint_url' => '', 'query_params' => '{}'];
        $dataSource = fn (array $sources, array $queryKeys) => [
            'property' => 'data_source', 'modes' => ['static', 'internal'], 'manual_prop' => 'items',
            'internal_sources' => $sources, 'query_keys' => $queryKeys,
        ];

        $stats = [
            ['value' => '+۷', 'label' => 'سال تجربه'],
            ['value' => '+۱۲۰', 'label' => 'پروژهٔ اجراشده'],
            ['value' => '+۱۵', 'label' => 'متخصص و مهندس'],
            ['value' => '٪۹۸', 'label' => 'رضایت مشتریان'],
        ];
        $rows = [
            ['number' => '۰۱', 'icon' => 'target', 'title' => 'بررسی نیاز', 'text' => 'نیاز، شرایط محل و بودجهٔ شما را پیش از هر پیشنهادی دقیق بررسی می‌کنیم.', 'image' => '', 'image_alt' => '', 'link_label' => '', 'link_url' => '#'],
            ['number' => '۰۲', 'icon' => 'pen-tool', 'title' => 'طراحی و برنامه‌ریزی', 'text' => 'راهکار متناسب با هدف شما طراحی و زمان‌بندی اجرا شفاف اعلام می‌شود.', 'image' => '', 'image_alt' => '', 'link_label' => '', 'link_url' => '#'],
            ['number' => '۰۳', 'icon' => 'wrench', 'title' => 'اجرا', 'text' => 'تیم خودمان کار را طبق استاندارد اجرا و تحویل می‌دهد.', 'image' => '', 'image_alt' => '', 'link_label' => '', 'link_url' => '#'],
            ['number' => '۰۴', 'icon' => 'headset', 'title' => 'پشتیبانی', 'text' => 'پس از تحویل هم کنار شما هستیم؛ بازدید دوره‌ای و پاسخ سریع.', 'image' => '', 'image_alt' => '', 'link_label' => '', 'link_url' => '#'],
        ];
        $audiences = [
            ['icon' => 'house', 'title' => 'مشتریان خانگی', 'text' => 'راهکارهای ساده و مقرون‌به‌صرفه برای خانه و ویلا.', 'link_label' => 'اطلاعات بیشتر', 'link_url' => '#'],
            ['icon' => 'building-2', 'title' => 'کسب‌وکارها', 'text' => 'کاهش هزینه و افزایش پایداری برای فروشگاه و دفتر کار.', 'link_label' => 'اطلاعات بیشتر', 'link_url' => '#'],
            ['icon' => 'cpu', 'title' => 'صنایع', 'text' => 'طراحی مهندسی برای مصرف بالا و شرایط کاری سخت.', 'link_label' => 'اطلاعات بیشتر', 'link_url' => '#'],
        ];
        $events = [
            ['title' => 'دورهٔ مقدماتی', 'day' => 7, 'date_label' => '۷ تیر', 'end_label' => '۱۸ تیر', 'hours' => '۴ ساعت', 'status' => 'registering', 'url' => '#'],
            ['title' => 'کارگاه تخصصی', 'day' => 16, 'date_label' => '۱۶ تیر', 'end_label' => '۱۶ تیر', 'hours' => '۳ ساعت', 'status' => 'running', 'url' => '#'],
            ['title' => 'دورهٔ پیشرفته', 'day' => 26, 'date_label' => '۲۶ تیر', 'end_label' => '۷ مرداد', 'hours' => '۸ ساعت', 'status' => 'registering', 'url' => '#'],
        ];
        $products = [
            ['product_id' => '', 'title' => 'محصول نمونهٔ اول', 'image' => '', 'image_alt' => '', 'badge' => 'پرفروش', 'price' => '۱۲٬۵۰۰٬۰۰۰ تومان', 'old_price' => '', 'meta' => 'ارسال سریع', 'url' => '#'],
            ['product_id' => '', 'title' => 'محصول نمونهٔ دوم', 'image' => '', 'image_alt' => '', 'badge' => 'جدید', 'price' => '۸٬۹۰۰٬۰۰۰ تومان', 'old_price' => '۹٬۸۰۰٬۰۰۰ تومان', 'meta' => 'گارانتی معتبر', 'url' => '#'],
        ];
        $courses = [
            ['title' => 'آشنایی با مبانی', 'url' => '#', 'image' => '', 'image_alt' => '', 'access' => 'free', 'start_label' => '۷ تیر ۱۴۰۵', 'end_label' => '', 'duration' => '۱ ساعت', 'popularity' => 40, 'published' => '1405-04-07'],
            ['title' => 'دورهٔ تخصصی اجرا', 'url' => '#', 'image' => '', 'image_alt' => '', 'access' => 'paid', 'start_label' => '۲۶ تیر ۱۴۰۵', 'end_label' => '۷ مرداد ۱۴۰۵', 'duration' => '۸ ساعت', 'popularity' => 85, 'published' => '1405-04-26'],
            ['title' => 'عیب‌یابی پیشرفته', 'url' => '#', 'image' => '', 'image_alt' => '', 'access' => 'paid', 'start_label' => '۱۰ مرداد ۱۴۰۵', 'end_label' => '۲۲ مرداد ۱۴۰۵', 'duration' => '۶ ساعت', 'popularity' => 60, 'published' => '1405-05-10'],
        ];
        $assets = [
            ['title' => 'کاتالوگ محصولات', 'file_url' => '#', 'size_label' => '۳ مگابایت', 'file_type' => 'pdf', 'description' => ''],
            ['title' => 'راهنمای نصب', 'file_url' => '#', 'size_label' => '۶۷۰ کیلوبایت', 'file_type' => 'pdf', 'description' => ''],
        ];

        $definitions = [
            'hero_with_stats' => [
                'name' => 'هیرو با آمار',
                'category' => BlockRegistry::CATEGORY_MARKETING,
                'icon' => 'stats',
                'schema' => $heading + [
                    'highlight' => $text('بخش برجستهٔ عنوان (با رنگ اصلی)'),
                    'tagline' => $text('شعار کوتاه زیر عنوان'),
                    'primary_label' => $text('متن دکمهٔ اصلی', 'درخواست مشاوره'),
                    'primary_url' => $link('لینک دکمهٔ اصلی', '/contact'),
                    'secondary_label' => $text('متن دکمهٔ دوم'),
                    'secondary_url' => $link('لینک دکمهٔ دوم', '#', ['condition' => ['secondary_label!' => '']]),
                    'image' => $image('تصویر'),
                    'image_alt' => $text('متن جایگزین تصویر'),
                    'layout' => $select('چیدمان', ['split' => 'دو ستونه (متن و تصویر)', 'centered' => 'وسط‌چین'], 'split', ['ui' => 'segmented']),
                    'stats' => $repeater('آمار', 'آمار', ['value' => $text('عدد'), 'label' => $text('عنوان')], $stats),
                    'section_spacing' => $spacing,
                ],
                'defaultProps' => [
                    'eyebrow' => 'تخصص، شفافیت، تعهد', 'title' => 'راهکاری که برای نیاز واقعی شما', 'highlight' => 'طراحی می‌شود',
                    'tagline' => '', 'subtitle' => 'از بررسی نیاز تا اجرا و پشتیبانی، همهٔ مراحل را با یک تیم و یک مسئول پیش می‌بریم.',
                    'primary_label' => 'درخواست مشاوره', 'primary_url' => '/contact', 'secondary_label' => '', 'secondary_url' => '#',
                    'image' => '', 'image_alt' => '', 'layout' => 'split', 'stats' => $stats, 'section_spacing' => 'none',
                ],
            ],
            'zigzag_rows' => [
                'name' => 'ردیف‌های زیگزاگ و مراحل',
                'category' => BlockRegistry::CATEGORY_CONTENT,
                'icon' => 'steps',
                'schema' => $heading + [
                    'variant' => $select('نمایش', ['zigzag' => 'ردیف‌های یک‌درمیان', 'steps' => 'مراحل شماره‌دار'], 'zigzag', ['ui' => 'segmented']),
                    'start_side' => $select('تصویر ردیف اول', ['start' => 'راست', 'end' => 'چپ'], 'start', ['ui' => 'segmented', 'condition' => ['variant' => 'zigzag']]),
                    'rows' => $repeater('ردیف‌ها', 'ردیف', [
                        'number' => $text('شماره یا برچسب'), 'icon' => $icon('آیکون'), 'title' => $text('عنوان'), 'text' => $area('متن'),
                        'image' => $image('تصویر (اختیاری)'), 'image_alt' => $text('متن جایگزین تصویر'),
                        'link_label' => $text('متن لینک (اختیاری)'), 'link_url' => $link('لینک'),
                    ], $rows),
                    'section_spacing' => $spacing,
                ],
                'defaultProps' => ['eyebrow' => '', 'title' => 'رویکرد ما در اجرای پروژه‌ها', 'subtitle' => '', 'variant' => 'steps', 'start_side' => 'start', 'rows' => $rows, 'section_spacing' => 'none'],
            ],
            'target_audience_grid' => [
                'name' => 'شبکهٔ مخاطبان و خدمات',
                'category' => BlockRegistry::CATEGORY_CONTENT,
                'icon' => 'services-cards',
                'schema' => $heading + [
                    'columns' => $select('ستون‌ها در دسکتاپ', ['2' => '۲', '3' => '۳', '4' => '۴'], '3', ['ui' => 'column_picker']),
                    'card_align' => $select('چینش متن کارت', ['center' => 'وسط', 'start' => 'راست'], 'center', ['ui' => 'segmented']),
                    'items' => $repeater('کارت‌ها', 'کارت', [
                        'icon' => $icon('آیکون', 'sparkles'), 'title' => $text('عنوان'), 'text' => $area('توضیح'),
                        'link_label' => $text('متن دکمه (اختیاری)'), 'link_url' => $link('لینک'),
                    ], $audiences),
                    'section_spacing' => $spacing,
                ],
                'defaultProps' => ['eyebrow' => '', 'title' => 'برای چه کسانی کار می‌کنیم', 'subtitle' => '', 'columns' => '3', 'card_align' => 'center', 'items' => $audiences, 'section_spacing' => 'none'],
            ],
            'event_calendar' => [
                'name' => 'تقویم رویدادها و دوره‌ها',
                'category' => BlockRegistry::CATEGORY_DYNAMIC,
                'icon' => 'timeline',
                'schema' => $source(['static', 'internal']) + $heading + [
                    'month_label' => $text('عنوان ماه', 'تیر ۱۴۰۵'),
                    'days_in_month' => ['type' => 'number', 'label' => 'تعداد روزهای ماه', 'default' => 31, 'min' => 29, 'max' => 31, 'step' => 1],
                    'first_weekday' => $select('روز اول ماه', self::WEEKDAYS, 'sat'),
                    'show_legend' => $bool('نمایش راهنمای وضعیت', true),
                    'items' => $repeater('رویدادها', 'رویداد', [
                        'title' => $text('عنوان'), 'day' => ['type' => 'number', 'label' => 'روز شروع در ماه', 'default' => 1, 'min' => 1, 'max' => 31, 'step' => 1],
                        'date_label' => $text('تاریخ شروع'), 'end_label' => $text('تاریخ پایان'), 'hours' => $text('مدت'),
                        'status' => $select('وضعیت', self::EVENT_STATUSES, 'registering'), 'url' => $link('لینک صفحهٔ رویداد'),
                    ], $events),
                    'empty_text' => $text('متن حالت خالی', 'رویدادی برای این ماه ثبت نشده است.'),
                    'section_spacing' => $spacing,
                ],
                'defaultProps' => $sourceDefaults + ['eyebrow' => '', 'title' => 'تقویم آموزشی', 'subtitle' => '', 'month_label' => 'تیر ۱۴۰۵', 'days_in_month' => 31, 'first_weekday' => 'mon', 'show_legend' => true, 'items' => $events, 'empty_text' => 'رویدادی برای این ماه ثبت نشده است.', 'section_spacing' => 'none'],
                'data_source' => $dataSource(['courses', 'events'], ['month', 'status', 'limit']),
            ],
            'dual_sale_product_grid' => [
                'name' => 'شبکهٔ محصولات با خرید دوگانه',
                // WidgetDefinition knows 'commerce', the registry 'ecommerce';
                // a data-source widget passes both only as 'dynamic', like
                // dynamic_product_grid.
                'category' => BlockRegistry::CATEGORY_DYNAMIC,
                'icon' => 'products',
                'schema' => $source(['static', 'internal']) + $heading + [
                    'columns' => $select('ستون‌ها در دسکتاپ', ['2' => '۲', '3' => '۳', '4' => '۴'], '2', ['ui' => 'column_picker']),
                    'buy_label' => $text('متن دکمهٔ خرید (وقتی سبد خرید فعال نیست)', 'خرید'),
                    'details_label' => $text('متن دکمهٔ جزئیات', 'جزئیات'),
                    'view_all_label' => $text('متن مشاهدهٔ همه'),
                    'view_all_url' => $link('لینک مشاهدهٔ همه', '/shop', ['condition' => ['view_all_label!' => '']]),
                    'items' => $repeater('محصولات دستی', 'محصول', [
                        'product_id' => $text('شناسهٔ محصول در فروشگاه (برای افزودن مستقیم به سبد)'),
                        'title' => $text('نام محصول'), 'image' => $image('تصویر'), 'image_alt' => $text('متن جایگزین تصویر'),
                        'badge' => $text('نشان'), 'price' => $text('قیمت'), 'old_price' => $text('قیمت پیش از تخفیف'),
                        'meta' => $text('ویژگی کوتاه'), 'url' => $link('لینک صفحهٔ محصول'),
                    ], $products),
                    'empty_text' => $text('متن حالت خالی', 'محصولی برای نمایش وجود ندارد.'),
                    'section_spacing' => $spacing,
                ],
                'defaultProps' => $sourceDefaults + ['eyebrow' => '', 'title' => 'محصولات پیشنهادی', 'subtitle' => '', 'columns' => '2', 'buy_label' => 'خرید', 'details_label' => 'جزئیات', 'view_all_label' => '', 'view_all_url' => '/shop', 'items' => $products, 'empty_text' => 'محصولی برای نمایش وجود ندارد.', 'section_spacing' => 'none'],
                'data_source' => $dataSource(['products'], ['category_id', 'collection_slug', 'limit', 'sort']),
            ],
            'course_video_filter' => [
                'name' => 'آموزش‌ها با فیلتر رایگان و هزینه‌دار',
                'category' => BlockRegistry::CATEGORY_DYNAMIC,
                'icon' => 'video',
                'schema' => $source(['static', 'internal']) + $heading + [
                    'columns' => $select('ستون‌ها در دسکتاپ', ['2' => '۲', '3' => '۳'], '3', ['ui' => 'column_picker']),
                    'show_filters' => $bool('نمایش فیلتر رایگان / هزینه‌دار', true),
                    'show_sort' => $bool('نمایش مرتب‌سازی', true),
                    'cta_label' => $text('متن لینک کارت', 'مشاهدهٔ صفحهٔ آموزش'),
                    'items' => $repeater('آموزش‌ها', 'آموزش', [
                        'title' => $text('عنوان'), 'url' => $link('لینک'), 'image' => $image('تصویر'), 'image_alt' => $text('متن جایگزین تصویر'),
                        'access' => $select('دسترسی', ['free' => 'رایگان', 'paid' => 'هزینه‌دار'], 'free'),
                        'start_label' => $text('شروع'), 'end_label' => $text('پایان'), 'duration' => $text('مدت'),
                        'popularity' => ['type' => 'number', 'label' => 'محبوبیت (برای مرتب‌سازی)', 'default' => 0, 'min' => 0, 'max' => 1000000, 'step' => 1],
                        'published' => $text('تاریخ انتشار برای مرتب‌سازی (مثل 1405-04-26)'),
                    ], $courses),
                    'empty_text' => $text('متن حالت خالی', 'آموزشی با این فیلتر وجود ندارد.'),
                    'section_spacing' => $spacing,
                ],
                'defaultProps' => $sourceDefaults + ['eyebrow' => '', 'title' => 'ویدئوهای آموزشی', 'subtitle' => '', 'columns' => '3', 'show_filters' => true, 'show_sort' => true, 'cta_label' => 'مشاهدهٔ صفحهٔ آموزش', 'items' => $courses, 'empty_text' => 'آموزشی با این فیلتر وجود ندارد.', 'section_spacing' => 'none'],
                'data_source' => $dataSource(['courses'], ['access', 'limit', 'sort']),
            ],
            'downloadable_assets' => [
                'name' => 'مرکز دانلود فایل‌ها',
                'category' => BlockRegistry::CATEGORY_CONTENT,
                'icon' => 'list',
                'schema' => $source(['static', 'internal']) + $heading + [
                    'columns' => $select('ستون‌ها در دسکتاپ', ['1' => '۱', '2' => '۲', '3' => '۳'], '2', ['ui' => 'column_picker']),
                    'button_label' => $text('متن دکمه', 'دانلود'),
                    'items' => $repeater('فایل‌ها', 'فایل', [
                        'title' => $text('عنوان فایل'), 'description' => $text('توضیح کوتاه'), 'file_url' => $link('نشانی فایل'),
                        'size_label' => $text('حجم'), 'file_type' => $select('نوع فایل', ['pdf' => 'PDF', 'doc' => 'سند', 'xls' => 'جدول', 'zip' => 'فشرده', 'other' => 'سایر'], 'pdf'),
                    ], $assets),
                    'empty_text' => $text('متن حالت خالی', 'فایلی برای دانلود وجود ندارد.'),
                    'section_spacing' => $spacing,
                ],
                'defaultProps' => $sourceDefaults + ['eyebrow' => '', 'title' => 'مرکز دانلود', 'subtitle' => '', 'columns' => '2', 'button_label' => 'دانلود', 'items' => $assets, 'empty_text' => 'فایلی برای دانلود وجود ندارد.', 'section_spacing' => 'none'],
                'data_source' => $dataSource(['downloads'], ['category', 'limit']),
            ],
        ];

        foreach ($definitions as $type => &$definition) {
            $definition = WidgetConfig::normalize($type, $definition);
        }

        return $definitions;
    }

    public static function register(): void
    {
        foreach (self::definitions() as $type => $definition) {
            BlockRegistry::registerHostType($type, $definition);
        }
    }
}
