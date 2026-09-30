<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * Phase 9 / Step 2 — the render snapshot that guards the `product_slider`
 * carousel migration to the shared-asset carrier.
 *
 * WHY A STRUCTURAL HASH AND NOT A RAW HASH
 *   The migration deliberately replaces one thing: the per-instance inline
 *   controller script. A raw sha256 would therefore have to be re-pinned on
 *   every script edit, which is exactly the kind of ritual that trains people
 *   to re-pin without reading. So the hash is taken over the markup with
 *   <script> BODIES NORMALIZED AWAY: any change to structure, attributes,
 *   class names, ordering or text fails loudly, while the one intended
 *   change (which script carries the behaviour) stays free to move.
 *
 *   The carrier is not left unverified by that, though — the tests below
 *   assert exactly what the carrier IS, so a page cannot quietly end up
 *   with no script at all.
 *
 * WHY TWO MODES
 *   The carousel's markup is identical in every mode; only the block wrapper
 *   differs. But the wrapper is what the SDK binds to, so the public mode
 *   must be pinned too — a migration that broke public binding would leave
 *   the neutral snapshot green and every live site broken.
 *
 * THE PINNED VALUES
 *   Captured from the renderer BEFORE the migration, then re-verified after
 *   it. They are the same numbers either way, which is the actual claim:
 *   this refactor changed no markup at all.
 */
class ProductSliderRenderSnapshotTest extends TestCase
{
    /** Neutral render (no forced mode, no editor_mode context). */
    private const NEUTRAL_STRUCTURAL_SHA256 = '4d638c2999efe680b170628c22df0798c8249c337f0fa1fb0ee6e75645d7f95a';

    /** MODE_PUBLIC — the real customer-site surface. */
    private const PUBLIC_STRUCTURAL_SHA256 = '154b0565a7aed917a998d34dea997670c1da834cf54481b8d77c78d8181935db';

    private function render(string $mode = 'neutral'): string
    {
        BlockRenderer::resetCarouselScriptEmission();

        $renderer = new BlockRenderer();
        if ($mode !== 'neutral') {
            $renderer->setMode($mode);
        }

        return $renderer->renderBlock(['type' => 'product_slider', 'id' => 'b1', 'props' => []]);
    }

    /**
     * The documented allowlist: strip <script> BODIES, keep every tag and
     * attribute. The `<script>` tag itself survives, so losing or gaining a
     * script tag is still a visible change — only its contents are free.
     */
    private function structural(string $html): string
    {
        return (string) preg_replace('#<script\b[^>]*>.*?</script>#s', '<script></script>', $html);
    }

    // ── The regression gate ──────────────────────────────────────────────

    public function test_product_slider_markup_is_unchanged_by_the_carousel_migration(): void
    {
        self::assertSame(
            self::NEUTRAL_STRUCTURAL_SHA256,
            hash('sha256', $this->structural($this->render())),
            'product_slider structural markup drifted. If this is an intended change, update the pin AND say why here — do not just re-pin.'
        );
    }

    public function test_product_slider_public_markup_is_unchanged_by_the_carousel_migration(): void
    {
        self::assertSame(
            self::PUBLIC_STRUCTURAL_SHA256,
            hash('sha256', $this->structural($this->render('public'))),
            'product_slider MODE_PUBLIC structural markup drifted — this is the surface every live tenant site renders.'
        );
    }

    // ── The carrier is asserted, not merely exempted ─────────────────────

    public function test_product_slider_ships_the_shared_carousel_asset_and_not_the_inline_controller(): void
    {
        $html = $this->render();

        self::assertStringContainsString(
            '__tbCarouselController',
            $html,
            'the shared asset must be the carrier, not the per-instance inline script'
        );
        self::assertStringNotContainsString(
            "init(document.getElementById('tb-b1'))",
            $html,
            'the pre-migration inline controller must be gone for the migrated type'
        );
    }

