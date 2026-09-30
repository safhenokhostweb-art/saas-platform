<?php

namespace Tests\Unit\Core\SEO;

use PHPUnit\Framework\TestCase;
use SaaSPlatform\CoreUtils\SEO\MetaContext;
use SaaSPlatform\CoreUtils\SEO\MetaEngine;

class MetaEngineTest extends TestCase
{
    private MetaEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new MetaEngine();
    }

    public function test_resolution_precedence_is_override_then_model_then_tenant_then_fallback(): void
    {
        $model = (object) [
            'meta_title' => 'Model title',
            'meta_description' => 'Model description',
            'canonical_url' => '/model',
            'language' => 'en',
            'og_type' => 'article',
        ];

        $meta = $this->engine->resolve(
            MetaContext::TENANT_PUBLIC,
            source: $model,
            overrides: [
                'title' => 'Override title',
                'description' => 'Override description',
                'canonical' => '/override',
                'twitter_card' => 'summary_large_image',
            ],
            tenantSettings: [
                'canonical_origin' => 'https://tenant.example',
                'seo_default_title' => 'Tenant title',
                'seo_default_description' => 'Tenant description',
                'robots' => 'index, follow',
                'og_image' => '/tenant.jpg',
            ],
        );

        self::assertSame('Override title', $meta->title);
        self::assertSame('Override description', $meta->description);
        self::assertSame('https://tenant.example/override', $meta->canonical);
        self::assertSame('en', $meta->language);
        self::assertSame('ltr', $meta->direction);
        self::assertSame('index, follow', $meta->robots);
        self::assertSame('/tenant.jpg', $meta->og_image);
        self::assertSame('article', $meta->og_type);
        self::assertSame('summary_large_image', $meta->twitter_card);
    }

    /**
     * A module's SeoOverrideProviderInterface contribution is a DEFAULT, not an
     * override. SeoOverrideProviderInterface documents the returned value as the
     * one to use "when the host has no existing default for that field", so the
     * full chain is: explicit overrides > the page/model > provider defaults >
     * the context's settings channel > the context's own fallback.
     *
     * The tier matters: a site-wide module title merged in as an override (as
     * App\Services\Seo\SeoService used to do) outranks the page's own title and
     * collapses every page of the site onto one string.
     */
    public function test_provider_defaults_rank_below_the_model_and_above_the_context_settings(): void
    {
        $meta = $this->engine->resolve(
            MetaContext::TENANT_PUBLIC,
            source: (object) ['title' => 'Model title', 'meta_description' => 'Model description'],
            overrides: [],
            tenantSettings: [
                'canonical_origin' => 'https://tenant.example',
                'seo_default_title' => 'Tenant title',
                'seo_default_description' => 'Tenant description',
                'robots' => 'index, follow',
            ],
            providerDefaults: [
                'title' => 'Module title',
                'description' => 'Module description',
                'robots' => 'noindex, follow',
                'twitter_card' => 'summary_large_image',
            ],
        );

        // The model outranks the module.
        self::assertSame('Model title', $meta->title);
        self::assertSame('Model description', $meta->description);

        // The module outranks the tenant settings where the model has nothing.
        self::assertSame('noindex, follow', $meta->robots);
        self::assertSame('summary_large_image', $meta->twitter_card);

        // ...and the settings channel still wins where the module has nothing.
        $meta2 = $this->engine->resolve(
            MetaContext::TENANT_PUBLIC,
            source: (object) [],
            tenantSettings: ['seo_default_title' => 'Tenant title'],
            providerDefaults: ['description' => 'Module description'],
        );
        self::assertSame('Tenant title', $meta2->title);
        self::assertSame('Module description', $meta2->description);
    }

    public function test_explicit_overrides_still_outrank_provider_defaults(): void
    {
        $meta = $this->engine->resolve(
            MetaContext::TENANT_PUBLIC,
            source: (object) [],
            overrides: ['title' => 'Override title'],
            providerDefaults: ['title' => 'Module title', 'description' => 'Module description'],
        );

        self::assertSame('Override title', $meta->title);
        self::assertSame('Module description', $meta->description);
    }

    /**
     * A module adapter is a third party reading its own tables. Whatever it hands
     * back must never be able to turn a page render into a 500, and a value it
     * returns for a field the page already has must never displace the page's own
     * value.
     */
    public function test_malformed_provider_defaults_are_ignored_instead_of_breaking_resolution(): void
    {
        $meta = $this->engine->resolve(
            MetaContext::TENANT_PUBLIC,
            source: (object) ['title' => 'Model title'],
            providerDefaults: [
                'title' => ['not', 'a', 'string'],
                'description' => new \stdClass(),
                'robots' => true,
                'canonical' => ['https://evil.example/x'],
                'og_image' => 12345,
            ],
        );

        self::assertSame('Model title', $meta->title);
        // Every malformed value is skipped, so the context fallback is used.
        self::assertNotSame('', $meta->description);
        self::assertSame('index, follow', $meta->robots);
        self::assertNotSame('', $meta->canonical);
    }

    public function test_empty_values_fall_through_each_resolution_level(): void
    {        $meta = $this->engine->resolve(
            MetaContext::TENANT_PUBLIC,
            source: ['meta_title' => ' ', 'meta_description' => null],
            overrides: ['title' => '', 'description' => '   ', 'canonical' => null],
            tenantSettings: [
                'canonical_origin' => 'https://tenant.example',
                'seo_default_title' => 'Tenant title',
                'seo_default_description' => '',
            ],
        );

        self::assertSame('Tenant title', $meta->title);
        self::assertSame('Site public page.', $meta->description);
        self::assertSame('https://tenant.example/', $meta->canonical);
    }

    public function test_platform_context_ignores_all_tenant_settings(): void
    {
        $meta = $this->engine->resolve(
            MetaContext::PLATFORM_ADMIN,
            tenantSettings: [
                'canonical_origin' => 'https://tenant.example',
                'seo_default_title' => 'Tenant secret title',
                'seo_default_description' => 'Tenant secret description',
                'robots' => 'index, follow',
                'og_image' => 'https://tenant.example/secret.jpg',
            ],
            platformSettings: [
                'canonical_origin' => 'https://panel.example',
                'seo_default_title' => 'Platform title',
            ],
        );

        self::assertSame('Platform title', $meta->title);
        self::assertSame('Platform administration area.', $meta->description);
        self::assertSame('https://panel.example/', $meta->canonical);
        self::assertSame('noindex, nofollow', $meta->robots);
        self::assertSame('', $meta->og_image);
    }

    public function test_tenant_context_ignores_all_platform_settings(): void
    {
        $meta = $this->engine->resolve(
            MetaContext::CANVAS_PUBLIC,
            platformSettings: [
                'canonical_origin' => 'https://panel.example',
                'seo_default_title' => 'Platform secret title',
            ],
            tenantSettings: [
                'canonical_origin' => 'https://shop.example',
                'seo_default_title' => 'Tenant title',
            ],
        );

        self::assertSame('Tenant title', $meta->title);
        self::assertSame('https://shop.example/', $meta->canonical);
        self::assertSame('index, follow', $meta->robots);
    }

    public function test_cross_origin_canonical_is_rejected_for_the_active_context(): void
    {
        $meta = $this->engine->resolve(
            MetaContext::PLATFORM_PUBLIC,
            overrides: ['canonical' => 'https://tenant.example/leak'],
            platformSettings: [
                'canonical_origin' => 'https://panel.example',
                'canonical' => '/platform-page',
            ],
        );

        self::assertSame('https://panel.example/platform-page', $meta->canonical);
    }

    public function test_neutral_contexts_ignore_both_settings_channels(): void
    {
        $meta = $this->engine->resolve(
            MetaContext::PREVIEW_EDITOR,
            platformSettings: ['seo_default_title' => 'Platform title'],
            tenantSettings: ['seo_default_title' => 'Tenant title'],
        );

        self::assertSame('Preview', $meta->title);
        self::assertSame('https://preview.invalid/', $meta->canonical);
        self::assertSame('noindex, nofollow', $meta->robots);
    }

    public function test_nested_page_seo_values_are_treated_as_model_data(): void
    {
        $meta = $this->engine->resolve(
            MetaContext::CANVAS_PUBLIC,
            source: [
                'title' => 'Canvas title',
                'seo' => [
                    'title' => 'Canvas SEO title',
                    'description' => 'Canvas SEO description',
                    'canonical' => '/canvas',
                ],
            ],
            tenantSettings: ['canonical_origin' => 'https://tenant.example'],
        );

        self::assertSame('Canvas SEO title', $meta->title);
        self::assertSame('Canvas SEO description', $meta->description);
        self::assertSame('https://tenant.example/canvas', $meta->canonical);
    }
}
