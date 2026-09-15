<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * BLD-04/05/08 (Full Product Commercial Audit, TASK 1/3): `icon`,
 * `progress_bar`, `modal` were registered with real schemas and zero
 * renderer anywhere in either repo — always empty regardless of props.
 */
class IconProgressBarModalRenderTest extends TestCase
{
    private function render(string $type, array $props, string $id = 'b1'): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => $id, 'props' => $props]);
    }

    public function test_icon_renders_the_authored_glyph(): void
    {
        $html = $this->render('icon', ['name' => '⚡', 'size' => 'large']);
        $this->assertStringContainsString('⚡', $html);
        $this->assertStringContainsString('3rem', $html);
    }

    public function test_icon_with_no_name_renders_nothing(): void
    {
        $this->assertSame('', trim($this->render('icon', [])));
    }

    public function test_progress_bar_reflects_the_real_value_in_width_and_aria(): void
    {
        $html = $this->render('progress_bar', ['value' => 73, 'label' => 'پیشرفت پروژه']);
        $this->assertStringContainsString('width:73%', $html);
        $this->assertStringContainsString('aria-valuenow="73"', $html);
        $this->assertStringContainsString('پیشرفت پروژه', $html);
        $this->assertStringContainsString('73%', $html);
    }

    public function test_progress_bar_clamps_out_of_range_values(): void
    {
        $this->assertStringContainsString('aria-valuenow="100"', $this->render('progress_bar', ['value' => 250]));
        $this->assertStringContainsString('aria-valuenow="0"', $this->render('progress_bar', ['value' => -30]));
    }

    public function test_modal_renders_trigger_and_nested_block_content(): void
    {
        $html = (new BlockRenderer())->renderBlock([
            'type' => 'modal',
            'id' => 'm1',
            'props' => ['trigger_text' => 'جزئیات بیشتر'],
            'blocks' => [
                ['id' => 'b1', 'type' => 'text', 'props' => ['content' => 'محتوای مودال']],
            ],
        ]);

        $this->assertStringContainsString('جزئیات بیشتر', $html);
        $this->assertStringContainsString('محتوای مودال', $html);
        $this->assertStringContainsString('tb-modal-trigger', $html);
        $this->assertStringContainsString('tb-modal-overlay', $html);
        $this->assertStringContainsString('role="dialog"', $html);
    }

    public function test_modal_with_no_blocks_still_renders_a_working_trigger(): void
    {
        $html = $this->render('modal', ['trigger_text' => 'باز کن']);
        $this->assertStringContainsString('باز کن', $html);
        $this->assertStringContainsString('tb-modal-overlay', $html);
    }
}
