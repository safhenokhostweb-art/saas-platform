<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * 2026-11-10 (owner, column widget rework): a Canvas drag persists its
 * result as props.columns[i].width (percent). The renderer must prefer
 * those widths over the legacy ratios string, clamp every track to the
 * 10% authoring minimum, ignore malformed width data, and expose drag
 * hooks only while editing.
 */
class ColumnsResizableWidthsTest extends TestCase
{
    private function render(array $props, array $children = [], string $mode = 'editor'): string
    {
        $renderer = new BlockRenderer();
        if ($mode === 'editor') {
            $renderer->setContext(['editor_mode' => true]);
        } else {
            $renderer->setMode($mode);
        }

        return $renderer->renderBlock([
            'type' => 'columns',
            'id' => 'cols1',
            'props' => $props,
            'children' => $children,
        ]);
    }

    private function style(string $html): string
    {
        $this->assertSame(1, preg_match('/<style>(.*?)<\/style>/s', $html, $match));

        return $match[1];
    }

    public function test_saved_widths_override_the_ratio_tracks(): void
    {
        $html = $this->render([
            'ratios' => '1/2,1/2',
            'columns' => [['width' => 65], ['width' => 35]],
        ]);

        $this->assertStringContainsString(
            'grid-template-columns:minmax(0,1.3fr) minmax(0,0.7fr);',
            $this->style($html)
        );
        $this->assertStringContainsString('data-columns="2"', $html);
    }

    public function test_each_track_is_clamped_to_the_ten_percent_minimum(): void
    {
        $css = $this->style($this->render([
            'columns' => [['width' => 2], ['width' => 98]],
        ]));

        // 2% -> 10%, 98% -> 90%, then normalised into two fr tracks.
        $this->assertStringContainsString('grid-template-columns:minmax(0,0.2fr) minmax(0,1.8fr);', $css);
    }

    public function test_malformed_width_data_falls_back_to_ratios(): void
    {
        $css = $this->style($this->render([
            'ratios' => '1/3,1/3,1/3',
            'columns' => [['width' => ''], ['width' => 50]],
        ]));

        $this->assertStringContainsString('grid-template-columns:repeat(3,minmax(0,1fr));', $css);
        $this->assertStringNotContainsString('minmax(0,0fr)', $css);
    }

    public function test_a_single_width_entry_is_not_a_resizable_row(): void
    {
        $css = $this->style($this->render([
            'ratios' => '1/2,1/2',
            'columns' => [['width' => 80]],
        ]));

        $this->assertStringContainsString('grid-template-columns:repeat(2,minmax(0,1fr));', $css);
    }

    public function test_drag_hooks_exist_only_in_editor_rendering(): void
    {
        $props = ['columns' => [['width' => 40], ['width' => 60]]];

        $this->assertStringContainsString('data-tb-resizable="1"', $this->render($props));
        $this->assertStringNotContainsString('data-tb-resizable', $this->render($props, [], 'public'));
    }

    public function test_the_row_stacks_into_one_track_below_the_mobile_breakpoint(): void
    {
        $css = $this->style($this->render(['columns' => [['width' => 30], ['width' => 70]]]));

        $this->assertStringContainsString('@media(max-width:768px){#tb-cols1{grid-template-columns:minmax(0,1fr);}}', $css);
    }

    public function test_the_contract_sheet_carries_equal_height_and_resizer_rules(): void
    {
        $css = (string) file_get_contents(
            __DIR__ . '/../../../packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-contract.css'
        );

        $this->assertStringContainsString('.tb-columns { position: relative; place-items: stretch; }', $css);
        $this->assertStringContainsString('.tb-col-splitters { position: absolute;', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 767\.98px\) \{\s*\.tb-col-splitters \{ display: none; \}/', $css);
        $this->assertMatchesRegularExpression('/\.tb-columns :is\([^)]*\) > \.tb-btn:last-child,/', $css);
    }
}
