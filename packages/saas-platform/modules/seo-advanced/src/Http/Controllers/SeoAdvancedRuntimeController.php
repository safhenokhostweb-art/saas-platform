<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\RobotsBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SitemapBuilderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Services\SitemapUrlCollector;

/**
 * Phase 18 — Part 8: Customer Runtime — the actual public-facing routes a
 * search engine hits. Public (no auth), served directly by whatever app
 * this module is deployed into.
 */
class SeoAdvancedRuntimeController extends Controller
{
    public function sitemap(int $siteId, SitemapBuilderInterface $sitemapBuilder, SitemapUrlCollector $collector): Response
    {
        return response($sitemapBuilder->generate($collector->collect($siteId)), 200)
            ->header('Content-Type', 'application/xml');
    }

    public function robots(int $siteId, RobotsBuilderInterface $robotsBuilder): Response
    {
        $sitemapUrl = url("/seo-advanced/{$siteId}/sitemap.xml");

        return response($robotsBuilder->generate($siteId, $sitemapUrl), 200)
            ->header('Content-Type', 'text/plain');
    }
}
