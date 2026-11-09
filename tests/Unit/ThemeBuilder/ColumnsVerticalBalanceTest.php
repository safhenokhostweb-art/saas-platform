<?php

namespace Tests\Unit\ThemeBuilder;

use PHPUnit\Framework\TestCase;

/**
 * 2026-11-07 (owner, live storefront): in a 2-cell `columns` row a
 * module:motion beside a taller slider sat at the top of its cell with a
 * gap beneath it. renderColumns() emits no column wrapper (each widget is a
 * grid cell), and cells already stretch to the row height. The fix centres
 * box-less widgets vertically. Equal-height cards keep stretching.
 */
class ColumnsVerticalBalanceTest extends TestCase
{
    private function css(): string
    {
        $css = file_get_contents(__DIR__ . '/../../../packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-contract.css');
        $this->assertIsString($css);

        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    private function body(string $css, string $selector): string
    {
        $this->assertSame(1, preg_match('/(?:^|[}\s])' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m), "rule for {$selector}");

        return $m[1];
    }

    public function test_a_motion_in_a_columns_cell_fills_the_cell_and_centres_its_artwork(): void
    {
        $body = $this->body($this->css(), '.tb-columns > :is(.tb-module-motion, .tb-module-motion-fallback)');

        $this->assertStringContainsString('display: flex;', $body);
        $this->assertStringContainsString('flex-direction: column;', $body);
        $this->assertStringContainsString('justify-content: center;', $body);
        // (0,2,0) beats `.tb-module-motion { display: block }` (0,1,0) in
        // theme-runtime-blocks.css without !important.
        $this->assertStringNotContainsString('!important', $body);
    }

    public function test_text_type_widgets_centre_in_the_cell(): void
    {
        $body = $this->body($this->css(), '.tb-columns > :is(.tb-heading, .tb-text, .tb-rich-text, .tb-image)');

        $this->assertStringContainsString('align-self: center;', $body);
    }

    public function test_the_columns_grid_itself_still_stretches_its_cells(): void
    {
        // Centring the whole row (align-items on .tb-columns) would make
        // cards and pricing boxes side by side unequal in height.
        $this->assertDoesNotMatchRegularExpression('/\.tb-columns\s*\{[^}]*align-items/', $this->css());
    }
}
