<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Contracts;

/**
 * Phase 18-E — Part 3: abstracts "where does a keyword's rank position
 * come from" so RankTrackingService never hardcodes a specific source.
 * Bound to ManualRankProvider by default (always returns null — this
 * module stays free of any external-service dependency out of the box).
 * A future provider (Google Search Console — see Part 2's
 * GoogleSearchConsoleRankProvider, a third-party rank API, or an
 * internal crawler) implements the same interface and is swapped in via
 * SeoAdvancedServiceProvider without touching any calling code.
 */
interface RankProviderInterface
{
    /** A short, stable identifier stored in seo_advanced_keyword_positions.source (e.g. "manual", "google_search_console"). */
    public function name(): string;

    /**
     * Returns the current rank position for $keyword against $url, or
     * null when the provider has no automatic answer (e.g. the default
     * ManualRankProvider always returns null — a human enters positions
     * by hand instead).
     */
    public function fetchPosition(int $siteId, string $keyword, ?string $url, string $searchEngine = 'google'): ?int;
}
