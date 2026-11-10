<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\Canvas\PreviewProtocol;
use Tests\TestCase;

/**
 * 2026-11-10 (owner, column widget rework): the Inspector is the second
 * surface for the same props.columns[i].width a Canvas drag writes. These
 * tests pin the contract that makes two-way sync safe: per-column controls,
 * a total that always stays at 100%, a reset that drops manual widths, and
 * a preview message that only accepts numeric percentages.
 */
class ColumnsInspectorWidthsTest extends TestCase
{
    private function editor(): string
    {
        return (string) file_get_contents(resource_path('views/admin/theme-builder/canvas.blade.php'));
    }

    private function preview(): string
    {
        return (string) file_get_contents(resource_path('views/admin/theme-builder/canvas-live-preview.blade.php'));
    }

    public function test_the_columns_inspector_renders_per_column_percent_controls(): void
    {
        $editor = $this->editor();

        foreach (['tb-column-widths', 'tb-col-width-input-', 'tb-col-width-range-', 'tb-col-widths-even', 'tb-col-widths-reset'] as $testId) {
            $this->assertStringContainsString($testId, $editor, "missing Inspector hook {$testId}");
        }
        $this->assertStringContainsString("selectedBlock?.type === 'columns'", $editor);
        $this->assertStringContainsString('queueColumnWidthInput(index, $event.target.value)', $editor);
        $this->assertStringContainsString('cancelColumnWidthInput()', $editor);
        $this->assertStringContainsString('columnWidths()', $editor);
    }

    public function test_every_numeric_edit_keeps_the_total_at_one_hundred(): void
    {
        $editor = $this->editor();

        // 10% floor, the remaining columns take the rest proportionally, and
        // one write lands as a single undoable value operation.
        $this->assertStringContainsString('100 - minimum * (count - 1)', $editor);
        $this->assertStringContainsString('columnWidthCoalesceKey(blockId)', $editor);
        $this->assertStringContainsString('coalesceWindow: 1500', $editor);
        $this->assertStringContainsString('columnWidthTotal()', $editor);
        $this->assertStringContainsString('clampColumnWidths(widths)', $editor);
    }

    public function test_even_and_reset_are_recorded_as_their_own_undo_steps(): void
    {
        $editor = $this->editor();

        // A button is one complete operation, never merged into a typing step.
        $this->assertStringContainsString('this.commitColumnWidths(new Array(count).fill(100 / count), { coalesceKey: null })', $editor);
        $this->assertStringContainsString("this.updateProp('columns', undefined, { coalesceKey: null })", $editor);
        // A finished drag is also discrete.
        $this->assertStringContainsString("this.updateProp('columns', columns, { coalesceKey: null })", $editor);
    }

    public function test_undo_and_redo_push_the_restored_widths_to_the_preview(): void
    {
        $editor = $this->editor();

        $this->assertStringContainsString("const isColumnWidth = entry.path[0] === 'props' && entry.path[1] === 'columns'", $editor);
        $this->assertStringContainsString('if (isColumnWidth) this.pushColumnWidthsFor(entry.targetId)', $editor);
        $this->assertStringContainsString('this.columnRatioWidths(block)', $editor, 'cleared widths fall back to the ratio layout on undo/redo');
        // Focusing a width field opens a typing session; consecutive edits in it merge.
        $this->assertStringContainsString('@focus="beginColumnWidthEdit()"', $editor);
        $this->assertStringContainsString('columnWidthSession: 0', $editor);
        // A render reply from before the undo must not replace the new DOM.
        $this->assertStringContainsString('if (this.propRenderSeq[blockId] !== requestId) return;', $editor);
        $this->assertStringContainsString('JSON.stringify(current) !== snapshot', $editor);
        // A redundant commit contributes no undo step of its own.
        $this->assertStringContainsString("JSON.stringify(before) === JSON.stringify(value)", $editor);
    }

    public function test_reset_returns_to_the_ratio_layout_and_drops_manual_widths(): void
    {
        $editor = $this->editor();

        $this->assertStringContainsString('this.pushColumnWidths(this.columnRatioWidths())', $editor);
        $this->assertStringContainsString('delete this.selectedBlock.props.columns', $editor);
        $this->assertStringContainsString("action: 'setColumnWidths'", $editor);
    }

    public function test_inspector_edits_reach_the_preview_without_a_block_rerender(): void
    {
        $preview = $this->preview();

        $this->assertStringContainsString("case 'setColumnWidths':", $preview);
        $this->assertStringContainsString('function setBlockColumnWidths(', $preview);
        // A count mismatch is never guessed at; the block re-render wins.
        $this->assertStringContainsString('if (widths.length !== cells.length) return;', $preview);
        $this->assertStringContainsString('syncColumnResizers();', $preview);
    }

    public function test_the_protocol_only_accepts_well_formed_width_messages(): void
    {
        $this->assertContains('setColumnWidths', PreviewProtocol::TO_PREVIEW);

        $this->assertTrue(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => 'cols1',
            'columns' => [['width' => 65], ['width' => 35]],
        ], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => '', 'columns' => [['width' => 100]],
        ], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => 'cols1', 'columns' => [],
        ], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => 'cols1', 'columns' => [['width' => '60%']],
        ], 'to-preview'));
        $this->assertFalse(PreviewProtocol::validate([
            'action' => 'setColumnWidths', 'blockId' => 'cols1', 'columns' => [['width' => 60]],
        ], 'to-editor'));
    }
}
