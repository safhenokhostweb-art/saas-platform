<?php

return [
    'default' => env('SMS_PROVIDER', 'meli_payamak'),

    /*
    |--------------------------------------------------------------------------
    | اعتبارات و خط ارسال (مطابق سرویس انتخاب‌شده)
    | برای ملی‌پیامک: username, password و از پنل خط ارسال (مثلاً 5000xxxx)
    | برای کاوه‌نگار/قاصدک: api_key و line_number (شماره خط)
    |--------------------------------------------------------------------------
    */
    'credentials' => [
        'meli_payamak' => [
            'url' => env('SMS_MELI_URL', 'https://rest.payamak-panel.com/api/SendSMS/SendSMS'),
            'username' => env('SMS_MELI_USERNAME', ''),
            'password' => env('SMS_MELI_PASSWORD', ''),
            'from' => env('SMS_MELI_FROM', ''), // شماره خط ارسال
        ],
        'kavenegar' => [
            'api_key' => env('SMS_KAVENEGAR_API_KEY', ''),
            'sender' => env('SMS_KAVENEGAR_SENDER', ''),
        ],
        'ghasedak' => [
            'api_key' => env('SMS_GHASEDAK_API_KEY', ''),
            'line_number' => env('SMS_GHASEDAK_LINE', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | قالب متن پیامک هشدار انقضا (کاربرپسند، مطابق محدودیت سرویس‌های ایرانی)
    | محدودیت: پیامک فارسی معمولاً 70 کاراکتر در هر بخش (۱ پیامک = ۱ بخش).
    | متغیرها: {platform_name} {site_title} {type_fa} {date_fa} {days_left}
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'expiry_reminder' => env('SMS_TEMPLATE_EXPIRY', '[{platform_name}] هشدار انقضای {type_fa} برای سایت {site_title}. تاریخ: {date_fa} ({days_left} روز مانده). لطفاً تمدید کنید.'),
    ],
];
