<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

/**
 * Phase 18-E — Part 2: abstracts "where does Google Search Console
 * performance data come from" — bound to GoogleSearchConsoleService by
 * default (this module owns its own connection storage, so — like
 * SitemapUrlProviderInterface's precedent — the default binding is the
 * real thing, not a Null Object; it degrades gracefully to a zeroed
 * result when a site has no connection, see isConnected()).
 */
interface SearchConsolePerformanceProviderInterface
{
    public function isConnected(int $siteId): bool;

    /**
     * @return array{
     *     clicks: int, impressions: int, ctr: float, average_position: float,
     *     top_queries: array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     top_pages: array<int, array{page: string, clicks: int, impressions: int, ctr: float, position: float}>,
     * }
     */
    public function getPerformance(int $siteId, string $startDate, string $endDate): array;
}
