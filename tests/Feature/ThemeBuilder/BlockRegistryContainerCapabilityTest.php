<?php

namespace Tests\Feature\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRegistry;
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
        foreach (['section', 'container', 'grid', 'columns', 'flex_container'] as $type) {
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
        // slider/tabs/accordion carry nested block content (slides/tabs
        // items) but are not, today, generic authoring-time containers —
        // this must not change as a side effect of the new declarative flag.
        foreach (['slider', 'tabs', 'accordion', 'modal'] as $type) {
            $this->assertFalse(BlockRegistry::isContainerType($type), "{$type} must remain non-container (pre-existing behavior)");
        }
    }

    public function test_unknown_type_is_false(): void
    {
        $this->assertFalse(BlockRegistry::isContainerType('definitely_not_a_real_type'));
        $this->assertFalse(BlockRegistry::isContainerType('ext:acme:demo_container')); // extension types are a host-layer concern, not this shared layer
    }
}
