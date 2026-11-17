<?php

namespace App\Support;

/**
 * The one normaliser for Iranian mobile numbers: Persian/Arabic-Indic digits
 * to Latin, separators dropped, a leading +98/0098/98 or a bare 9xxxxxxxxx
 * brought to the canonical 09xxxxxxxxx form that `customers.mobile` stores.
 * IranianMobile (validation) and the onboarding wizard (lookup, storage)
 * both read through here so a number typed as «۰۹۱۲ ۳۴۵ ۶۷۸۹» and one typed
 * as «+98 912 345 6789» resolve to the same customer.
 */
final class IranianMobileNumber
{
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /** Canonical 09xxxxxxxxx, or null when the input is not an Iranian mobile. */
    public static function normalize(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', strtr((string) $value, self::DIGITS)) ?? '';

        if (str_starts_with($digits, '0098')) {
            $digits = '0' . substr($digits, 4);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '98')) {
            $digits = '0' . substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        return preg_match('/^09\d{9}$/', $digits) === 1 ? $digits : null;
    }

    public static function isValid(mixed $value): bool
    {
        return self::normalize($value) !== null;
    }
}
