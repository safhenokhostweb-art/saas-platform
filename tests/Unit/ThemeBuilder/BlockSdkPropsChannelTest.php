<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRenderer as SharedBlockRenderer;
use SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider;
use Tests\TestCase;

/**
 * Phase 9 / Step 1 — the server half of the BaseBlock SDK contract.
 *
 * ThemeCanvasRuntime.initBlock() has always read `data-tb-block-props` to
 * build `ctx.props`, but no renderer ever emitted the attribute, so the SDK's
 * documented prop channel was permanently empty. These tests pin the
 * additive fix and its two hard constraints:
 *
 *   1. The attribute rides the SAME wrapper that already carries
 *      data-tb-block-id / -type, in BOTH editor and public mode, so the two
 *      wrappers stay a pair and the runtime keeps one addressing contract.
 *   2. A block with no props of its own renders byte-identically to before —
 *      no empty attribute, no golden-output churn.
 */
class BlockSdkPropsChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SharedBlockRenderer::resetReferenceBlockScriptEmission();
    }

    private function propsOf(string $html, string $blockId): ?array
    {
        $pattern = '/data-tb-block-id="' . preg_quote($blockId, '/') . '"[^>]*data-tb-block-props="([^"]*)"/';
        if (!preg_match($pattern, $html, $m)) {
            return null;
        }
        $decoded = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), true);
        $this->assertIsArray($decoded, 'data-tb-block-props must carry valid JSON');

        return $decoded;
    }

    public function test_public_mode_emits_the_props_channel(): void
    {
        $html = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->renderBlock([
                'type' => 'hero',
                'id' => 'h1',
                'props' => ['title' => 'تست', 'subtitle' => 'زیرعنوان'],
            ]);

        $this->assertSame(
            ['title' => 'تست', 'subtitle' => 'زیرعنوان'],
            $this->propsOf($html, 'h1'),
        );
    }

    public function test_editor_mode_emits_the_same_props_channel(): void
    {
        $html = (new BlockRenderer())
            ->setContext(['editor_mode' => true])
            ->renderBlock(['type' => 'text', 'id' => 't1', 'props' => ['content' => 'متن']]);

        $this->assertSame(['content' => 'متن'], $this->propsOf($html, 't1'));
    }

    public function test_a_block_with_no_props_emits_no_attribute(): void
    {
        // The no-regression invariant: existing content must not gain an
        // empty attribute it never had.
        $html = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->renderBlock(['type' => 'button', 'id' => 'b1', 'props' => []]);

        $this->assertStringContainsString('data-tb-block-id="b1"', $html);
        $this->assertStringNotContainsString('data-tb-block-props', $html);
    }

    public function test_nested_children_are_never_serialised_into_a_parent_attribute(): void
    {
        // renderBlocks() copies the child tree into $props['blocks'] before
        // dispatch so structural renderers can read it. That tree is already
        // rendered into $inner; re-serialising it would duplicate the entire
        // subtree into a DOM attribute on every container block.
        $html = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->renderBlock([
                'type' => 'section',
                'id' => 'sec1',
                'props' => ['padding' => 'lg'],
                'children' => [
                    ['type' => 'text', 'id' => 'txt1', 'props' => ['content' => 'سلام']],
                ],
            ]);

        $props = $this->propsOf($html, 'sec1');
        $this->assertIsArray($props);
        $this->assertArrayNotHasKey('blocks', $props, 'the child tree must stay out of the attribute');
        $this->assertSame('lg', $props['padding']);
        // And the child itself carries its own channel:
        $this->assertSame(['content' => 'سلام'], $this->propsOf($html, 'txt1'));
    }

    public function test_props_values_cannot_break_out_of_the_attribute(): void
    {
        $hostile = '"><script>alert(1)</script><div a="';
        $html = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->renderBlock(['type' => 'text', 'id' => 'x1', 'props' => ['content' => $hostile]]);

        // The raw payload must never appear unescaped in the attribute...
        $this->assertStringNotContainsString('"><script>alert(1)</script><div a="', $html);
        // ...and it must still round-trip to exactly what was authored.
        $this->assertSame($hostile, $this->propsOf($html, 'x1')['content']);
    }

    public function test_unicode_props_survive_the_attribute_unescaped(): void
    {
        $html = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->renderBlock(['type' => 'text', 'id' => 'u1', 'props' => ['content' => 'سلام دنیا']]);

        $this->assertStringContainsString('سلام دنیا', $html);
        $this->assertSame('سلام دنیا', $this->propsOf($html, 'u1')['content']);
    }

    public function test_reference_block_renders_through_the_sdk_contract(): void
    {
        $html = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->renderBlock([
                'type' => 'reference_counter',
                'id' => 'rc1',
                'props' => ['step' => 2, 'min' => 0, 'max' => 10, 'value' => 4],
            ]);

        // The block is server-rendered and complete on its own: the SDK
        // script only layers interactivity on top.
        $this->assertStringContainsString('tb-ref-counter', $html);
        $this->assertStringContainsString('tb-ref-counter__value', $html);
        $this->assertStringContainsString('data-tb-block-type="reference_counter"', $html);

        $props = $this->propsOf($html, 'rc1');
        $this->assertSame(2, $props['step']);
        $this->assertSame(10, $props['max']);
        $this->assertSame(4, $props['value']);
    }

    public function test_reference_block_clamps_an_out_of_range_start_value(): void
    {
        $html = (new BlockRenderer())
            ->setMode(SharedBlockRenderer::MODE_PUBLIC)
            ->renderBlock([
                'type' => 'reference_counter',
                'id' => 'rc2',
                'props' => ['min' => 0, 'max' => 5, 'value' => 99],
            ]);

        // The RENDERED value is clamped...
        $this->assertStringContainsString('>5</span>', $html);
        // ...while the props channel stays faithful to what was authored: it
        // is the block's configuration, and the client applies the same clamp
        // (see reference-counter.js) rather than the server silently editing
        // an author's input.
        $this->assertSame(99, $this->propsOf($html, 'rc2')['value']);
    }

    public function test_reference_block_script_is_emitted_once_per_document(): void
    {
        $renderer = (new BlockRenderer())->setMode(SharedBlockRenderer::MODE_PUBLIC);
        $first = $renderer->renderBlock(['type' => 'reference_counter', 'id' => 'a', 'props' => []]);
        $second = $renderer->renderBlock(['type' => 'reference_counter', 'id' => 'b', 'props' => []]);

        $this->assertStringContainsString('ThemeBlockSDK', $first);
        $this->assertStringNotContainsString('ThemeBlockSDK', $second, 'the inlined script must not repeat per instance');
    }

    public function test_reference_block_is_not_a_registered_palette_type(): void
    {
        // It is a documentation artifact, not a product block: registering it
        // would change BlockRegistry::all()'s cardinality and put a demo in
        // the editor palette. Dispatch still works because renderBlock()
        // resolves render{Studly}() by method existence.
        $registry = \SaaSPlatform\ThemeRuntime\BlockRegistry::all();
        $this->assertArrayNotHasKey('reference_counter', $registry);
    }

    public function test_fast_path_allowlist_keeps_countdown_slow_and_limits_modern_header_to_colors(): void
    {
        $editor = file_get_contents(resource_path('views/admin/theme-builder/canvas.blade.php'));
        $counter = file_get_contents(ThemeRuntimeServiceProvider::referenceBlockScriptPath());

        $this->assertIsString($editor);
        $this->assertIsString($counter);
        $this->assertStringContainsString("const FAST_PATH_BLOCK_TYPES = new Set(['reference_counter', 'header_modern_v1']);", $editor);
        $this->assertStringContainsString("['header_modern_v1', new Set(['bgColor', 'textColor', 'ctaColor'])]", $editor);
        $this->assertStringContainsString('(!fastPathKeys || fastPathKeys.has(propKey))', $editor);
        $this->assertStringContainsString('this.pushFastPropUpdate(targetId, propKey, value);', $editor);
        $this->assertStringContainsString('/canvas/render-block', $editor);
        $this->assertStringNotContainsString("new Set(['countdown'])", $editor);
        $this->assertStringContainsString('ctx.on(EVENTS.STATE_UPDATE', $counter);
        $this->assertStringContainsString('self.paint();', $counter);
    }

    public function test_packaged_block_assets_exist_where_the_provider_says_they_do(): void
    {
        foreach ([
            ThemeRuntimeServiceProvider::blockSdkContractPath(),
            ThemeRuntimeServiceProvider::referenceBlockScriptPath(),
            ThemeRuntimeServiceProvider::headerModernScriptPath(),
            ThemeRuntimeServiceProvider::headerModernStylePath(),
            ThemeRuntimeServiceProvider::headerModernDefinitionPath(),
        ] as $path) {
            $this->assertFileExists($path);
        }
    }
}
