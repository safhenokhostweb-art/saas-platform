<?php

/**
 * PERSIAN LOCALIZATION GLOBAL STANDARD — Global Helper Functions
 *
 * These helpers are auto-loaded via composer.json.
 * Use them anywhere in PHP code (controllers, services, exports).
 *
 * For Blade templates, prefer the @directive form for cleaner templates:
 *   @numFa($n)  instead of  {{ num_fa($n) }}
 */

use App\Services\PersianFormatter;

if (!function_exists('num_fa')) {
    function num_fa(int|float|null $value, int $decimals = 0): string
    {
        return PersianFormatter::num($value, $decimals);
    }
}

if (!function_exists('price_fa')) {
    function price_fa(int|float|null $value, string $unit = 'تومان', int $decimals = 0): string
    {
        return PersianFormatter::currency($value, $unit, $decimals);
    }
}

if (!function_exists('date_fa')) {
    function date_fa(mixed $date, string $format = 'Y/m/d'): string
    {
        return PersianFormatter::date($date, $format);
    }
}

if (!function_exists('date_fa_long')) {
    function date_fa_long(mixed $date): string
    {
        return PersianFormatter::dateLong($date);
    }
}

if (!function_exists('date_fa_short')) {
    function date_fa_short(mixed $date): string
    {
        return PersianFormatter::dateShort($date);
    }
}

if (!function_exists('datetime_fa')) {
    function datetime_fa(mixed $date, string $format = 'Y/m/d H:i'): string
    {
        return PersianFormatter::datetime($date, $format);
    }
}

if (!function_exists('ago_fa')) {
    function ago_fa(mixed $date): string
    {
        return PersianFormatter::ago($date);
    }
}

if (!function_exists('digits_fa')) {
    function digits_fa(string|int|float|null $value): string
    {
        return PersianFormatter::digits($value);
    }
}

if (!function_exists('jalali_to_gregorian')) {
    function jalali_to_gregorian(?string $jalaliDate): ?string
    {
        return PersianFormatter::jalaliToGregorian($jalaliDate);
    }
}
