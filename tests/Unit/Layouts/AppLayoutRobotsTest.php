<?php

namespace Tests\Unit\Layouts;

use App\Services\Seo\NullSeoOverrideProvider;
use Illuminate\Support\Facades\Blade;
use SaaSPlatform\CoreUtils\SEO\MetaData;
use Tests\TestCase;

/**
 * `layouts.app` is the one layout every surface that renders the platform's
 * public/admin chrome goes through, and `<x-seo-meta>` is the one component
 * allowed to write head tags.
 *
 * It used to inline a hardcoded `<meta name="robots" content="noindex,
 * nofollow">` for every surface — including the public, indexable
 * `page/{slug}` route — with a `partials.meta-robots` include intended to
 * parameterise it. The MetaEngine integration then replaced the inline tags
 * with a `<title>`/description/canonical/robots block resolved as
 * PLATFORM_ADMIN and dropped the include, which left `partials.meta-robots`
 * and the controller's `seoRobots` value inert: the public route rendered the
 * platform-admin fallback and its page-level overrides never reached the HTML.
 *
 * These tests pin the two structural properties that make that class of
 * regression impossible to reintroduce silently: the layout delegates, and the
 * component emits each tag exactly once and never renders a document.
 * Route-level rendering is covered by Tests\Feature\Seo\MetaEngineRenderedHeadTest.
 */
class AppLayoutRobotsTest extends TestCase
{
    private function metaData(array $overrides = []): MetaData
    {
        return new MetaData(
            title: $overrides['title'] ?? 'A resolved title',
            description: $overrides['description'] ?? 'A resolved description.',
            canonical: $overrides['canonical'] ?? 'https://tenant.invalid/a-page',
            language: 'fa',
            direction: 'rtl',
            robots: $overrides['robots'] ?? 'index, follow',
            og_image: $overrides['og_image'] ?? '',
            og_type: $overrides['og_type'] ?? 'website',
            twitter_card: 'summary',
        );
    }

    private function renderComponent(MetaData $metaData): string
    {
        return Blade::render('<x-seo-meta :meta-data="$metaData" />', compact('metaData'));
    }

    public function test_the_layout_delegates_every_seo_tag_to_the_single_renderer(): void
    {
        $contents = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('<x-seo-meta :meta-data="$metaData" />', $contents);

        // Strip Blade/PHP comments before checking: the file documents the
        // very tags it must not hand-write, and the point of the assertion is
        // that none of them appear as markup.
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
        $markup = preg_replace('#/\*.*?\*/#s', '', $markup ?? '');

        $this->assertStringNotContainsString('<title', $markup);
        $this->assertStringNotContainsString('name="description"', $markup);
        $this->assertStringNotContainsString('rel="canonical"', $markup);
        $this->assertStringNotContainsString('name="robots"', $markup);
    }

    public function test_the_layout_accepts_a_context_resolved_meta_data_from_a_child_view(): void
    {
        $contents = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('$metaData = $metaData ?? $seoService->resolveForPlatformAdmin(', $contents);
    }

    public function test_the_renderer_emits_each_tag_exactly_once(): void
    {
        $html = $this->renderComponent($this->metaData([
            'og_image' => 'https://tenant.invalid/og.png',
        ]));

        $this->assertSame(1, substr_count($html, '<title>'));
        $this->assertSame(1, substr_count($html, 'name="description"'));
        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertSame(1, substr_count($html, 'name="robots"'));
        $this->assertSame(1, substr_count($html, 'property="og:title"'));
        $this->assertSame(1, substr_count($html, 'name="twitter:card"'));
    }

    public function test_the_renderer_emits_the_resolved_values_verbatim(): void
    {
        $html = $this->renderComponent($this->metaData([
            'title' => 'صفحهٔ آزمایشی',
            'description' => 'توضیح آزمایشی صفحه.',
            'canonical' => 'https://tenant.invalid/probe',
            'robots' => 'index, follow',
        ]));

        $this->assertStringContainsString('<title>صفحهٔ آزمایشی</title>', $html);
        $this->assertStringContainsString('name="description" content="توضیح آزمایشی صفحه."', $html);
        $this->assertStringContainsString('rel="canonical" href="https://tenant.invalid/probe"', $html);
        $this->assertStringContainsString('name="robots" content="index, follow"', $html);
    }

    public function test_the_renderer_escapes_every_value_it_writes(): void
    {
        $html = $this->renderComponent($this->metaData([
            'title' => 'x"><script>alert(1)</script>',
        ]));

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_the_renderer_emits_head_tags_only_and_never_renders_its_slot_twice(): void
    {
        $html = $this->renderComponent($this->metaData());

        // A component that emits a document of its own is an independent second
        // renderer of the whole page, and re-rendering its slot duplicates
        // whatever markup the caller passed it.
        $this->assertStringNotContainsString('<!doctype', strtolower($html));
        $this->assertStringNotContainsString('<html', strtolower($html));
        $this->assertStringNotContainsString('<head', strtolower($html));
        $this->assertStringNotContainsString('<body', strtolower($html));
        $this->assertStringNotContainsString('$slot', $html);
    }

    public function test_the_null_object_provider_contributes_no_overrides(): void
    {
        $provider = new NullSeoOverrideProvider();

        $this->assertNull($provider->overrideFor());
        $this->assertSame([], $provider->extraJsonLd());
    }
}
