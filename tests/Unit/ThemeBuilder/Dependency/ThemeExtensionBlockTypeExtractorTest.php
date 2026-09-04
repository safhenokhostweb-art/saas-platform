<?php

namespace Tests\Unit\ThemeBuilder\Dependency;

use App\Services\ThemeBuilder\Dependency\ThemeExtensionBlockTypeExtractor;
use Tests\TestCase;

/**
 * Phase 3G-B-3 — structural recursion tests for the pure Theme snapshot →
 * ext:* block-type scanner. Traversal shapes exercised here mirror
 * RenderIntegrityCheck::checkBlockList() exactly (children, blocks,
 * props.blocks, props.tabs[].blocks, props.items[].blocks for accordion,
 * props.slides[]) — the one production-proven shape of a real SaaS Theme
 * snapshot.
 */
class ThemeExtensionBlockTypeExtractorTest extends TestCase
{
    private ThemeExtensionBlockTypeExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ThemeExtensionBlockTypeExtractor();
    }

    public function test_no_blocks_yields_no_extension_types(): void
    {
        $this->assertSame([], $this->extractor->extractFromSnapshot([], [], []));
    }

    public function test_built_in_only_theme_yields_no_extension_types(): void
    {
        $header = [['type' => 'nav_menu', 'id' => 'h1', 'props' => []]];
        $footer = [['type' => 'social_icons', 'id' => 'f1', 'props' => []]];
        $pages = ['home' => ['blocks' => [
            ['type' => 'section', 'id' => 's1', 'props' => [], 'blocks' => [
                ['type' => 'heading', 'id' => 's1a', 'props' => []],
            ]],
        ]]];

        $this->assertSame([], $this->extractor->extractFromSnapshot($header, $footer, $pages));
    }

    public function test_one_top_level_ext_block_is_found(): void
    {
        $pages = ['home' => ['blocks' => [
            ['type' => 'ext:acme:gallery', 'id' => 'e1', 'props' => []],
        ]]];

        $this->assertSame(['ext:acme:gallery'], $this->extractor->extractFromSnapshot([], [], $pages));
    }

    public function test_duplicate_ext_block_occurrence_is_deduped(): void
    {
        $pages = ['home' => ['blocks' => [
            ['type' => 'ext:acme:gallery', 'id' => 'e1', 'props' => []],
            ['type' => 'ext:acme:gallery', 'id' => 'e2', 'props' => []],
        ]]];

        $this->assertSame(['ext:acme:gallery'], $this->extractor->extractFromSnapshot([], [], $pages));
    }

    public function test_deeply_nested_ext_block_via_children_is_found(): void
    {
        $pages = ['home' => ['blocks' => [
            ['type' => 'section', 'id' => 's1', 'children' => [
                ['type' => 'section', 'id' => 's2', 'children' => [
                    ['type' => 'section', 'id' => 's3', 'children' => [
                        ['type' => 'ext:acme:deep', 'id' => 'e1'],
                    ]],
                ]],
            ]],
        ]]];

        $this->assertSame(['ext:acme:deep'], $this->extractor->extractFromSnapshot([], [], $pages));
    }

    public function test_ext_block_inside_built_in_container_is_found(): void
    {
        $pages = ['home' => ['blocks' => [
            ['type' => 'section', 'id' => 's1', 'blocks' => [
                ['type' => 'ext:acme:card', 'id' => 'e1'],
            ]],
        ]]];

        $this->assertSame(['ext:acme:card'], $this->extractor->extractFromSnapshot([], [], $pages));
    }

    public function test_built_in_block_inside_ext_container_is_found(): void
    {
        $pages = ['home' => ['blocks' => [
            ['type' => 'ext:acme:container', 'id' => 'e1', 'blocks' => [
                ['type' => 'heading', 'id' => 'b1'],
            ]],
        ]]];

        $this->assertSame(['ext:acme:container'], $this->extractor->extractFromSnapshot([], [], $pages));
    }

    public function test_ext_inside_ext_inside_built_in_is_found(): void
    {
        $pages = ['home' => ['blocks' => [
            ['type' => 'section', 'id' => 's1', 'blocks' => [
                ['type' => 'ext:acme:outer', 'id' => 'e1', 'blocks' => [
                    ['type' => 'ext:acme:inner', 'id' => 'e2'],
                ]],
            ]],
        ]]];

        $types = $this->extractor->extractFromSnapshot([], [], $pages);
        sort($types);
        $this->assertSame(['ext:acme:inner', 'ext:acme:outer'], $types);
    }

    public function test_header_extension_block_is_found(): void
    {
        $header = [['type' => 'ext:acme:header_widget', 'id' => 'h1']];

        $this->assertSame(['ext:acme:header_widget'], $this->extractor->extractFromSnapshot($header, [], []));
    }

    public function test_footer_extension_block_is_found(): void
    {
        $footer = [['type' => 'ext:acme:footer_widget', 'id' => 'f1']];

        $this->assertSame(['ext:acme:footer_widget'], $this->extractor->extractFromSnapshot([], $footer, []));
    }

    public function test_page_extension_block_is_found(): void
    {
        $pages = ['about' => ['blocks' => [['type' => 'ext:acme:team', 'id' => 'p1']]]];

        $this->assertSame(['ext:acme:team'], $this->extractor->extractFromSnapshot([], [], $pages));
    }

    public function test_multiple_pages_are_all_scanned(): void
    {
        $pages = [
            'home' => ['blocks' => [['type' => 'ext:acme:home_widget', 'id' => 'p1']]],
            'about' => ['blocks' => [['type' => 'ext:foo:about_widget', 'id' => 'p2']]],
        ];

        $types = $this->extractor->extractFromSnapshot([], [], $pages);
        sort($types);
        $this->assertSame(['ext:acme:home_widget', 'ext:foo:about_widget'], $types);
    }

    public function test_tabs_accordion_and_slides_nesting_are_all_scanned(): void
    {
        $pages = ['home' => ['blocks' => [
            ['type' => 'tabs', 'id' => 't1', 'props' => ['tabs' => [
                ['label' => 'Tab A', 'blocks' => [['type' => 'ext:acme:in_tab', 'id' => 't1a']]],
            ]]],
            ['type' => 'accordion', 'id' => 'a1', 'props' => ['items' => [
                ['label' => 'Item A', 'blocks' => [['type' => 'ext:acme:in_accordion', 'id' => 'a1a']]],
            ]]],
            ['type' => 'carousel', 'id' => 'c1', 'props' => ['slides' => [
                ['type' => 'ext:acme:in_slide', 'id' => 'c1a'],
            ]]],
            ['type' => 'section', 'id' => 's1', 'props' => ['blocks' => [
                ['type' => 'ext:acme:in_props_blocks', 'id' => 's1a'],
            ]]],
        ]]];

        $types = $this->extractor->extractFromSnapshot([], [], $pages);
        sort($types);
        $this->assertSame([
            'ext:acme:in_accordion',
            'ext:acme:in_props_blocks',
            'ext:acme:in_slide',
            'ext:acme:in_tab',
        ], $types);
    }

    public function test_arbitrary_supported_nesting_depth_has_no_fixed_limit(): void
    {
        $node = ['type' => 'ext:acme:bottom', 'id' => 'bottom'];
        for ($i = 0; $i < 25; $i++) {
            $node = ['type' => 'section', 'id' => "wrap{$i}", 'children' => [$node]];
        }

        $pages = ['home' => ['blocks' => [$node]]];

        $this->assertSame(['ext:acme:bottom'], $this->extractor->extractFromSnapshot([], [], $pages));
    }

    public function test_malformed_unrelated_structures_are_ignored_safely(): void
    {
        $pages = [
            'home' => ['blocks' => [
                'not-an-array-block',
                ['id' => 'no-type'],
                ['type' => 123, 'id' => 'non-string-type'],
                ['type' => 'section', 'id' => 's1', 'children' => 'not-an-array'],
                ['type' => 'section', 'id' => 's2', 'props' => 'not-an-array'],
                ['type' => 'tabs', 'id' => 't1', 'props' => ['tabs' => 'not-an-array']],
                ['type' => 'accordion', 'id' => 'a1', 'props' => ['items' => ['not-an-array-item']]],
                ['type' => 'ext:acme:survivor', 'id' => 'e1'],
            ]],
            'weird' => 'not-an-array-page',
        ];

        $this->assertSame(['ext:acme:survivor'], $this->extractor->extractFromSnapshot([], [], $pages));
    }

    public function test_release_snapshot_convenience_wrapper_reads_expected_keys(): void
    {
        $snapshot = [
            'header_blocks' => [['type' => 'ext:acme:h', 'id' => 'h1']],
            'footer_blocks' => [['type' => 'ext:acme:f', 'id' => 'f1']],
            'pages' => ['home' => ['blocks' => [['type' => 'ext:acme:p', 'id' => 'p1']]]],
        ];

        $types = $this->extractor->extractFromReleaseSnapshot($snapshot);
        sort($types);
        $this->assertSame(['ext:acme:f', 'ext:acme:h', 'ext:acme:p'], $types);
    }
}
