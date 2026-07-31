<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Events;

/**
 * Phase 18 — Part 5: the module's Extension Point for sitemap content.
 * Fired whenever a sitemap is generated; any listener (registered by
 * Core, a theme, or another module — never by this module itself) may
 * push URL entries onto `$urls`. This is how SEO Advanced stays fully
 * independent of Core's Page/Product/etc. models while still producing a
 * useful sitemap once something is wired to listen.
 *
 * Example listener (registered in the HOST app, not here):
 *   Event::listen(SitemapUrlsRequested::class, function ($event) {
 *       if ($event->siteId !== $thisSitesId) return;
 *       foreach (Page::where('site_id', $event->siteId)->get() as $page) {
 *           $event->urls[] = ['loc' => $page->url, 'lastmod' => $page->updated_at->toAtomString()];
 *       }
 *   });
 */
class SitemapUrlsRequested
{
    /** @var array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: float}> */
    public array $urls = [];

    public function __construct(
        public readonly int $siteId,
    ) {}
}
