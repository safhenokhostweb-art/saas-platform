<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRenderer as SharedBlockRenderer;
use Tests\TestCase;

/**
 * Smart Public Rendering — dual-mode rendering contract.
 *
 * MODE_PUBLIC must retain the STRUCTURAL attributes the JS runtime
 * addresses (data-tb-block-id / data-tb-block-type / data-tb-container)
 * while emitting ZERO editor-only output (data-tb-editable,
 * data-tb-inline-type, the canvas selection overlay, drag handle, block
 * badge) — even when an editor_mode context flag leaks into the request.
 * MODE_EDITOR/default rendering must remain byte-identical to the
 * historical behavior.
 */
class BlockRendererPublicModeTest extends TestCase
{
    public function test_public_mode_retains_structural_attributes_for_runtime(): void
    {
        $renderer = (new BlockRenderer())->setMode(SharedBlockRenderer::MODE_PUBLIC);

        $html = $renderer->renderBlock([
            'type' => 'section',
            'id' => 'sec1',
            'children' => [
                ['type' => 'text', 'id' => 'txt1', 'props' => ['content' => '<p>سلام</p>']],
            ],
        ]);

        // Structural attributes the runtime requires (task spec):
        $this->assertStringContainsString('data-tb-block-id="sec1"', $html);
        $this->assertStringContainsString('data-tb-block-type="section"', $html);
        $this->assertStringContainsString('data-tb-container="1"', $html);
        // Nested blocks are addressable too:
        $this->assertStringContainsString('data-tb-block-id="txt1"', $html);
        $this->assertStringContainsString('data-tb-block-type="text"', $html);
    }

    public function test_public_mode_never_emits_editor_output_even_with_editor_mode_flag(): void
    {
        // The leak scenario the mode gate exists for: a host context that
        // still carries editor_mode=true must NOT re-enable editor output
        // once the document mode is forced to public.
        $renderer = (new BlockRenderer())
            ->setContext(['editor_mode' => true])
            ->setMode(SharedBlockRenderer::MODE_PUBLIC);

        $html = $renderer->renderBlock([
            'type' => 'hero',
            'id' => 'h1',
            'props' => ['title' => 'تست', 'subtitle' => 'زیرعنوان', 'button_text' => 'برو'],
        ]);

        // Editor-only output — none of it may exist in public mode:
        $this->assertStringNotContainsString('data-tb-editable', $html);
        $this->assertStringNotContainsString('data-tb-inline-type', $html);
        $this->assertStringNotContainsString('tb-canvas-block', $html);
        $this->assertStringNotContainsString('tb-drag-handle', $html);
        $this->assertStringNotContainsString('tb-block-badge', $html);
        // …while the structural wrapper IS present:
        $this->assertStringContainsString('data-tb-block-id="h1"', $html);
        $this->assertStringContainsString('data-tb-block-type="hero"', $html);
    }

    public function test_public_mode_strips_editor_attributes_smuggled_through_custom_renderers(): void
    {
        // Defense in depth: the final-pass stripper must remove editor
        // attributes that did not come from this class's own gates (here: a
        // host module_renderer emitting raw editor markup).
        $renderer = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->setContext([
                'module_renderer' => fn (): string =>
                    '<div data-tb-editable="title" data-tb-inline-type="text" data-tb-edit-action="remove">محصول</div>',
            ]);

        $html = $renderer->renderBlock(['type' => 'module:products', 'id' => 'm1', 'props' => []]);

        $this->assertStringNotContainsString('data-tb-editable', $html);
        $this->assertStringNotContainsString('data-tb-inline-type', $html);
        $this->assertStringNotContainsString('data-tb-edit-action', $html);
        // Legitimate module content survives the strip:
        $this->assertStringContainsString('محصول', $html);
        $this->assertStringContainsString('data-tb-block-id="m1"', $html);
    }

    public function test_public_mode_strips_editor_chrome_smuggled_into_content(): void
    {
        $renderer = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->setContext([
                'module_renderer' => fn (): string =>
                    '<div class="tb-canvas-block" data-tb-block-id="x">'
                    . '<button type="button" class="tb-drag-handle">⋮⋮</button>'
                    . '<div class="tb-block-badge">hero</div>'
                    . '<p>بدنه</p></div>',
            ]);

        $html = $renderer->renderBlock(['type' => 'module:gallery', 'id' => 'm2', 'props' => []]);

        $this->assertStringNotContainsString('tb-drag-handle', $html);
        $this->assertStringNotContainsString('tb-block-badge', $html);
        $this->assertStringContainsString('بدنه', $html);
    }

    public function test_public_mode_does_not_render_editor_empty_container_hint(): void
    {
        $block = ['type' => 'flex_container', 'id' => 'fc1', 'children' => []];

        $public = (new BlockRenderer())->setMode(SharedBlockRenderer::MODE_PUBLIC)->renderBlock($block);
        $editor = (new BlockRenderer())->setContext(['editor_mode' => true])->renderBlock($block);

        $this->assertStringNotContainsString('tb-fc-empty', $public);
        $this->assertStringContainsString('tb-fc-empty', $editor);
    }

    public function test_editor_mode_unchanged_and_public_structural_wrapper_absent_by_default(): void
    {
        $block = ['type' => 'hero', 'id' => 'h2', 'props' => ['title' => 'تست']];

        // Historical editor rendering is intact:
        $editor = (new BlockRenderer())->setContext(['editor_mode' => true])->renderBlock($block);
        $this->assertStringContainsString('tb-canvas-block', $editor);
        $this->assertStringContainsString('data-tb-editable', $editor);

        // Default (no mode, no flag) gains NO structural wrapper — the
        // historical public/preview output is byte-identical:
        $default = (new BlockRenderer())->renderBlock($block);
        $this->assertStringNotContainsString('data-tb-block-id', $default);
        $this->assertStringNotContainsString('data-tb-block-type', $default);
        $this->assertStringContainsString('tb-hero', $default);
    }

    public function test_set_context_mode_key_forces_the_mode(): void
    {
        $renderer = (new BlockRenderer())->setContext(['mode' => SharedBlockRenderer::MODE_PUBLIC]);

        $html = $renderer->renderBlock(['type' => 'text', 'id' => 't1', 'props' => ['content' => 'متن']]);

        $this->assertStringContainsString('data-tb-block-id="t1"', $html);
        $this->assertStringNotContainsString('tb-canvas-block', $html);
    }

    public function test_unknown_mode_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BlockRenderer())->setMode('preview');
    }

    public function test_mode_constants_match_the_document_contract(): void
    {
        $this->assertSame(
            \App\Services\ThemeCanvas\ThemeRuntimeDocument::MODE_PUBLIC,
            SharedBlockRenderer::MODE_PUBLIC
        );
        $this->assertSame(
            \App\Services\ThemeCanvas\ThemeRuntimeDocument::MODE_EDITOR,
            SharedBlockRenderer::MODE_EDITOR
        );
    }
}