    public function test_the_shared_carousel_asset_is_emitted_once_per_document_not_once_per_carousel(): void
    {
        BlockRenderer::resetCarouselScriptEmission();

        $first  = (new BlockRenderer())->renderBlock(['type' => 'product_slider', 'id' => 'a', 'props' => []]);
        $second = (new BlockRenderer())->renderBlock(['type' => 'product_slider', 'id' => 'b', 'props' => []]);

        // Counted with >0 / ==0 rather than ==1: the marker legitimately
        // appears more than once inside the asset itself, so pinning an exact
        // count would break on a harmless edit to the controller.
        self::assertGreaterThan(0, substr_count($first, '__tbCarouselController'), 'first carousel carries the asset');
        self::assertSame(0, substr_count($second, '__tbCarouselController'), 'the second carousel must reuse the first emission, not re-inline 11 KB');
    }

    /**
     * The regression the mode-keyed emission flag exists to prevent: a single
     * process that renders both modes must give BOTH documents a carrier. A
     * plain boolean flag let the first render starve the second, and a
     * carousel with no script renders its arrows and then does nothing.
     */
    public function test_rendering_both_modes_in_one_process_still_emits_a_carrier_for_each(): void
    {
        BlockRenderer::resetCarouselScriptEmission();

        $neutral = (new BlockRenderer())->renderBlock(['type' => 'product_slider', 'id' => 'b1', 'props' => []]);

        $public = new BlockRenderer();
        $public->setMode('public');
        $publicHtml = $public->renderBlock(['type' => 'product_slider', 'id' => 'b1', 'props' => []]);

        self::assertStringContainsString('__tbCarouselController', $neutral);
        self::assertStringContainsString(
            '__tbCarouselController',
            $publicHtml,
            'the public document must not be left without the carousel controller'
        );
    }

    // ── The public surface must stay SDK-bindable ────────────────────────

    /**
     * The SDK binds to the block wrapper the renderer emits, NOT to the
     * carousel element. If that wrapper ever stops carrying the type, the
     * carousel still renders and still looks correct — it simply never
     * becomes interactive. This is the assertion that catches it.
     */
    public function test_the_public_block_wrapper_still_carries_the_sdk_binding_attributes(): void
    {
        BlockRenderer::resetCarouselScriptEmission();

        $renderer = new BlockRenderer();
        $renderer->setMode('public');
        $html = $renderer->renderBlock(['type' => 'product_slider', 'id' => 'b1', 'props' => ['columns' => 4]]);

        self::assertStringContainsString('data-tb-block-type="product_slider"', $html);
        self::assertStringContainsString('data-tb-block-id="b1"', $html);
        self::assertStringContainsString(
            'data-tb-block-props=',
            $html,
            'authored props must reach the client so ctx.props is not permanently empty'
        );
        // The attribute is HTML-escaped, so the payload arrives as
        // {&quot;columns&quot;:4} on the wire and is parsed back to JSON by the
        // runtime.
        self::assertStringContainsString('{&quot;columns&quot;:4}', $html);
    }

    // ── The un-migrated types must be untouched ──────────────────────────

    /**
     * The whole point of the opt-in is that the other carousel types keep
     * byte-identical output. This fails if anyone "helpfully" points them at
     * the shared carrier, or edits the inline copy, without migrating them.
     */
    public function test_unmigrated_carousel_types_still_use_the_inline_controller(): void
    {
        foreach ([['slider', ['slides' => [['title' => 'یک'], ['title' => 'دو']]]],
                  ['category_slider', []],
                  ['testimonials_slider', ['mode' => 'carousel', 'items' => [['name' => 'الف', 'text' => 'متن']]]]] as [$type, $props]) {
            $html = (new BlockRenderer())->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);

            // The inline copy's own init guard, matched rather than the id:
            // each caller names its carousel element differently
            // (category_slider appends '-track'), so the id is not a stable
            // marker to assert on.
            self::assertStringContainsString(
                'root.dataset.tbCarouselInit',
                $html,
                "{$type} has not been migrated yet and must keep its inline controller"
            );
            self::assertStringNotContainsString('__tbCarouselController', $html, "{$type} must not silently adopt the shared carrier");
        }
    }
}
