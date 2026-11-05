<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * nav_header `header_order`: the stored order positions header elements and
 * never hides one; visibility stays with each element's own props.
 */
class NavHeaderOrderTest extends TestCase
{
    private const SEARCH = 'class="tb-nav-header-search"';
    private const PHONE = 'class="tb-nav-header-phone';
    private const CTA = 'tb-nav-header-cta';
    private const LOGO = 'class="tb-nav-header-logo-slot';

    private function render(array $props): string
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => 'h1', 'props' => $props + [
            'logo_text' => 'برند',
            'show_search' => true,
            'phone' => '021 1234',
            'cta_text' => 'تماس',
            'cta_url' => '/contact',
        ]]);
        // Element markup only: the inline <style> also names these classes.
        $start = strpos($html, '<div class="tb-nav-header-inner">');
        $this->assertNotFalse($start, 'header inner wrapper rendered');

        return substr($html, $start);
    }

    private function assertBefore(string $first, string $second, string $html): void
    {
        $a = strpos($html, $first);
        $b = strpos($html, $second);
        $this->assertNotFalse($a, "«{$first}» rendered");
        $this->assertNotFalse($b, "«{$second}» rendered");
        $this->assertLessThan($b, $a, "«{$first}» comes before «{$second}»");
    }

    public function test_the_stored_order_is_followed(): void
    {
        $html = $this->render(['header_order' => ['cta', 'phone', 'search', 'logo']]);

        $this->assertBefore(self::CTA, self::PHONE, $html);
        $this->assertBefore(self::PHONE, self::SEARCH, $html);
        $this->assertBefore(self::SEARCH, self::LOGO, $html);
    }

    public function test_an_empty_or_invalid_order_falls_back_to_the_full_default(): void
    {
        foreach ([[], '', null, 'not json', ['', ['x'], 'unknown']] as $order) {
            $html = $this->render(['header_order' => $order]);
            $this->assertBefore(self::LOGO, self::SEARCH, $html);
            $this->assertBefore(self::SEARCH, self::PHONE, $html);
            $this->assertBefore(self::PHONE, self::CTA, $html);
        }
    }

    public function test_a_json_string_order_is_accepted(): void
    {
        $html = $this->render(['header_order' => json_encode(['phone', 'logo'])]);

        $this->assertBefore(self::PHONE, self::LOGO, $html);
    }

    public function test_a_partial_order_never_hides_the_elements_it_omits(): void
    {
        $html = $this->render(['header_order' => ['phone', 'logo']]);

        $this->assertBefore(self::PHONE, self::LOGO, $html);
        // search and cta were omitted from the stored order: still rendered, after.
        $this->assertBefore(self::LOGO, self::SEARCH, $html);
        $this->assertBefore(self::SEARCH, self::CTA, $html);
    }

    public function test_visibility_props_still_apply_inside_the_order(): void
    {
        $html = $this->render([
            'header_order' => ['search', 'cta', 'phone', 'logo'],
            'show_search' => false,
            'cta_text' => '',
        ]);

        $this->assertStringNotContainsString(self::SEARCH, $html);
        $this->assertStringNotContainsString(self::CTA, $html);
        $this->assertBefore(self::PHONE, self::LOGO, $html);
    }
}
