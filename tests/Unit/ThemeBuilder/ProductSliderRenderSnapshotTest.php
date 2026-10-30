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
 *   Originally captured from the renderer BEFORE the Phase 9 / Step 2
 *   migration and re-verified after it — the same numbers, because that
 *   refactor changed no markup at all.
 *
 *   Phase 6.1-B RE-PINNED them, and this is the why: the once-per-document
 *   emission now ships TWO script tags (the controller AND the legacy
 *   adapter that registers the formerly inline-carried types against the
 *   SDK), and the structural hash keeps the <script> TAGS while normalizing
 *   only their bodies — so a second tag is a real, intended structural
 *   change. No other markup moved: carousel structure, attributes, classes
 *   and text are unchanged.
 *
 *   Phase 0.5 (ADR-064) RE-PINNED them again, and this is the why: the demo
 *   prices print through PersianNumber («۱٬۲۵۰٬۰۰۰ تومان», was
 *   «1,250,000 تومان») and the old-price / stock text colour reads
 *   var(--tb-text-muted, #6b7280) instead of the bare hex. Verified by a
 *   line diff of the HEAD and new renders: only those price strings and
 *   colour declarations differ — structure, classes and every other
 *   attribute are unchanged.
 *
 *   Phase 0.6 (ADR-065) RE-PINNED them again: the placeholder of a card
 *   without a photo is the catalog SVG (IconLibrary `image` / `package`)
 *   instead of the 🖼️ / 📦 emoji. Verified by mapping those SVGs back to
 *   the emoji: the result hashes to exactly the previous pins.
 *
 *   Phase 2 (ADR-067) RE-PINNED them again: the product card is the new
 *   commercial productCard() (discount badge with percentage, rating,
 *   stock pill, add-to-cart action) and the arrows are logical-side SVG
 *   chevrons styled by the shared sheet. Verified by rendering the HEAD
 *   package and this one with each slide's content and the two arrow
 *   buttons replaced by tokens: both modes hash identically, so nothing
 *   outside the cards and arrows moved.
 *
 * Re-pinned again in Phase 3 (ADR-068): the demo cards gained derived
 * badge overlays — «پرامتیاز» on the two cards rated 4.6/128 and 4.8/412,
 * and «جدید» moved from the free-text `primary` badge to `info`. Verified
 * by diffing the HEAD-package render with this one: those three badge
 * spans are the only change.
 */
class ProductSliderRenderSnapshotTest extends TestCase
{
    /** Neutral render (no forced mode, no editor_mode context). */
    private const NEUTRAL_STRUCTURAL_SHA256 = '419619285d3cf2ba1d5211971561681c689d55bb0c9a0bc4c4992f3447899c8c';

    /** MODE_PUBLIC — the real customer-site surface. */
    private const PUBLIC_STRUCTURAL_SHA256 = '587e1281de610f117751d83393c789ec6f7ccf018ba7d5cc1bf715afbbe36949';

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

    // ── Every carousel type ships the shared carrier (Phase 6.1-B) ───────

    /**
     * Phase 6.1-B completed the carousel migration: the per-instance inline
     * controller is DELETED, and every type — the four that used to keep it
     * byte-identical included — emits the shared assets. Each type must
     * carry the controller AND the adapter (which registers it against the
     * SDK and gives it the destroy() the inline copy never had), and the
     * inline controller must be gone for good. This test replaces
     * test_unmigrated_carousel_types_still_use_the_inline_controller(),
     * whose job was to BLOCK this exact flip until it happened deliberately.
     */
    public function test_every_carousel_type_ships_the_shared_carrier_and_the_adapter(): void
    {
        $types = [
            ['slider', ['slides' => [['title' => 'یک'], ['title' => 'دو']]]],
            ['category_slider', []],
            ['testimonials_slider', ['mode' => 'carousel', 'items' => [['name' => 'الف', 'text' => 'متن']]]],
            ['client_logos', ['mode' => 'carousel', 'logos' => [['name' => 'برند', 'image' => 'https://example.test/l.png']]]],
        ];

        foreach ($types as [$type, $props]) {
            BlockRenderer::resetCarouselScriptEmission();

            $html = (new BlockRenderer())->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);

            self::assertStringContainsString(
                '__tbCarouselController',
                $html,
                "{$type} must ship the shared controller"
            );
            self::assertStringContainsString(
                '__tbLegacyCarouselAdapter',
                $html,
                "{$type} must ship the adapter that registers it against the SDK"
            );
            self::assertStringNotContainsString(
                'root.dataset.tbCarouselInit',
                $html,
                "{$type} must no longer inline the per-instance controller (Phase 6.1-B deleted it)"
            );
        }
    }

    /**
     * Attribute integrity: the adapter reads the same data-* contract the
     * inline controller did. A flip that dropped any of these would render
     * a carousel that looks right and behaves wrong.
     */
    public function test_legacy_carousel_markup_keeps_the_data_attribute_contract(): void
    {
        BlockRenderer::resetCarouselScriptEmission();

        $html = (new BlockRenderer())->renderBlock(['type' => 'category_slider', 'id' => 'b1', 'props' => ['interval' => 2500]]);

        self::assertStringContainsString('data-autoplay=', $html);
        self::assertStringContainsString('data-interval="2500"', $html);
        self::assertStringContainsString('data-pause-hover=', $html);
        self::assertStringContainsString('data-fade=', $html);
    }
}
