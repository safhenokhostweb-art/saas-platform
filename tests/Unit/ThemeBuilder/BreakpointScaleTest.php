<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * WU-D breakpoint scale (sm 480, md 768, lg 1024, xl 1280) ratchet. Every
 * width in a viewport or container query of the shared widget runtime — the
 * two package stylesheets, header-modern.css, the renderer's inline <style>
 * and site-header.js's matchMedia — must be one of the scale's eight forms.
 *
 * The legacy (max-width: 768px) rules put a 768px iPad in phone mode while
 * every other rule treats 768px as tablet. Moving them would change existing
 * pages at that width (the zero-diff rule), so their number is pinned per
 * file: it may only go down.
 */
class BreakpointScaleTest extends TestCase
{
    private const RUNTIME = __DIR__ . '/../../../packages/saas-platform/modules/theme-runtime';

    private const ON_SCALE = [
        'max-width: 479.98px', 'max-width: 767.98px', 'max-width: 1023.98px', 'max-width: 1279.98px',
        'min-width: 480px', 'min-width: 768px', 'min-width: 1024px', 'min-width: 1280px',
    ];

    /** @return array<string, array{string, int}> */
    public static function sources(): array
    {
        return [
            'blocks sheet' => ['resources/css/theme-runtime-blocks.css', 0],
            // Timeline, commercial/trust grids, dynamic widget.
            'contract sheet' => ['resources/css/theme-runtime-contract.css', 3],
            'header-modern sheet' => ['resources/js/blocks/header-modern/header-modern.css', 0],
            // renderColumns() stacks its children at 768px and below.
            'renderer' => ['src/BlockRenderer.php', 1],
            'site-header script' => ['resources/js/blocks/site-header.js', 0],
        ];
    }

    #[DataProvider('sources')]
    public function test_every_query_width_is_on_the_scale(string $file, int $legacy768): void
    {
        $widths = $this->queryWidths((string) file_get_contents(self::RUNTIME . '/' . $file));
        $this->assertNotSame([], $widths, "{$file}: found no queries; the extractor is broken.");

        $off = array_values(array_filter($widths, fn (string $w) => !in_array($w, self::ON_SCALE, true) && $w !== 'max-width: 768px'));
        $this->assertSame([], $off, "{$file}: off-scale query width(s). Use one of: " . implode(', ', self::ON_SCALE) . '.');

        $this->assertSame(
            $legacy768,
            count(array_keys($widths, 'max-width: 768px', true)),
            "{$file}: the legacy (max-width: 768px) count changed. New rules use 767.98px; if you moved one, lower the pin."
        );
    }

    public function test_the_extractor_reads_media_container_and_match_media_forms(): void
    {
        $src = "@media(max-width:640px){a{}} @media (min-width: 1024px) and (hover: hover){} @container tb-slot (max-width:479.98px){}"
            . " .x{max-width:72rem} root.matchMedia('(hover: hover) and (min-width: 1025px)')";

        $this->assertSame(['max-width: 640px', 'min-width: 1024px', 'max-width: 479.98px', 'min-width: 1025px'], $this->queryWidths($src));
    }

    public function test_columns_and_layout_grid_children_are_tb_slot_containers(): void
    {
        $css = (string) file_get_contents(self::RUNTIME . '/resources/css/theme-runtime-contract.css');

        $this->assertMatchesRegularExpression('/\.tb-columns > :not\(style\):not\(:has\([^)]*\.tb-modal-overlay[^)]*\)\),\s*\.tb-layout-grid > :not\(style\):not\(:has\([^)]*\)\) \{ container: tb-slot \/ inline-size; \}/', $css);
        $this->assertStringContainsString('@container tb-slot (max-width: 767.98px)', $css);
        $this->assertStringContainsString('@container tb-slot (max-width: 479.98px)', $css);
    }

    public function test_grid_layout_block_is_marked_and_card_grids_are_not(): void
    {
        $renderer = new BlockRenderer();

        $this->assertStringContainsString(
            'class="tb-grid tb-grid-cols-3 tb-gap-md tb-layout-grid"',
            $renderer->renderBlock(['type' => 'grid', 'id' => 'g1', 'props' => ['columns' => 3]])
        );
        $this->assertStringNotContainsString(
            'tb-layout-grid',
            $renderer->renderBlock(['type' => 'features_grid', 'id' => 'f1', 'props' => []])
        );
    }

    public function test_carousel_item_counts_follow_the_viewport_and_the_slot(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'slider', 'id' => 'c1', 'props' => [
            'slides' => [['image' => '', 'title' => 'یک'], ['image' => '', 'title' => 'دو']],
        ]]);

        $this->assertStringContainsString('@media (max-width:1023.98px){ #tb-c1-carousel{--tb-items:var(--tb-items-tablet);} }', $this->normalizeId($html));
        $this->assertStringContainsString('@media (max-width:479.98px){ #tb-c1-carousel{--tb-items:var(--tb-items-mobile);} }', $this->normalizeId($html));
        $this->assertStringContainsString('@container tb-slot (max-width:1023.98px){ #tb-c1-carousel{--tb-items:var(--tb-items-tablet);} }', $this->normalizeId($html));
        $this->assertStringContainsString('@container tb-slot (max-width:479.98px){ #tb-c1-carousel{--tb-items:var(--tb-items-mobile);} }', $this->normalizeId($html));
    }

    public function test_style_responsive_overrides_use_the_scale(): void
    {
        $html = (new BlockRenderer())->renderBlock([
            'type' => 'heading', 'id' => 'h1', 'props' => ['text' => 'عنوان'],
            'responsive' => ['tablet' => ['bgColor' => '#ffffff'], 'mobile' => ['bgColor' => '#000000']],
        ]);

        $this->assertStringContainsString('@media(max-width:1023.98px){.tb-blk-h1{', $html);
        $this->assertStringContainsString('@media(max-width:479.98px){.tb-blk-h1{', $html);
    }

    /**
     * Widths of every @media/@container prelude and matchMedia() argument,
     * normalised to "min|max-width: <n><unit>".
     *
     * @return list<string>
     */
    private function queryWidths(string $source): array
    {
        preg_match_all('/@(?:media|container)\b([^{]*)\{|matchMedia\(\s*[\'"]([^\'"]*)[\'"]/', $source, $m, PREG_SET_ORDER);
        $widths = [];
        foreach ($m as $match) {
            $prelude = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');
            preg_match_all('/\b(min|max)-width\s*:\s*([0-9.]+)(px|rem|em)\b/', $prelude, $w, PREG_SET_ORDER);
            foreach ($w as $one) {
                $widths[] = "{$one[1]}-width: {$one[2]}{$one[3]}";
            }
        }

        return $widths;
    }

    /** The slider's carousel id carries a suffix; map it to a fixed one. */
    private function normalizeId(string $html): string
    {
        return (string) preg_replace('/#tb-c1[A-Za-z0-9_-]*\{/', '#tb-c1-carousel{', $html);
    }
}
