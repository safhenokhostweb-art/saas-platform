<?php

namespace Tests\Feature\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRegistry;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\GlobalStyleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\ThemeRuntime\BlockTypeAlreadyRegisteredException;
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
        // Phase 3E-B (ADR-030): a canonical collision now fails CLOSED
        // (throws, registry state unchanged) rather than being silently
        // absorbed by merge precedence. The old contract ("silently keep
        // the canonical value, no error") left a caller with no way to
        // know its registration was ignored — unacceptable once real
        // third-party extension registration exists.
        BlockRegistry::resetHostTypes();

        try {
            $before = BlockRegistry::all()['hero'];

            $this->expectException(BlockTypeAlreadyRegisteredException::class);
            $this->expectExceptionMessage('hero');

            try {
                BlockRegistry::registerHostType('hero', ['name' => 'Forged Hero', 'category' => 'layout']);
            } finally {
                // Assert BEFORE re-throwing: registry state must be
                // completely unmutated by a rejected registration.
                $this->assertSame($before, BlockRegistry::all()['hero']);
                $this->assertFalse(BlockRegistry::isHostRegisteredType('hero'));
            }
        } finally {
            BlockRegistry::resetHostTypes();
        }
    }

    public function test_re_registering_the_same_host_type_with_an_identical_definition_is_idempotent(): void
    {
        // A persistent worker (e.g. Octane) can legitimately run a service
        // provider's boot() more than once in the same process — the exact
        // same host-type registration repeating must not become a fatal
        // error merely because it repeats (Phase 3D-F's singleton/TOCTOU
        // lesson: repetition under a persistent worker is a real scenario,
        // not a hypothetical one).
        BlockRegistry::resetHostTypes();

        try {
            $definition = ['name' => 'Newsletter Signup', 'category' => 'marketing'];

            BlockRegistry::registerHostType('newsletter_signup_test', $definition);
            BlockRegistry::registerHostType('newsletter_signup_test', $definition);

            $this->assertTrue(BlockRegistry::isHostRegisteredType('newsletter_signup_test'));
            $this->assertSame($definition, BlockRegistry::all()['newsletter_signup_test']);
        } finally {
            BlockRegistry::resetHostTypes();
        }
    }

    public function test_re_registering_the_same_host_type_with_a_different_definition_is_rejected(): void
    {
        BlockRegistry::resetHostTypes();

        try {
            BlockRegistry::registerHostType('newsletter_signup_test', ['name' => 'Newsletter Signup', 'category' => 'marketing']);

            $this->expectException(BlockTypeAlreadyRegisteredException::class);
            BlockRegistry::registerHostType('newsletter_signup_test', ['name' => 'Different Definition', 'category' => 'marketing']);
        } finally {
            BlockRegistry::resetHostTypes();
        }
    }

    public function test_registering_a_genuinely_non_colliding_host_type_succeeds(): void
    {
        BlockRegistry::resetHostTypes();

        try {
            BlockRegistry::registerHostType('acme_widget_test', ['name' => 'Acme Widget', 'category' => 'utility']);

            $this->assertTrue(BlockRegistry::isHostRegisteredType('acme_widget_test'));
            $this->assertArrayHasKey('acme_widget_test', BlockRegistry::all());
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
