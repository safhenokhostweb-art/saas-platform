<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\RankProviderInterface;

/**
 * Phase 18-E — Part 3: the default RankProviderInterface binding —
 * deliberately never calls any external service. fetchPosition() always
 * returns null, meaning RankTrackingService's automatic sweep is a
 * no-op until a real provider (e.g. Google Search Console) is bound
 * instead. Manual entry (SeoAdvancedKeyword::recordPosition(), driven
 * by the admin UI) is a separate, always-available path that doesn't
 * go through this interface at all.
 */
class ManualRankProvider implements RankProviderInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function fetchPosition(int $siteId, string $keyword, ?string $url, string $searchEngine = 'google'): ?int
    {
        return null;
    }
}
