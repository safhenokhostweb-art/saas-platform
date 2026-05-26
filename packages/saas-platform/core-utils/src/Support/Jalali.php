<?php

namespace SaaSPlatform\CoreUtils\Support;

use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use SaaSPlatform\CoreUtils\Contracts\Services\DateFormatterInterface;

/**
 * Jalali (Solar Hijri) date utility.
 * Canonical implementation of DateFormatterInterface.
 * Moved from App\Helpers\Jalali so packages can depend on this class
 * without importing the host application namespace.
 *
 * The host app keeps its own App\Helpers\Jalali as a thin proxy.
 */
class Jalali implements DateFormatterInterface
{
    public function format(mixed $date, string $format = 'Y/m/d'): string
    {
        return static::formatStatic($date, $format);
    }

    public function formatFa(mixed $date, string $format = 'Y/m/d H:i'): string
    {
        return static::formatFaStatic($date, $format);
    }

    public function toCarbon(string $jalaliDate): ?\DateTimeInterface
    {
        return static::toCarbonStatic($jalaliDate);
    }

    // ─── Static helpers (backward-compat for call-sites that use Jalali::format()) ─

    public static function formatStatic(mixed $date, string $format = 'Y/m/d'): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        if (is_string($date)) {
            try {
                $date = Carbon::parse($date);
            } catch (\Throwable $e) {
                return '';
            }
        }
        if (!$date instanceof Carbon && !$date instanceof \DateTimeInterface) {
            return '';
        }
        try {
            return Verta::instance($date)->format($format);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function formatFaStatic(mixed $date, string $format = 'Y/m/d H:i'): string
    {
        $en = static::formatStatic($date, $format);
        if ($en === '') {
            return '';
        }
        $persian = ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                    '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'];
        return strtr($en, $persian);
    }

    public static function toCarbonStatic(?string $jalaliDate): ?Carbon
    {
        if ($jalaliDate === null || trim($jalaliDate) === '') {
            return null;
        }
        $jalaliDate = trim($jalaliDate);
        $persian = ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                    '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'];
        $jalaliDate = strtr($jalaliDate, $persian);
        $jalaliDate = str_replace(['/', '-', ' '], '-', $jalaliDate);
        $parts = array_filter(explode('-', $jalaliDate));
        if (count($parts) < 3) {
            return null;
        }
        try {
            $y = (int) $parts[0];
            $m = (int) $parts[1];
            $d = (int) $parts[2];
            $v = Verta::createJalaliDate($y, $m, $d);
            return $v->toCarbon();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function isJalaliString(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }
        $value = preg_replace('/[۰-۹]/', '0', trim($value));
        return (bool) preg_match('/^1[34]\d{2}[\-\/]/', $value);
    }
}
