<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use SaaSPlatform\CoreUtils\Contracts\Services\SitemapUrlProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Events\SitemapUrlsRequested;
use SaaSPlatform\Modules\SeoAdvanced\Services\SitemapUrlCollector;
use Tests\TestCase;

/**
 * Phase 18-C — Part 3/7: SEO Sitemap Runtime scenarios — proves the
 * sitemap actually gets real URLs from whichever source is available
 * (Core-bound provider and/or event listeners), and degrades gracefully
 * (empty, never broken) when neither is present.
 */
class SeoSitemapRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private function collector(): SitemapUrlCollector
    {
        return new SitemapUrlCollector();
    }

    public function test_collects_urls_from_bound_provider(): void
    {
        $this->app->bind(SitemapUrlProviderInterface::class, function () {
            return new class implements SitemapUrlProviderInterface {
                public function urls(): array
                {
                    return [['loc' => 'https://example.com/'], ['loc' => 'https://example.com/about']];
                }
            };
        });

        $urls = $this->collector()->collect(1);

        $this->assertCount(2, $urls);
        $this->assertSame('https://example.com/', $urls[0]['loc']);
    }

    public function test_collects_urls_from_event_listeners_too(): void
    {
        \Illuminate\Support\Facades\Event::listen(SitemapUrlsRequested::class, function (SitemapUrlsRequested $event) {
            $event->urls[] = ['loc' => 'https://example.com/from-listener'];
        });

        $urls = $this->collector()->collect(1);

        $this->assertSame('https://example.com/from-listener', $urls[0]['loc']);
    }

    public function test_provider_and_event_urls_are_both_included(): void
    {
        $this->app->bind(SitemapUrlProviderInterface::class, function () {
            return new class implements SitemapUrlProviderInterface {
                public function urls(): array { return [['loc' => 'https://example.com/from-provider']]; }
            };
        });
        \Illuminate\Support\Facades\Event::listen(SitemapUrlsRequested::class, function (SitemapUrlsRequested $event) {
            $event->urls[] = ['loc' => 'https://example.com/from-listener'];
        });

        $urls = $this->collector()->collect(1);

        $this->assertCount(2, $urls);
    }

    public function test_no_provider_and_no_listener_yields_an_empty_but_valid_list(): void
    {
        $urls = $this->collector()->collect(1);

        $this->assertSame([], $urls);
    }

    public function test_a_throwing_provider_never_breaks_sitemap_collection(): void
    {
        $this->app->bind(SitemapUrlProviderInterface::class, function () {
            return new class implements SitemapUrlProviderInterface {
                public function urls(): array { throw new \RuntimeException('boom'); }
            };
        });

        $urls = $this->collector()->collect(1);

        $this->assertSame([], $urls);
    }

    public function test_runtime_route_serves_valid_xml_using_collected_urls(): void
    {
        $this->app->bind(SitemapUrlProviderInterface::class, function () {
            return new class implements SitemapUrlProviderInterface {
                public function urls(): array { return [['loc' => 'https://example.com/']]; }
            };
        });

        $response = $this->get(route('seo-advanced.runtime.sitemap', 1));

        $response->assertOk();
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $response->getContent());
    }
}
