<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;

interface ContentDeficitQueueServiceInterface
{
    /**
     * Build a content-deficit queue for a site.
     *
     * Returns items ordered by ascending readiness_score (lowest readiness first).
     *
     * @return Collection<int, \SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto>
     */
    public function build(?int $siteId): Collection;

    /**
     * Deficit type constants.
     */
    public const TYPE_ARTICLES       = 'articles';
    public const TYPE_WALKTHROUGHS   = 'walkthroughs';
    public const TYPE_LEARNING_PATHS = 'learning_paths';
    public const TYPE_FRESHNESS      = 'freshness';

    /**
     * Priority thresholds (based on readiness_score).
     * Score < 25 → Critical, < 50 → High, < 75 → Medium, >= 75 → Low
     */
    public const THRESHOLD_CRITICAL = 25.0;
    public const THRESHOLD_HIGH     = 50.0;
    public const THRESHOLD_MEDIUM   = 75.0;
}
