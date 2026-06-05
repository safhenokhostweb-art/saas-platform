<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;

interface KnowledgeGapServiceInterface
{
    /**
     * Return knowledge gap assessments for every module the user has access to.
     * Results are cached 15 minutes (edu_gap.{userId}.{siteId}).
     * Each entry: { module_slug, gap_score, priority }
     *
     * @return Collection<int, array{module_slug: string, gap_score: float, priority: string}>
     */
    public function detectForUser(int $userId, ?int $siteId): Collection;

    /**
     * Invalidate the cached gap assessment so it is rebuilt on next call.
     */
    public function invalidate(int $userId, ?int $siteId): void;

    /**
     * Priority constants exposed for downstream consumers.
     */
    public const PRIORITY_CRITICAL = 'Critical';
    public const PRIORITY_HIGH     = 'High';
    public const PRIORITY_MEDIUM   = 'Medium';
    public const PRIORITY_LOW      = 'Low';
}
