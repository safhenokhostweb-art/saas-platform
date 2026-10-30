<?php

namespace SaaSPlatform\ThemeRuntime;

use SaaSPlatform\ThemeRuntime\Widget\WidgetConfig;

/** Phase 3C/M3 interactive widgets registered through the existing host seam. */
final class InteractionBlockTypes
{
    private const TYPES = [
        'interactive_form' => [
            'name' => 'فرم تعاملی',
            'category' => 'forms',
            'icon' => 'form',
            'schema' => [
                'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'فرم تماس'],
                'submit_label' => ['type' => 'string', 'label' => 'متن دکمه ارسال', 'default' => 'ارسال'],
                'endpoint_url' => ['type' => 'link', 'label' => 'نشانی API', 'default' => ''],
                'success_message' => ['type' => 'string', 'label' => 'پیام موفقیت', 'default' => 'با موفقیت ارسال شد.'],
                'error_message' => ['type' => 'string', 'label' => 'پیام خطا', 'default' => 'خطا در ارسال. دوباره تلاش کنید.'],
            ],
            'defaultProps' => [
                'title' => 'فرم تماس',
                'submit_label' => 'ارسال',
                'endpoint_url' => '',
                'success_message' => 'با موفقیت ارسال شد.',
                'error_message' => 'خطا در ارسال. دوباره تلاش کنید.',
            ],
            'interaction_config' => [
                'property' => 'interaction_config',
                'events' => ['submit'],
                'actions' => ['call_api'],
            ],
        ],
        'action_button' => [
            'name' => 'دکمه عملیات',
            'category' => 'content',
            'icon' => 'button',
            'schema' => [
                'label' => ['type' => 'string', 'label' => 'برچسب دکمه', 'default' => 'دکمه'],
                'action_type' => [
                    'type' => 'select',
                    'label' => 'نوع عملیات',
                    'options' => ['link' => 'لینک', 'api_call' => 'فراخوانی API', 'scroll_to' => 'اسکرول به المان'],
                    'default' => 'link',
                ],
                'target_value' => ['type' => 'string', 'label' => 'مقدار مقصد (URL یا انتخاب‌گر)', 'default' => '#'],
                'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['primary' => 'اصلی', 'secondary' => 'ثانویه', 'outline' => 'خط‌دار', 'ghost' => 'شفاف'], 'default' => 'primary'],
                'icon' => ['type' => 'icon', 'label' => 'آیکون (اختیاری)', 'default' => ''],
            ],
            'defaultProps' => [
                'label' => 'دکمه',
                'action_type' => 'link',
                'target_value' => '#',
                'style' => 'primary',
                'icon' => '',
            ],
            'interaction_config' => [
                'property' => 'interaction_config',
                'events' => ['click'],
                'actions' => ['navigate', 'call_api', 'scroll_to'],
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