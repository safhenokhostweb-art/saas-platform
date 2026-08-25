<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;

/**
 * A deliberately narrow, additive contract for browsing the published,
 * reusable (global) asset library — e.g. a Theme Builder authoring picker.
 * Kept separate from MotionAssetReadContract for the exact same reason
 * MotionAssetLibrarySummaryContract already is (see that interface's own
 * docblock): so no existing implementation — including the several
 * anonymous test fakes across the Phase 1-7 suite — needs to change.
 *
 * Deliberately global-only: a customer-specific/bespoke MotionGraphic is
 * never returned here — it remains reachable only through its own
 * site-scoped runtime/assignment path (SiteAssetPackage /
 * MotionBespokeAssignment), never through global template authoring.
 */
interface MotionAssetLibraryBrowseContract
{
    /**
     * @param array{search?: string, category?: string} $filters
     * @return array{items: list<AssetReadModel>, total: int, page: int, per_page: int}
     */
    public function browsePublished(array $filters, int $page, int $perPage): array;
}
