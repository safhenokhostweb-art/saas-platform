<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\RankProviderInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeywordPosition;

/**
 * Phase 18-E — Part 3: orchestrates keyword position recording — both
 * the manual path (admin enters a number) and the automatic sweep
 * (delegates to whichever RankProviderInterface is bound). Keeps
 * SeoAdvancedKeyword::recordPosition() (Phase 18-D, the flat JSON
 * column) working unchanged for backward compatibility, while this
 * service is the new source of truth for the normalized, multi-URL,
 * multi-search-engine history.
 */
class RankTrackingService
{
    public function __construct(
        private readonly RankProviderInterface $provider,
    ) {}

    public function recordManual(SeoAdvancedKeyword $keyword, int $position, ?string $url = null, string $searchEngine = 'google', ?string $date = null): SeoAdvancedKeywordPosition
    {
        $keyword->recordPosition($position, $date);

        return SeoAdvancedKeywordPosition::create([
            'site_id' => $keyword->site_id,
            'keyword_id' => $keyword->id,
            'url' => $url ?? $keyword->target_url,
            'position' => $position,
            'recorded_at' => $date ?? now()->toDateString(),
            'search_engine' => $searchEngine,
            'source' => 'manual',
        ]);
    }

    /**
     * Sweeps every active keyword for a site through the bound
     * RankProviderInterface. With the default ManualRankProvider this
     * is always a no-op (returns 0) — see that class's own docblock.
     */
    public function sweep(int $siteId): int
    {
        $updated = 0;

        SeoAdvancedKeyword::where('site_id', $siteId)
            ->where('status', SeoAdvancedKeyword::STATUS_ACTIVE)
            ->each(function (SeoAdvancedKeyword $keyword) use (&$updated) {
                $position = $this->provider->fetchPosition($keyword->site_id, $keyword->keyword, $keyword->target_url);

                if ($position === null) {
                    return;
                }

                $keyword->recordPosition($position);

                SeoAdvancedKeywordPosition::create([
                    'site_id' => $keyword->site_id,
                    'keyword_id' => $keyword->id,
                    'url' => $keyword->target_url,
                    'position' => $position,
                    'recorded_at' => now()->toDateString(),
                    'search_engine' => 'google',
                    'source' => $this->provider->name(),
                ]);

                $updated++;
            });

        return $updated;
    }

    public function history(SeoAdvancedKeyword $keyword, ?string $searchEngine = null, int $limit = 30): Collection
    {
        return SeoAdvancedKeywordPosition::where('keyword_id', $keyword->id)
            ->when($searchEngine, fn ($q) => $q->where('search_engine', $searchEngine))
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
