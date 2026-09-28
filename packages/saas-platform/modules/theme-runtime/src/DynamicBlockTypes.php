<?php

namespace SaaSPlatform\ThemeRuntime;

use SaaSPlatform\ThemeRuntime\Widget\WidgetConfig;

/** Phase 3C/M2 dynamic widgets registered through the existing host seam. */
final class DynamicBlockTypes
{
    private const SOURCE_FIELDS = [
        'source_type' => [
            'type' => 'select',
            'label' => 'نوع منبع',
            'options' => ['static' => 'ثابت', 'api' => 'API', 'internal' => 'داخلی'],
            'default' => 'static',
        ],
        'endpoint_url' => [
            'type' => 'string',
            'label' => 'نشانی یا منبع',
            'default' => '',
        ],
        'query_params' => [
            'type' => 'textarea',
            'label' => 'پارامترهای JSON',
            'default' => '{}',
        ],
    ];

    private const TYPES = [
        'dynamic_product_grid' => [
            'name' => 'شبکه محصولات پویا',
            'category' => 'dynamic',
            'icon' => 'grid',
            'schema' => self::SOURCE_FIELDS + [
                'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'محصولات منتخب'],
                'columns' => ['type' => 'select', 'label' => 'ستون‌ها', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '4'],
                'static_items' => ['type' => 'textarea', 'label' => 'داده ثابت JSON', 'default' => '[]'],
                'loading_text' => ['type' => 'string', 'label' => 'متن بارگذاری', 'default' => 'در حال بارگذاری محصولات…'],
                'empty_text' => ['type' => 'string', 'label' => 'متن حالت خالی', 'default' => 'محصولی یافت نشد.'],
                'error_text' => ['type' => 'string', 'label' => 'متن خطا', 'default' => 'دریافت محصولات ممکن نشد.'],
            ],
            'defaultProps' => [
                'source_type' => 'static', 'endpoint_url' => '', 'query_params' => '{}',
                'title' => 'محصولات منتخب', 'columns' => '4', 'static_items' => '[]',
                'loading_text' => 'در حال بارگذاری محصولات…', 'empty_text' => 'محصولی یافت نشد.',
                'error_text' => 'دریافت محصولات ممکن نشد.',
            ],
            'data_source' => [
                'property' => 'data_source',
                'modes' => ['static', 'internal'],
                'internal_sources' => ['products'],
                'query_keys' => ['category_id', 'collection_slug', 'limit', 'sort'],
            ],
        ],
        'dynamic_feed_slider' => [
            'name' => 'اسلایدر خوراک پویا',
            'category' => 'dynamic',
            'icon' => 'slides',
            'schema' => self::SOURCE_FIELDS + [
                'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'تازه‌ترین مطالب'],
                'static_items' => ['type' => 'textarea', 'label' => 'داده ثابت JSON', 'default' => '[]'],
                'show_controls' => ['type' => 'boolean', 'label' => 'نمایش کنترل‌ها', 'default' => true],
                'loading_text' => ['type' => 'string', 'label' => 'متن بارگذاری', 'default' => 'در حال دریافت محتوا…'],
                'empty_text' => ['type' => 'string', 'label' => 'متن حالت خالی', 'default' => 'محتوایی یافت نشد.'],
                'error_text' => ['type' => 'string', 'label' => 'متن خطا', 'default' => 'دریافت محتوا ممکن نشد.'],
            ],
            'defaultProps' => [
                'source_type' => 'static', 'endpoint_url' => '', 'query_params' => '{}',
                'title' => 'تازه‌ترین مطالب', 'static_items' => '[]', 'show_controls' => true,
                'loading_text' => 'در حال دریافت محتوا…', 'empty_text' => 'محتوایی یافت نشد.',
                'error_text' => 'دریافت محتوا ممکن نشد.',
            ],
            'data_source' => [
                'property' => 'data_source',
                'modes' => ['static', 'api'],
            ],
        ],
    ];

    public static function register(): void
    {
        foreach (self::TYPES as $type => $definition) {
            BlockRegistry::registerHostType($type, WidgetConfig::normalize($type, $definition));
        }
    }
}
