<?php
namespace App\Helpers;

use Carbon\Carbon;
use SaaSPlatform\CoreUtils\Support\Jalali as CoreJalali;

/**
 * Backward-compatibility proxy.
 * All logic lives in SaaSPlatform\CoreUtils\Support\Jalali.
 */
class Jalali
{
    public static function format($date, string $format = 'Y/m/d'): string
    {
        return CoreJalali::formatStatic($date, $format);
    }

    public static function formatFa($date, string $format = 'Y/m/d H:i'): string
    {
        return CoreJalali::formatFaStatic($date, $format);
    }

    public static function toCarbon(?string $jalaliDate): ?Carbon
    {
        return CoreJalali::toCarbonStatic($jalaliDate);
    }

    public static function isJalaliString(?string $value): bool
    {
        return CoreJalali::isJalaliString($value);
    }

    public static function diffForHumansFa($date): string
    {
        return CoreJalali::diffForHumansFaStatic($date);
    }
}
