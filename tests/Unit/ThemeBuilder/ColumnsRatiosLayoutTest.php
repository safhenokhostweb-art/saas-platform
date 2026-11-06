<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * 2026-11-06 (owner, Canvas Dev HUD): a widget inside `columns` must sit in
 * its own cell. The renderer used to give every child `flex:1 1 0` and
 * ignore `ratios`, so a lone motion in a 2-column block filled the whole
 * row. The columns are now grid tracks taken from `ratios`.
 */
class ColumnsRatiosLayoutTest extends TestCase
{
    private function render(array $props, array $children, string $id = 'cols1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => 'columns', 'id' => $id, 'props' => $props, 'children' => $children]);
    }

    private function style(string $html): string
    {
        $this->assertSame(1, preg_match('/<style>(.*?)<\/style>/s', $html, $m));

        return $m[1];
    }

    public function test_a_lone_child_of_two_columns_gets_one_cell_not_the_whole_row(): void
    {
        $html = $this->render(['ratios' => '1/2,1/2', 'gap' => 'md'], [
            ['id' => 'm1', 'type' => 'heading', 'props' => ['text' => 'تنها فرزند']],
        ]);
        $css = $this->style($html);

        $this->assertStringContainsString('#tb-cols1{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.5rem;}', $css);
        $this->assertStringNotContainsString('flex:1 1 0', $css, 'no child may grow to fill the row');
        $this->assertStringContainsString('#tb-cols1>*{min-width:0;}', $css);
        $this->assertStringContainsString('data-columns="2"', $html);
        $this->assertStringContainsString('تنها فرزند', $html);
    }

    public function test_every_picker_option_maps_to_that_many_equal_tracks(): void
    {
        foreach (['1' => 1, '1/2,1/2' => 2, '1/3,1/3,1/3' => 3, '1/4,1/4,1/4,1/4' => 4] as $ratios => $count) {
            $html = $this->render(['ratios' => $ratios], []);
            $this->assertStringContainsString("grid-template-columns:repeat({$count},minmax(0,1fr));", $this->style($html), $ratios);
            $this->assertStringContainsString("data-columns=\"{$count}\"", $html);
        }
    }

    public function test_unequal_ratios_become_weighted_tracks(): void
    {
        $css = $this->style($this->render(['ratios' => '1/3, 2/3'], []));

        $this->assertStringContainsString('grid-template-columns:minmax(0,0.667fr) minmax(0,1.333fr);', $css);
    }

    public function test_malformed_ratios_fall_back_to_two_equal_columns_safely(): void
    {
        foreach (['', 'abc', '1/0', '0/2,1/2', '1/2;}body{display:none'] as $ratios) {
            $css = $this->style($this->render(['ratios' => $ratios], []));
            $this->assertStringContainsString('grid-template-columns:repeat(2,minmax(0,1fr));', $css, var_export($ratios, true));
            $this->assertStringNotContainsString('body{', $css);
        }
    }

    public function test_it_stacks_into_one_column_on_small_screens(): void
    {
        $css = $this->style($this->render(['ratios' => '1/3,1/3,1/3', 'gap' => 'lg'], []));

        $this->assertStringContainsString('gap:2rem;', $css);
        $this->assertStringContainsString('@media(max-width:768px){#tb-cols1{grid-template-columns:minmax(0,1fr);}}', $css);
    }
}
