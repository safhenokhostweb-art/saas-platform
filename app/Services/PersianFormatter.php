<?php

namespace App\Services;

use App\Helpers\Jalali;
use App\Helpers\NumberHelper;

/**
 * PERSIAN LOCALIZATION GLOBAL STANDARD — Centralized Formatter
 *
 * Single entry point for ALL user-visible localization in this platform.
 * Never call number_format() or Carbon::format() directly in views.
 * Always route through this class or its Blade directive equivalents.
 *
 * Blade directives (registered in AppServiceProvider):
 *   @numFa($n)            → self::num($n)
 *   @numFa($n, 2)         → self::num($n, 2)
 *   @priceFa($n)          → self::currency($n)
 *   @priceFa($n, 'ریال')  → self::currency($n, 'ریال')
 *   @dateFa($d)           → self::date($d)
 *   @dateFaLong($d)       → self::dateLong($d)
 *   @dateFaShort($d)      → self::dateShort($d)
 *   @datetimeFa($d)       → self::datetime($d)
 *   @agoFa($d)            → self::ago($d)
 *   @digitsFa($s)         → self::digits($s)
 *
 * Global PHP helpers (auto-loaded via composer.json):
 *   num_fa($n)            price_fa($n)    date_fa($d)
 *   date_fa_long($d)      date_fa_short($d)   datetime_fa($d)
 *   ago_fa($d)            digits_fa($s)
 */
final class PersianFormatter
{
    /**
     * Format a number with thousand separators + Persian digits.
     * Replaces: number_format($n)
     */
    public static function num(int|float|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '۰';
        }
        return NumberHelper::toPersianNumbers(number_format((float) $value, $decimals));
    }

    /**
     * Format a number as currency with Persian digits + unit.
     * Replaces: number_format($n) . ' تومان'
     */
    public static function currency(int|float|null $value, string $unit = 'تومان', int $decimals = 0): string
    {
        return self::num($value, $decimals) . ' ' . $unit;
    }

    /**
     * Format a date in Jalali with Persian digits.
     * Default output: ۱۴۰۵/۰۴/۰۴
     * Replaces: Jalali::format($d, 'Y/m/d') or Carbon's format()
     */
    public static function date(mixed $date, string $format = 'Y/m/d'): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        return Jalali::formatFa($date, $format);
    }

    /**
     * Full Jalali date with Persian month name + Persian digits.
     * Output: چهارشنبه ۴ تیر ۱۴۰۵
     */
    public static function dateLong(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $str = Jalali::format($date, 'l j F Y');
        return NumberHelper::toPersianNumbers($str);
    }

    /**
     * Short Jalali date with month name.
     * Output: ۴ تیر ۱۴۰۵
     */
    public static function dateShort(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $str = Jalali::format($date, 'j F Y');
        return NumberHelper::toPersianNumbers($str);
    }

    /**
     * Jalali date + time with Persian digits.
     * Output: ۱۴۰۵/۰۴/۰۴ ۱۴:۳۰
     */
    public static function datetime(mixed $date, string $format = 'Y/m/d H:i'): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        return Jalali::formatFa($date, $format);
    }

    /**
     * Persian relative time.
     * Output: همین الان / ۳ دقیقه پیش / دیروز / ۲ ماه پیش
     * Replaces: Carbon::diffForHumans()
     */
    public static function ago(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        return Jalali::diffForHumansFa($date);
    }

    /**
     * Convert any English/Arabic digits in a string to Persian.
     * Use for arbitrary strings that may contain numbers.
     */
    public static function digits(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return NumberHelper::toPersianNumbers((string) $value);
    }

    /**
     * Convert a submitted Jalali date string (from jalali-datepicker) to
     * a Gregorian date string (YYYY-MM-DD) safe for Laravel validation + DB.
     * Returns null if conversion fails (field was empty or unparseable).
     */
    public static function jalaliToGregorian(?string $jalaliDate): ?string
    {
        if ($jalaliDate === null || trim($jalaliDate) === '') {
            return null;
        }
        $carbon = Jalali::toCarbon(trim($jalaliDate));
        return $carbon?->toDateString();
    }
}
