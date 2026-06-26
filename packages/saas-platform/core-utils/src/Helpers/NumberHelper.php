<?php

namespace SaaSPlatform\CoreUtils\Helpers;

class NumberHelper
{
    /**
     * Format a number with Persian/Arabic digits and thousand separators.
     * PERSIAN LOCALIZATION STANDARD: always outputs Persian digits.
     */
    public static function format(int|float $number, int $decimals = 0): string
    {
        return self::toPersian(number_format($number, $decimals));
    }

    /**
     * Convert English digits to Persian digits.
     */
    public static function toPersian(string $text): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($english, $persian, $text);
    }

    /**
     * Format a number as currency (Toman/IRR) with Persian digits.
     * PERSIAN LOCALIZATION STANDARD: always outputs Persian digits + unit.
     */
    public static function currency(int|float $amount, string $unit = 'تومان'): string
    {
        return self::toPersian(number_format($amount)) . ' ' . $unit;
    }

    /**
     * Format a percentage value with Persian digits.
     */
    public static function percent(float $value, int $decimals = 1): string
    {
        return self::toPersian(number_format($value * 100, $decimals)) . '%';
    }

    /**
     * Generate a random numeric code with given length.
     */
    public static function randomCode(int $length = 6): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }
}
