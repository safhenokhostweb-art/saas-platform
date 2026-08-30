<?php

namespace Tests\Feature\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\GlobalStyleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;
use Tests\TestCase;

/**
 * Phase 2h (ADR-029) — proves saas-platform genuinely consumes the ONE
 * shared canonical rendering/registry/token authority rather than
 * maintaining its own independent copy. See customer-site's own
 * ThemeRuntimeSharedAuthorityTest for the equivalent proof on that host.
 */
class ThemeRuntimeSharedAuthorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_apps_block_registry_is_the_shared_canonical_registry(): void
    {
        $registry = new BlockRegistry();

        $this->assertInstanceOf(\SaaSPlatform\ThemeRuntime\BlockRegistry::class, $registry);
        // Not a re-declared/forked data set — the same static cache, since
        // the subclass never overrides all().
        $this->assertSame(\SaaSPlatform\ThemeRuntime\BlockRegistry::all(), BlockRegistry::all());
    }

    public function test_the_apps_block_renderer_is_the_shared_canonical_renderer(): void
    {
        $renderer = new BlockRenderer();

        $this->assertInstanceOf(\SaaSPlatform\ThemeRuntime\BlockRenderer::class, $renderer);
    }

    public function test_a_canonical_structural_block_renders_via_the_shared_implementation(): void
    {
        $block = ['type' => 'client_logos', 'id' => 'x1', 'props' => ['title' => 'مشتریان']];

        $viaHost = (new BlockRenderer())->renderBlock($block);
        $viaShared = (new \SaaSPlatform\ThemeRuntime\BlockRenderer())->renderBlock($block);

        $this->assertSame($viaShared, $viaHost);
    }

    public function test_registering_a_host_type_never_overrides_a_canonical_definition(): void
    {
        BlockRegistry::resetHostTypes();

        try {
            $before = BlockRegistry::all()['hero'];
            BlockRegistry::registerHostType('hero', ['name' => 'Forged Hero', 'category' => 'layout']);
            $after = BlockRegistry::all()['hero'];

            $this->assertSame($before, $after, 'A host registration must never override a canonical type.');
        } finally {
            BlockRegistry::resetHostTypes();
        }
    }

    public function test_the_global_style_engine_normalizes_via_the_shared_token_normalizer(): void
    {
        $engine = $this->app->make(GlobalStyleEngine::class);
        $normalizer = new ThemeTokenNormalizer();

        $globalStyles = ['primary_color' => '#123456'];

        $viaEngine = $engine->getCssVariables($globalStyles);
        $viaNormalizer = $normalizer->normalize($globalStyles);

        $this->assertSame($viaNormalizer['--tb-primary'], $viaEngine['--tb-primary']);
        $this->assertSame('#123456', $viaEngine['--tb-primary']);
    }
}
