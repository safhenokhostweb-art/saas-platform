<?php

namespace Tests\Unit\Models;

use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * THEME-03 (Full Product Commercial Audit, TASK 2A): Theme::getHeaderVariants()/
 * getFooterVariants() are the canonical, Theme-owned source of selectable
 * Header/Footer variants. Additive: a Theme authored before multi-variant
 * support existed has no `header_variants` row, and must still resolve to
 * exactly one real, non-fabricated variant built from its own existing
 * `header_blocks` — never an empty list, never placeholder content.
 */
class ThemeHeaderFooterVariantsTest extends TestCase
{
    // BASELINE ZERO PASS (test-pollution fix): this class created real
    // Theme::create() rows with no isolation trait at all — every row was a
    // real, permanently-committed INSERT with nothing to roll it back. The
    // two leaked rows (slug prefixes 'single-variant-'/'multi-variant-')
    // survived for the rest of any full-suite run as zero-release master
    // themes with no real pages, which is exactly what made
    // Tests\Feature\ThemeBuilder\ThemeReleaseBackfillCommandTest fail only
    // in full-suite context — theme-release:backfill scans ALL zero-release
    // master themes globally and its exit code reflects EVERY candidate,
    // not just the ones a given test itself created.
    use RefreshDatabase;

    public function test_a_theme_with_no_authored_variants_synthesizes_one_default_from_its_flat_blocks(): void
    {
        $theme = Theme::create([
            'name' => 'قالب تک‌متغیره', 'slug' => 'single-variant-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'header_blocks' => [['id' => 'h1', 'type' => 'nav_header', 'props' => ['logo_text' => 'X']]],
            'footer_blocks' => [['id' => 'f1', 'type' => 'nav_footer', 'props' => []]],
        ]);

        $headerVariants = $theme->getHeaderVariants();
        $footerVariants = $theme->getFooterVariants();

        $this->assertCount(1, $headerVariants);
        $this->assertCount(1, $footerVariants);
        $this->assertTrue($headerVariants[0]['is_default']);
        $this->assertSame($theme->header_blocks, $headerVariants[0]['blocks']);
        $this->assertSame($theme->footer_blocks, $footerVariants[0]['blocks']);
        $this->assertNotEmpty($headerVariants[0]['key']);
    }

    public function test_a_theme_with_explicit_variants_returns_them_unchanged(): void
    {
        $variants = [
            ['key' => 'v1', 'label' => 'نوع یک', 'blocks' => [['id' => 'a', 'type' => 'nav_header', 'props' => []]], 'is_default' => true],
            ['key' => 'v2', 'label' => 'نوع دو', 'blocks' => [['id' => 'b', 'type' => 'nav_menu', 'props' => []]], 'is_default' => false],
        ];

        $theme = Theme::create([
            'name' => 'قالب چندمتغیره', 'slug' => 'multi-variant-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'header_blocks' => [['id' => 'x', 'type' => 'nav_header', 'props' => []]],
            'header_variants' => $variants,
        ]);

        $this->assertSame($variants, $theme->getHeaderVariants());
    }
}
