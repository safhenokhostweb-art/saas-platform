<?php

namespace SaaSPlatform\ThemeRuntime\Support;

/**
 * Phase 0.4 (ADR-063) — the theme runtime's own Persian number formatter.
 *
 * This package cannot use a host's App\Services\PersianFormatter (the
 * package owns no host code), so it carries this small, dependency-free
 * equivalent: Persian digits, Persian thousands/decimal separators (٬ ٫),
 * Rial→Toman, and compact amounts such as «۱٫۲ میلیون». Calendar-agnostic:
 * dates stay the host's concern.
 */
final class PersianNumber
{
    public const THOUSANDS_SEPARATOR = '٬'; // U+066C
    public const DECIMAL_SEPARATOR = '٫';   // U+066B
    public const TOMAN = 'تومان';

    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const ARABIC_INDIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    private const LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /** Largest unit first; «هزار میلیارد» is the usual Persian word for 10¹². */
    private const UNITS = [
        1_000_000_000_000 => 'هزار میلیارد',
        1_000_000_000 => 'میلیارد',
        1_000_000 => 'میلیون',
        1_000 => 'هزار',
    ];

    /**
     * A minus sign that stays on the left of the number in RTL text: a bare
     * hyphen before Arabic-script digits is reordered to their right.
     */
    private const MINUS = "\u{200E}\u{2212}";

    /** Latin and Arabic-Indic digits in any string become Persian digits. */
    public static function digits(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }
        return str_replace(self::LATIN, self::PERSIAN, str_replace(self::ARABIC_INDIC, self::PERSIAN, (string) $value));
    }

    /** Persian and Arabic-Indic digits become Latin — for parsing authored input. */
    public static function latinDigits(string $value): string
    {
        return str_replace(self::ARABIC_INDIC, self::LATIN, str_replace(self::PERSIAN, self::LATIN, $value));
    }

    /**
     * «۱٬۲۵۰٬۰۰۰», «۳٫۵». Accepts numbers or numeric strings in any digit
     * script; a non-numeric string is returned with its digits converted.
     */
    public static function format(int|float|string|null $value, int $decimals = 0): string
    {
        $number = self::toNumber($value);
        if ($number === null) {
            return self::digits(is_string($value) ? $value : '');
        }
        $decimals = max(0, $decimals);
        $formatted = strtr(number_format(abs($number), $decimals, '.', ','), [',' => self::THOUSANDS_SEPARATOR, '.' => self::DECIMAL_SEPARATOR]);
        // No sign on a value that rounds to zero («−۰»).
        $sign = $number < 0 && round(abs($number), $decimals) != 0 ? self::MINUS : '';
        return $sign . self::digits($formatted);
    }

    /**
     * Numbers inside authored text, e.g. a counter «5,000+» or «4.9/5»:
     * Persian digits; `,` between digits becomes ٬ and `.` becomes ٫; `%`
     * after a digit becomes ٪; a bare integer of four or more digits is
     * grouped («۵٬۰۰۰») unless it is probably a year (1300–1499, 1900–2099)
     * or starts with 0 (a phone or code). Everything else is kept as written.
     */
    public static function localize(string $text): string
    {
        $latin = self::latinDigits($text);
        $latin = preg_replace_callback('/\d+(?:[.,]\d+)*/', static function (array $m): string {
            $n = $m[0];
            if (!ctype_digit($n)) {
                return strtr($n, [',' => self::THOUSANDS_SEPARATOR, '.' => self::DECIMAL_SEPARATOR]);
            }
            $value = (int) $n;
            $year = strlen($n) === 4 && (($value >= 1300 && $value <= 1499) || ($value >= 1900 && $value <= 2099));
            if (strlen($n) < 4 || $year || $n[0] === '0' || strlen($n) > 15) {
                return $n;
            }
            return number_format($value, 0, '.', self::THOUSANDS_SEPARATOR);
        }, $latin) ?? $latin;
        $latin = preg_replace('/(\d)\s?%/', '$1٪', $latin) ?? $latin;
        return self::digits($latin);
    }

    /** One Toman is ten Rials. Whole results stay integers. */
    public static function rialToToman(int|float $rial): int|float
    {
        $toman = $rial / 10;
        return is_int($rial) && $rial % 10 === 0 ? intdiv($rial, 10) : $toman;
    }

    /**
     * «۱۲۵٬۰۰۰ تومان». With $fromRial the amount is converted first; Rial
     * amounts that are not whole Tomans keep one decimal place.
     */
    public static function toman(int|float|string|null $amount, bool $fromRial = false, bool $withUnit = true): string
    {
        $number = self::toNumber($amount) ?? 0;
        if ($fromRial) {
            $number = self::rialToToman($number);
        }
        $decimals = (is_float($number) && floor($number) != $number) ? 1 : 0;
        return self::format($number, $decimals) . ($withUnit ? ' ' . self::TOMAN : '');
    }

    /**
     * «۹۵۰», «۱ هزار», «۱٫۲ میلیون», «۳ میلیارد». Rounds to $precision
     * decimals, drops trailing zeros, and moves up a unit when rounding
     * reaches a thousand of the current one (۹۹۹٬۹۵۰ → «۱ میلیون»).
     */
    public static function compact(int|float|string|null $value, int $precision = 1): string
    {
        $number = self::toNumber($value) ?? 0;
        $abs = abs($number);
        $sign = $number < 0 ? self::MINUS : '';
        $units = array_reverse(self::UNITS, true); // smallest first

        $chosen = null;
        foreach ($units as $size => $word) {
            if ($abs >= $size) {
                $chosen = $size;
            }
        }
        if ($chosen === null) {
            $rounded = round($abs, $precision);
            if ($rounded < 1000) {
                return $rounded == 0 ? self::digits('0') : $sign . self::trimmed($rounded, $precision);
            }
            $chosen = 1_000; // 999.96 rounds up to a thousand
        }

        $scaled = round($abs / $chosen, $precision);
        $sizes = array_keys($units);
        $next = $sizes[array_search($chosen, $sizes, true) + 1] ?? null;
        if ($scaled >= 1000 && $next !== null) {
            $chosen = $next;
            $scaled = round($abs / $chosen, $precision);
        }

        return $sign . self::trimmed($scaled, $precision) . ' ' . self::UNITS[$chosen];
    }

    private static function trimmed(float $value, int $precision): string
    {
        $text = number_format($value, max(0, $precision), '.', ',');
        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }
        return self::digits(strtr($text, [',' => self::THOUSANDS_SEPARATOR, '.' => self::DECIMAL_SEPARATOR]));
    }

    private static function toNumber(int|float|string|null $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? $value : null;
        }
        if ($value === null) {
            return null;
        }
        $normalized = str_replace(
            [self::THOUSANDS_SEPARATOR, ',', self::DECIMAL_SEPARATOR, ' '],
            ['', '', '.', ''],
            self::latinDigits(trim($value))
        );
        if (!is_numeric($normalized)) {
            return null;
        }
        return str_contains($normalized, '.') || stripos($normalized, 'e') !== false ? (float) $normalized : (int) $normalized;
    }
}
