<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\CoreUtils\Contracts\Services\SitemapUrlProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Events\SitemapUrlsRequested;

/**
 * Phase 18-C — Part 3: combines both of this module's sitemap URL
 * sources — the Core-bound SitemapUrlProviderInterface (primary; Core's
 * own Page/Post/Product, added in 18-C) and the SitemapUrlsRequested
 * event (secondary; ad-hoc contributions from any other listener,
 * unchanged since Phase 18). Neither is required — an app with neither
 * bound/listened just gets a URL-less (but still valid) sitemap, never
 * an error (Part 6: Runtime Safety).
 */
class SitemapUrlCollector
{
    /**
     * @return array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: float}>
     */
    public function collect(int $siteId): array
    {
        $urls = [];

        if (app()->bound(SitemapUrlProviderInterface::class)) {
            try {
                $urls = array_merge($urls, app(SitemapUrlProviderInterface::class)->urls());
            } catch (\Throwable $e) {
                // A misbehaving provider must never break sitemap rendering.
            }
        }

        $event = new SitemapUrlsRequested($siteId);
        event($event);

        return array_merge($urls, $event->urls);
    }
}
