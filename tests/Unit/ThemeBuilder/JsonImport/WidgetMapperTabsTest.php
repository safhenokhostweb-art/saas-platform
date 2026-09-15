<?php

namespace Tests\Unit\ThemeBuilder\JsonImport;

use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\JsonImport\WidgetMapper;
use Tests\TestCase;

/**
 * TASK 2A (tabs container authoring fix): the JSON Import pipeline
 * (WidgetMapper::mapNode()) is a live authoring path that creates new
 * `tabs` blocks (from imported Elementor/other-builder theme JSON) — it
 * must build real `tab_panel` children, the same canonical shape the
 * Canvas editor now authors, never the legacy `props.tabs[].blocks` shape.
 * Before this fix it always produced the legacy shape regardless of
 * source, which would have made JSON import a second live tabs-content
 * authority.
 */
class WidgetMapperTabsTest extends TestCase
{
    public function test_imported_elementor_tabs_widget_produces_real_tab_panel_children(): void
    {
        $mapper = new WidgetMapper();

        $block = $mapper->mapNode('elementor', 'tabs', [
            'tabs' => [
                ['tab_title' => 'ویژگی‌ها'],
                ['tab_title' => 'قیمت‌گذاری'],
            ],
        ]);

        self::assertSame('tabs', $block['type']);
        self::assertArrayNotHasKey('tabs', $block['props'], 'tabs prop must not be written by the importer anymore');
        self::assertCount(2, $block['blocks']);
        self::assertSame('tab_panel', $block['blocks'][0]['type']);
        self::assertSame('ویژگی‌ها', $block['blocks'][0]['props']['title']);
        self::assertSame('tab_panel', $block['blocks'][1]['type']);
        self::assertSame('قیمت‌گذاری', $block['blocks'][1]['props']['title']);
        // Every mapped child must itself be a valid, individually-addressable block.
        foreach ($block['blocks'] as $child) {
            self::assertNotEmpty($child['id']);
            self::assertArrayHasKey('blocks', $child);
        }
    }

    public function test_a_tabs_node_with_no_source_tab_data_still_produces_one_real_tab_panel_child(): void
    {
        $mapper = new WidgetMapper();

        $block = $mapper->mapNode('elementor', 'tabs', []);

        self::assertCount(1, $block['blocks']);
        self::assertSame('tab_panel', $block['blocks'][0]['type']);
    }

    public function test_tab_panel_is_registered_as_the_canonical_container_type_the_importer_now_produces(): void
    {
        self::assertTrue(BlockRegistry::isContainerType('tab_panel'));
    }
}
