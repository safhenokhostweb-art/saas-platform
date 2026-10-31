<?php

namespace App\Services\DevOps;

use App\Services\PersianFormatter;

/** Persian byte sizes for the DevOps dashboard: "۵٫۲ گیگابایت". */
final class ByteFormatter
{
    private const UNITS = [
        1099511627776 => 'ترابایت',
        1073741824    => 'گیگابایت',
        1048576       => 'مگابایت',
        1024          => 'کیلوبایت',
    ];

    public static function fa(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        foreach (self::UNITS as $size => $unit) {
            if ($bytes >= $size) {
                $value    = round($bytes / $size, $bytes / $size >= 100 ? 0 : 1);
                $decimals = fmod($value, 1.0) === 0.0 ? 0 : 1;

                return PersianFormatter::num($value, $decimals) . ' ' . $unit;
            }
        }

        return PersianFormatter::num($bytes) . ' بایت';
    }
}
