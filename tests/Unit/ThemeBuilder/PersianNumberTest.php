<?php

namespace Tests\Unit\ThemeBuilder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaaSPlatform\ThemeRuntime\Support\PersianNumber;

/**
 * Phase 0.4 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-063) — the
 * theme runtime's package-local Persian number formatter.
 */
class PersianNumberTest extends TestCase
{
    private const MINUS = "\u{200E}\u{2212}";

    public function test_digits_converts_latin_and_arabic_indic_and_keeps_other_text(): void
    {
        $this->assertSame('۰۱۲۳۴۵۶۷۸۹', PersianNumber::digits('0123456789'));
        $this->assertSame('۰۱۲۳۴۵۶۷۸۹', PersianNumber::digits('٠١٢٣٤٥٦٧٨٩'));
        $this->assertSame('ارسال در ۲ روز', PersianNumber::digits('ارسال در 2 روز'));
        $this->assertSame('۴۲', PersianNumber::digits(42));
        $this->assertSame('', PersianNumber::digits(null));
    }

    public function test_latin_digits_reverses_both_scripts(): void
    {
        $this->assertSame('09121234567', PersianNumber::latinDigits('۰۹۱۲۱۲۳۴۵۶۷'));
        $this->assertSame('2026', PersianNumber::latinDigits('٢٠٢٦'));
    }

    /** @return array<string, array{0: mixed, 1: int, 2: string}> */
    public static function formatted(): array
    {
        return [
            'grouping' => [1250000, 0, '۱٬۲۵۰٬۰۰۰'],
            'small' => [999, 0, '۹۹۹'],
            'zero' => [0, 0, '۰'],
            'decimals' => [3.5, 1, '۳٫۵'],
            'rounding' => [1234.567, 2, '۱٬۲۳۴٫۵۷'],
            'negative' => [-1500, 0, self::MINUS . '۱٬۵۰۰'],
            'negative rounds to zero' => [-0.004, 2, '۰٫۰۰'],
            'persian numeric string' => ['۱۲۵۰۰۰۰', 0, '۱٬۲۵۰٬۰۰۰'],
            'grouped string' => ['1,250,000', 0, '۱٬۲۵۰٬۰۰۰'],
            'persian decimal string' => ['۳٫۲۵', 2, '۳٫۲۵'],
        ];
    }

    #[DataProvider('formatted')]
    public function test_format(mixed $value, int $decimals, string $expected): void
    {
        $this->assertSame($expected, PersianNumber::format($value, $decimals));
    }

    public function test_format_leaves_non_numeric_text_alone_apart_from_digits(): void
    {
        $this->assertSame('تماس بگیرید', PersianNumber::format('تماس بگیرید'));
        $this->assertSame('رایگان ۱', PersianNumber::format('رایگان 1'));
        $this->assertSame('', PersianNumber::format(null));
        $this->assertSame('', PersianNumber::format(NAN));
    }

    public function test_rial_to_toman(): void
    {
        $this->assertSame(125000, PersianNumber::rialToToman(1250000));
        $this->assertSame(12.5, PersianNumber::rialToToman(125));
        $this->assertSame(0.5, PersianNumber::rialToToman(5.0));
    }

    public function test_toman(): void
    {
        $this->assertSame('۱۲۵٬۰۰۰ تومان', PersianNumber::toman(125000));
        $this->assertSame('۱۲۵٬۰۰۰ تومان', PersianNumber::toman(1250000, fromRial: true));
        $this->assertSame('۱۲٫۵ تومان', PersianNumber::toman(125, fromRial: true));
        $this->assertSame('۱۲۵٬۰۰۰', PersianNumber::toman(125000, withUnit: false));
        $this->assertSame('۰ تومان', PersianNumber::toman(null));
    }

    /** @return array<string, array{0: int|float, 1: string}> */
    public static function compacted(): array
    {
        return [
            'below a thousand' => [950, '۹۵۰'],
            'zero' => [0, '۰'],
            'one thousand' => [1000, '۱ هزار'],
            'thousands' => [350000, '۳۵۰ هزار'],
            'one decimal' => [1200000, '۱٫۲ میلیون'],
            'trailing zero dropped' => [2000000, '۲ میلیون'],
            'rounds within the unit' => [1250000, '۱٫۳ میلیون'],
            'rolls up a unit' => [999950, '۱ میلیون'],
            'rolls up below a thousand' => [999.96, '۱ هزار'],
            'billions' => [3400000000, '۳٫۴ میلیارد'],
            'trillions' => [1500000000000, '۱٫۵ هزار میلیارد'],
            'beyond the largest unit' => [2500000000000000, '۲٬۵۰۰ هزار میلیارد'],
            'negative' => [-1200000, self::MINUS . '۱٫۲ میلیون'],
        ];
    }

    #[DataProvider('compacted')]
    public function test_compact(int|float $value, string $expected): void
    {
        $this->assertSame($expected, PersianNumber::compact($value));
    }

    public function test_compact_precision_and_numeric_strings(): void
    {
        $this->assertSame('۱٫۲۵ میلیون', PersianNumber::compact(1250000, 2));
        $this->assertSame('۱ میلیون', PersianNumber::compact(1250000, 0));
        $this->assertSame('۱٫۲ میلیون', PersianNumber::compact('۱۲۰۰۰۰۰'));
    }
}
