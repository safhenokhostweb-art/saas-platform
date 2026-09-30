<?php

namespace Tests\Feature\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use Tests\TestCase;

/**
 * Phase 3F-B — proves BlockRegistry::isContainerType() (added to the shared
 * SaaSPlatform\ThemeRuntime\BlockRegistry package this phase, inherited here
 * unchanged) is the one canonical/shared answer for container capability,
 * declarative (reads the explicit `is_container` flag), and does not
 * silently widen beyond the pre-existing container set merely because a
 * type happens to declare a `type => 'blocks'` schema field (e.g. `slider`).
 */
class BlockRegistryContainerCapabilityTest extends TestCase
{
    public function test_canonical_container_types_are_true(): void
    {
        // BLD-08 (Full Product Commercial Audit, TASK 1/3): `modal` moved
        // here from the "stays false" group below — unlike slider/
        // accordion, it has no special-case per-item renderer of its own;
        // its `blocks` need exactly the same flat $inner population every
        // other container type gets. Its previous non-container status
        // was not a deliberate design choice, it was the reason `modal`
        // had no working renderer at all (its nested content had nowhere
        // to go): confirmed by direct render-output inspection before
        // fixing, not assumed.
        //
        // TASK 2A (tabs container authoring fix): `tabs` moved here too,
        // for the same reason `modal` was — it no longer has a special-case
        // per-item renderer of its own. Each tab is now a real `tab_panel`
        // CHILD block in the canonical tree (registered as its own
        // container type below, alongside `tabs`), authored with the same
        // add/remove/reorder/edit-children controls every other container
        // already has. BlockRenderer::renderTabs() keeps a read-only
        // compatibility fallback for pre-fix content still shaped as
        // `props.tabs[].blocks` (never written by the editor once it
        // migrates a block to real tab_panel children on load — see
        // BlockRenderer::renderTabs()'s own docblock) — that fallback is
        // not a second live authoring/render authority, confirmed by
        // direct render-output inspection of both the tab_panel-children
        // path and the legacy props.tabs path on both saas-platform's own
        // BlockRenderer and customer-site's App\Services\ThemeBuilder\
        // BlockRenderer (which had its own now-fixed leftover special case
        // that discarded real tab_panel content — see that class).
        foreach (['section', 'container', 'grid', 'columns', 'flex_container', 'modal', 'tabs', 'tab_panel'] as $type) {
            $this->assertTrue(BlockRegistry::isContainerType($type), "{$type} must be a container");
        }
    }

    public function test_representative_non_container_types_are_false(): void
    {
        foreach (['heading', 'text', 'button', 'hero', 'divider'] as $type) {
            $this->assertFalse(BlockRegistry::isContainerType($type), "{$type} must not be a container");
        }
    }

    public function test_types_with_a_blocks_typed_schema_field_that_are_not_declared_containers_stay_false(): void
    {
        // slider/accordion carry nested block content (slides/accordion
        // items) but each has its own special-case per-item renderer
        // (renderSlider()/renderAccordion()) that wraps each child
        // individually (a slide, an accordion item) — they must NOT also
        // get the generic flat-$inner container treatment, which would
        // double-render or bypass that per-item wrapping. `modal` and
        // `tabs` are deliberately no longer in this list — see
        // test_canonical_container_types_are_true() above.
        foreach (['slider', 'accordion'] as $type) {
            $this->assertFalse(BlockRegistry::isContainerType($type), "{$type} must remain non-container (has its own special-case per-item renderer)");
        }
    }

    public function test_unknown_type_is_false(): void
    {
        $this->assertFalse(BlockRegistry::isContainerType('definitely_not_a_real_type'));
        $this->assertFalse(BlockRegistry::isContainerType('ext:acme:demo_container')); // extension types are a host-layer concern, not this shared layer
    }

    public function test_every_registry_container_is_exposed_as_an_editor_drop_target(): void
    {
        $renderer = (new BlockRenderer())->setContext(['editor_mode' => true]);

        foreach (['section', 'container', 'grid', 'columns', 'flex_container', 'modal', 'tabs', 'tab_panel'] as $type) {
            $html = $renderer->renderBlock([
                'id' => 'container-'.$type,
                'type' => $type,
                'props' => BlockRegistry::all()[$type]['defaultProps'] ?? [],
                'children' => [],
            ]);

            $this->assertStringContainsString('data-tb-container="1"', $html, "{$type} must be a Canvas drop target");
        }
    }

    public function test_extension_container_uses_the_same_overridable_drop_target_seam(): void
    {
        $renderer = (new BlockRenderer())->setContext([
            'editor_mode' => true,
            'ext_container_types' => ['ext:acme:demo_container' => true],
        ]);

        $html = $renderer->renderBlock([
            'id' => 'extension-container',
            'type' => 'ext:acme:demo_container',
            'props' => [],
            'children' => [],
        ]);

        $this->assertStringContainsString('data-tb-container="1"', $html);
    }

    public function test_registry_containers_render_children_recursively_from_the_canonical_children_key(): void
    {
        $renderer = new BlockRenderer();
        $html = $renderer->renderBlock([
            'id' => 'outer',
            'type' => 'container',
            'props' => [],
            'children' => [[
                'id' => 'inner',
                'type' => 'modal',
                'props' => [],
                'children' => [[
                    'id' => 'leaf',
                    'type' => 'text',
                    'props' => ['content' => 'recursive-child-marker'],
                ]],
            ]],
        ]);

        $this->assertStringContainsString('id="tb-outer"', $html);
        $this->assertStringContainsString('id="tb-inner"', $html);
        $this->assertStringContainsString('id="tb-leaf"', $html);
        $this->assertStringContainsString('recursive-child-marker', $html);
    }
}
