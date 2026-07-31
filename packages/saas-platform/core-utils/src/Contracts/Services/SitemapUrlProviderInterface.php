<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

/**
 * Phase 18-C — Part 3: the generic (not SEO-Advanced-specific) extension
 * point a tenant runtime uses to declare its own public URLs for sitemap
 * generation. The host binds a real implementation here (it already
 * knows its own content models — Page/Post/Product/etc.); any SEO/sitemap
 * module consumes this interface without ever hardcoding a Core model.
 */
interface SitemapUrlProviderInterface
{
    /**
     * @return array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: float}>
     */
    public function urls(): array;
}
