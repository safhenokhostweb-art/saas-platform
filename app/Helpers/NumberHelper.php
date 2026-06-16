<?php
namespace App\Helpers;

class NumberHelper
{
    /**
     * تبدیل اعداد انگلیسی به فارسی با static cache برای رشته‌های تکراری.
     *
     * @param string|int|float|null $value
     * @return string
     */
    public static function toPersianNumbers($value): string
    {
        if ($value === null || $value === '') {
            return (string) $value;
        }

        $input = (string) $value;

        // static cache: رشته‌های تکراری دوباره پردازش نشوند
        static $cache = [];
        if (isset($cache[$input])) {
            return $cache[$input];
        }

        // تبدیل اعداد انگلیسی (0-9) و عربی (٠-٩) به فارسی
        $persian = [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
            '٠' => '۰', '١' => '۱', '٢' => '۲', '٣' => '۳', '٤' => '۴',
            '٥' => '۵', '٦' => '۶', '٧' => '۷', '٨' => '۸', '٩' => '۹',
        ];

        $result = strtr($input, $persian);
        $cache[$input] = $result;

        return $result;
    }

    /**
     * ترکیب number_format و toPersianNumbers.
     * مقدار عددی را فرمت می‌کند و اعداد را به فارسی تبدیل می‌کند.
     *
     * @param int|float|null $amount
     * @param int $decimals
     * @return string
     */
    public static function formatFa($amount, int $decimals = 0): string
    {
        if ($amount === null || $amount === '') {
            return '۰';
        }
        if (is_string($amount)) {
            $amount = (float) str_replace(',', '', $amount);
        }
        return self::toPersianNumbers(number_format((float) $amount, $decimals));
    }
}
