<?php

namespace App\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\DTO\CustomerRuntimeAssetEntry;
use App\Modules\MotionGraphics\Models\MotionBespokeAssignment;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;

/**
 * ADR-081: the runtime entries of one site's ACTIVE bespoke assignments.
 *
 * Only what the read contracts already allow this site to read comes out.
 * The projection drops a customer_specific asset owned by another site, so
 * an assignment row can never widen who sees an asset. An assignment whose
 * version is no longer readable, or has no projection for this site, is
 * skipped on its own. It never blocks the site's other assets.
 *
 * No license-tier check here: the purchase recorded before an admin could
 * assign the commission is the entitlement (MotionBespokeAssignmentService).
 */
final class BespokeAssignedAssets
{
    public function __construct(
        private readonly MotionAssetReadContract $assets,
        private readonly MotionRuntimeProjectionReadContract $projections,
        private readonly CustomerRuntimeAssetEntryFactory $entries = new CustomerRuntimeAssetEntryFactory(),
    ) {}

    /**
     * @param list<string> $exclude asset ids already delivered by the site's package
     * @return list<CustomerRuntimeAssetEntry>
     */
    public function entriesFor(int $siteId, array $exclude = []): array
    {
        $assignments = MotionBespokeAssignment::query()
            ->where('site_id', $siteId)
            ->where('status', MotionBespokeAssignment::STATUS_ACTIVE)
            ->orderBy('id')
            ->get();

        $entries = [];
        $seen = array_fill_keys($exclude, true);
        foreach ($assignments as $assignment) {
            if (isset($seen[$assignment->asset_id])
                || $this->assets->findByCanonicalId($assignment->asset_id, $assignment->active_version) === null) {
                continue;
            }

            $projection = $this->projections->projectAsset($assignment->asset_id, (string) $siteId);
            $entry = $projection === null
                ? null
                : $this->entries->fromProjection(ReadModelMapper::toRuntimeProjectionDTO($projection), $siteId);
            if ($entry === null) {
                continue;
            }

            $seen[$assignment->asset_id] = true;
            $entries[] = $entry;
        }

        return $entries;
    }
}
