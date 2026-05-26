<?php

namespace App\Adapters;

use SaaSPlatform\CoreUtils\Contracts\Services\DateFormatterInterface;
use SaaSPlatform\CoreUtils\Support\Jalali;

class JalaliDateFormatterAdapter implements DateFormatterInterface
{
    public function format(mixed $date, string $format = 'Y/m/d'): string
    {
        return Jalali::formatStatic($date, $format);
    }

    public function formatFa(mixed $date, string $format = 'Y/m/d H:i'): string
    {
        return Jalali::formatFaStatic($date, $format);
    }

    public function toCarbon(string $jalaliDate): ?\DateTimeInterface
    {
        return Jalali::toCarbonStatic($jalaliDate);
    }
}
