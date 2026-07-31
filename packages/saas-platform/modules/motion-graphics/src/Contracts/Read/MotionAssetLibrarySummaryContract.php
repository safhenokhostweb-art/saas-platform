<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read;

/**
 * Phase 7 (docs/motion-admin-management/): a deliberately narrow,
 * additive contract for the admin dashboard's "total assets" count.
 * Kept separate from MotionAssetReadContract (rather than adding a
 * method to it) so no existing implementation — including the several
 * anonymous test fakes across the Phase 1-6 suite — needs to change.
 *
 * Implementations must never return anything beyond a count: no asset
 * ids, no metadata, no source data.
 */
interface MotionAssetLibrarySummaryContract
{
    /**
     * Count of assets that currently have at least one published version.
     */
    public function countPublishedAssets(): int;
}
