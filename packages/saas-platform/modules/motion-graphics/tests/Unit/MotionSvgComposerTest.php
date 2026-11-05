<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SaaSPlatform\Modules\MotionGraphics\Support\MotionSvgComposer;

/**
 * ADR-079: runtime parameters are validated strictly and applied safely.
 */
final class MotionSvgComposerTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 960 540"><style id="asset-theme">:root{--asset-primary:#2563eb}</style><style>.a{animation:x calc(1s*var(--k,1)) both}</style><text data-p="title">پیش‌فرض</text><text data-p="share">۳۶٪</text><text>ثابت</text></svg>';

    private function definitions(): array
    {
        return MotionSvgComposer::normalizeDefinitions([
            ['key' => 'title', 'type' => 'text', 'label' => 'عنوان', 'default' => 'پیش‌فرض', 'max' => 12],
            ['key' => 'primary', 'type' => 'color', 'label' => 'رنگ', 'token' => '--asset-primary', 'default' => '#2563EB'],
            ['key' => 'share', 'type' => 'number', 'label' => 'سهم', 'min' => 0, 'max' => 100, 'step' => 1, 'default' => 36, 'format' => 'percent', 'css' => true],
            ['key' => 'animate', 'type' => 'boolean', 'role' => 'animate', 'label' => 'پخش', 'default' => true],
            ['key' => 'loop', 'type' => 'boolean', 'role' => 'loop', 'label' => 'لوپ', 'default' => true],
            ['key' => 'speed', 'type' => 'select', 'role' => 'speed', 'label' => 'سرعت', 'options' => ['slow' => 'آرام', 'normal' => 'عادی', 'fast' => 'تند', 'warp' => 'نامعتبر'], 'default' => 'normal'],
        ]);
    }

    public function test_malformed_definitions_are_dropped_not_guessed(): void
    {
        $defs = MotionSvgComposer::normalizeDefinitions([
            ['key' => 'Bad Key', 'type' => 'text', 'label' => 'x', 'default' => 'x'],
            ['key' => 'no_label', 'type' => 'text', 'default' => 'x'],
            ['key' => 'odd_type', 'type' => 'script', 'label' => 'x', 'default' => 'x'],
            ['key' => 'bad_token', 'type' => 'color', 'label' => 'x', 'token' => 'color', 'default' => '#000000'],
            ['key' => 'bad_range', 'type' => 'number', 'label' => 'x', 'min' => 10, 'max' => 5, 'default' => 7],
            ['key' => 'ok', 'type' => 'text', 'label' => 'x', 'default' => 'y'],
            ['key' => 'ok', 'type' => 'text', 'label' => 'duplicate', 'default' => 'z'],
            'not-an-array',
        ]);

        $this->assertSame(['ok'], array_column($defs, 'key'));
        $this->assertSame(['slow', 'normal', 'fast'], array_keys($this->definitions()[5]['options']), 'speed options are limited to known factors');
        $this->assertSame('#2563eb', $this->definitions()[1]['default']);
    }

    public function test_values_are_validated_canonicalized_and_defaults_dropped(): void
    {
        $values = MotionSvgComposer::resolveValues($this->definitions(), [
            'title' => "  عنوان\u{202E}جدید 2026  ",
            'primary' => '#FF0055',
            'share' => '72.4',
            'speed' => 'fast',
            'loop' => '0',
            'animate' => 'true',          // equals default → dropped
            'unknown' => 'x',
        ]);

        // Bidi override stripped, whitespace collapsed, Persian digits, and
        // truncated at the definition's max (12 characters).
        $this->assertSame(['loop' => false, 'primary' => '#ff0055', 'share' => 72, 'speed' => 'fast', 'title' => 'عنوانجدید ۲۰'], $values);
        $this->assertSame([], MotionSvgComposer::resolveValues($this->definitions(), [
            'primary' => 'red', 'share' => '101', 'speed' => 'warp', 'title' => '', 'loop' => 'maybe',
        ]));
    }

    public function test_text_is_set_as_dom_text_never_markup(): void
    {
        $defs = $this->definitions();
        $out = (new MotionSvgComposer())->compose(self::SVG, $defs, MotionSvgComposer::resolveValues($defs, [
            'title' => '<script>x</script>',
        ]));

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('<text>ثابت</text>', $out, 'elements without data-p are untouched');
    }

    public function test_colours_numbers_and_runtime_switches_become_css(): void
    {
        $defs = $this->definitions();
        $out = (new MotionSvgComposer())->compose(self::SVG, $defs, MotionSvgComposer::resolveValues($defs, [
            'primary' => '#ff0055', 'share' => 55, 'speed' => 'slow', 'loop' => false, 'animate' => false,
        ]));

        $this->assertStringContainsString('--asset-primary:#ff0055', $out);
        $this->assertStringContainsString('@media (prefers-color-scheme:dark){:root{--asset-primary:#ff0055}}', $out);
        $this->assertStringContainsString('--p-share:55;--p-share-rest:45', $out);
        $this->assertStringContainsString('--k:1.5', $out);
        $this->assertStringContainsString('*{animation:none!important}', $out);
        $this->assertStringContainsString('*{animation-iteration-count:1!important}', $out);
        $this->assertStringContainsString('>۵۵٪</text>', $out);
        // The override style comes after the theme style, so it wins.
        $this->assertLessThan(strpos($out, 'id="asset-runtime"'), strpos($out, 'id="asset-theme"'));
    }

    public function test_the_font_is_embedded_first_and_output_is_deterministic(): void
    {
        $defs = $this->definitions();
        $composer = new MotionSvgComposer();
        $a = $composer->compose(self::SVG, $defs, [], 'WOFF2BYTES');
        $b = $composer->compose(self::SVG, $defs, [], 'WOFF2BYTES');

        $this->assertSame($a, $b);
        $this->assertStringContainsString("@font-face{font-family:'Vazirmatn';src:url(data:font/woff2;base64,".base64_encode('WOFF2BYTES').')', $a);
        $this->assertLessThan(strpos($a, 'id="asset-theme"'), strpos($a, 'id="asset-font"'));
        $this->assertStringNotContainsString('asset-runtime', $a, 'defaults add no override style');
        $this->assertNotFalse(simplexml_load_string($a));
    }

    public function test_non_svg_input_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MotionSvgComposer())->compose('<html><body/></html>', [], []);
    }
}
