<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

/**
 * Contract for locale-aware date formatting (Jalali / Solar Hijri).
 * Packages inject this to format dates without importing App\Helpers\Jalali.
 */
interface DateFormatterInterface
{
    /** Format a date as Jalali string (e.g. "1403/02/15"). */
    public function format(mixed $date, string $format = 'Y/m/d'): string;

    /** Format a date with Persian (Eastern Arabic) numerals. */
    public function formatFa(mixed $date, string $format = 'Y/m/d H:i'): string;

    /** Convert a Jalali date string to a Carbon/DateTime instance, or null on failure. */
    public function toCarbon(string $jalaliDate): ?\DateTimeInterface;
}
