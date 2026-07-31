<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

interface SitemapBuilderInterface
{
    /**
     * Deliberately takes the URL list as input rather than querying any
     * Core model (Page/Product/etc.) directly — this module has no
     * knowledge of Core's entities. See SitemapUrlsRequested (Part 5:
     * Extension Point via Events) for how a caller supplies them.
     *
     * @param  array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: float}>  $urls
     */
    public function generate(array $urls): string;
}
